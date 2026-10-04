<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\LeaveEntitlement;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Audit;
use App\Services\LeaveRules;
use App\Services\LeaveWorkflow;
use App\Services\Scope;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leave for the people who look after it: the approvals queue, every request
 * in one's scope, Human Resource's leave planning, and recording leave that
 * was approved on paper.
 */
class LeaveAdminController extends Controller
{
    public function approvals(Content $content)
    {
        /** @var User $me */
        $me = Admin::user();

        return $content->title('Leave approvals')
            ->description('Requests waiting for your decision')
            ->body(view('ehrms.leave.approvals', [
                'waiting' => LeaveWorkflow::pendingFor($me)->get(),
                'recent' => LeaveAction::with('leave.user.department')
                    ->where('actor_id', $me->id)
                    ->whereIn('action', [LeaveAction::RECOMMENDED, LeaveAction::VERIFIED, LeaveAction::APPROVED, LeaveAction::REJECTED])
                    ->orderByDesc('created_at')->limit(10)->get(),
            ]));
    }

    public function all(Content $content, Request $request)
    {
        /** @var User $me */
        $me = Admin::user();
        $base = Leave::query()->with('user.department');
        Scope::apply($base, $me, 'user_id');

        $filtered = (clone $base)
            ->when($request->filled('type'), fn ($q) => $q->where('leave_type', $request->input('type')))
            ->when($request->filled('department'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('department_id', (int) $request->input('department'))))
            ->when($request->filled('year'), fn ($q) => $q->where('leave_year', (int) $request->input('year')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)));
            })
            ->when($request->input('when') === 'now', fn ($q) => $q->inForceOn(today()))
            ->when($request->input('when') === 'upcoming', fn ($q) => $q->where('status', Leave::APPROVED)->where('start_date', '>', today()->toDateString()));

        $counts = (clone $filtered)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $status = $request->input('status');
        $leaves = (clone $filtered)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->orderByDesc('start_date')
            ->paginate(30)
            ->withQueryString();

        return $content->title('All leave')
            ->description(Scope::label($me))
            ->body(view('ehrms.leave.all', [
                'leaves' => $leaves,
                'counts' => $counts,
                'status' => $status,
                'filters' => $request->only(['type', 'department', 'year', 'q', 'when']),
                'departments' => Scope::departments($me),
                'years' => Leave::whereNotNull('leave_year')->distinct()->orderByDesc('leave_year')->pluck('leave_year'),
            ]));
    }

    /*
    |--------------------------------------------------------------------------
    | Leave planning (Human Resource)
    |--------------------------------------------------------------------------
    */

    public function planning(Content $content, Request $request)
    {
        $year = (int) $request->input('year', LeaveRules::currentLeaveYear());
        $departmentId = $request->input('department');

        $people = Scope::apply(User::with('department')->where('status', 'Active'), Admin::user())
            ->when($departmentId, fn ($q) => $q->where('department_id', (int) $departmentId))
            ->orderBy('name')->get();
        $entitlements = LeaveEntitlement::where('leave_year', $year)->whereIn('user_id', $people->pluck('id'))->get()->keyBy('user_id');
        $rows = $people->map(function (User $u) use ($year, $entitlements) {
            $balance = LeaveRules::annualBalance($u, $year);

            return (object) ['user' => $u, 'entitlement' => $entitlements[$u->id] ?? null, 'balance' => $balance];
        })->sortBy(fn ($r) => [optional($r->user->department)->name, $r->user->name])->values();

        [$from, $to] = SystemConfiguration::current()->leaveYearBounds($year);

        return $content->title('Leave planning')
            ->description('Annual leave allocated for ' . LeaveRules::yearLabel($year) . ' (' . $from->format('d M Y') . ' – ' . $to->format('d M Y') . ')')
            ->body(view('ehrms.leave.planning', [
                'rows' => $rows,
                'year' => $year,
                'years' => range(LeaveRules::currentLeaveYear() + 1, LeaveRules::currentLeaveYear() - 3),
                'departments' => Department::where('is_active', true)->orderBy('name')->get(),
                'departmentId' => $departmentId,
                'defaultDays' => SystemConfiguration::current()->annual_leave_days ?: 30,
                'unallocated' => $rows->filter(fn ($r) => !$r->entitlement)->count(),
            ]));
    }

    public function savePlanning(Request $request)
    {
        $year = (int) $request->input('year');
        abort_unless($year > 2000 && $year < 2100, 422);
        $me = Admin::user();
        $mode = $request->input('mode');
        $changed = 0;

        DB::transaction(function () use ($request, $year, $me, $mode, &$changed) {
            if ($mode === 'rows') {
                $allowed = array_flip(Scope::userIds($me) ?? []);
                foreach ((array) $request->input('due', []) as $userId => $due) {
                    if (!isset($allowed[(int) $userId])) {
                        continue; // only people in the viewer's scope
                    }
                    $carried = $request->input("carried.{$userId}");
                    if ($due === null || $due === '') {
                        continue;
                    }
                    $this->allocate((int) $userId, $year, (int) $due, (int) $carried, $me->id, $changed);
                }
            } elseif ($mode === 'bulk') {
                $request->validate(['bulk_days' => 'required|integer|min:0|max:120']);
                $people = Scope::apply(User::where('status', 'Active'), $me)
                    ->when($request->filled('bulk_department'), fn ($q) => $q->where('department_id', (int) $request->input('bulk_department')))
                    ->pluck('id');
                foreach ($people as $userId) {
                    $current = LeaveEntitlement::where('user_id', $userId)->where('leave_year', $year)->first();
                    if ($current && $request->input('bulk_only_missing')) {
                        continue;
                    }
                    $this->allocate($userId, $year, (int) $request->input('bulk_days'), $current ? $current->carried_forward : 0, $me->id, $changed);
                }
            } elseif ($mode === 'carry') {
                $request->validate(['carry_cap' => 'required|integer|min:0|max:120']);
                $cap = (int) $request->input('carry_cap');
                foreach (Scope::apply(User::where('status', 'Active'), $me)->get() as $user) {
                    $previous = LeaveRules::annualBalance($user, $year - 1);
                    if (!$previous->hasAllocation) {
                        continue;
                    }
                    $carry = max(0, min($previous->balance(), $cap));
                    $current = LeaveEntitlement::where('user_id', $user->id)->where('leave_year', $year)->first();
                    $due = $current ? $current->days_due : (SystemConfiguration::current()->annual_leave_days ?: 30);
                    $this->allocate($user->id, $year, $due, $carry, $me->id, $changed);
                }
            }
        });

        Audit::log('leave.planning', "Leave allocations for " . LeaveRules::yearLabel($year) . ": {$changed} changed ({$mode})");
        admin_toastr("{$changed} allocation(s) saved for " . LeaveRules::yearLabel($year) . '.');

        return redirect(admin_url('leave/planning') . '?' . http_build_query(array_filter(['year' => $year, 'department' => $request->input('department')])));
    }

    private function allocate(int $userId, int $year, int $due, int $carried, int $by, int &$changed): void
    {
        $due = max(0, min($due, 365));
        $carried = max(0, min($carried, 365));
        $entitlement = LeaveEntitlement::firstOrNew(['user_id' => $userId, 'leave_year' => $year]);
        if (!$entitlement->exists || $entitlement->days_due !== $due || $entitlement->carried_forward !== $carried) {
            $entitlement->fill(['days_due' => $due, 'carried_forward' => $carried, 'updated_by' => $by])->save();
            $changed++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Leave recorded by Human Resource
    |--------------------------------------------------------------------------
    */

    public function record(Content $content)
    {
        return $content->title('Record leave')
            ->description('Leave approved on paper, or official duty and travel')
            ->body(view('ehrms.leave.record', [
                'people' => Scope::apply(User::with('department')->where('status', 'Active'), Admin::user())->orderBy('name')->get(),
            ]));
    }

    public function storeRecord(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'leave_type' => 'required|in:' . implode(',', array_keys(Leave::TYPES)),
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|min:3|max:1000',
        ], [], ['user_id' => 'employee', 'leave_type' => 'type of leave']);

        $user = User::findOrFail($request->input('user_id'));
        abort_unless(Scope::canSee(Admin::user(), (int) $user->id), 403);
        [$days, $year, $errors] = LeaveRules::check($user, $request->input('leave_type'), $request->input('start_date'), $request->input('end_date'), null, true, true);
        if ($errors) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $errors[0], 'errors' => ['start_date' => $errors]], 422);
            }

            return back()->withInput()->withErrors(['dates' => $errors]);
        }

        $leave = LeaveWorkflow::recordByHr(new Leave([
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'days' => $days,
            'leave_year' => $year,
            'return_date' => LeaveRules::returnDateAfter($request->input('end_date')),
            'reason' => trim($request->input('reason')),
        ]), Admin::user());

        $message = "{$leave->typeLabel()} recorded for {$user->name}: {$days} working day(s).";
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => admin_url('leave/' . $leave->id)]);
        }
        admin_toastr($message);

        return redirect(admin_url('leave/' . $leave->id));
    }
}
