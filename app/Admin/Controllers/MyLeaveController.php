<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use App\Services\LeaveRules;
use App\Services\LeaveWorkflow;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * Everyone's own leave: their requests, their balance, and the application
 * form — Section I of the University's leave form.
 */
class MyLeaveController extends Controller
{
    public function index(Content $content)
    {
        /** @var User $me */
        $me = Admin::user();

        return $content->title('My leave')
            ->description('Your requests, where they are, and your annual leave balance')
            ->body(view('ehrms.leave.mine', [
                'me' => $me,
                'balance' => LeaveRules::annualBalance($me),
                'leaves' => Leave::where('user_id', $me->id)->orderByDesc('start_date')->get(),
                'last' => LeaveRules::lastLeaveTaken($me),
            ]));
    }

    public function create(Content $content)
    {
        /** @var User $me */
        $me = Admin::user();

        return $content->breadcrumb(['text' => 'My leave', 'url' => 'my-leave'], ['text' => 'Apply'])->title('Apply for leave')
            ->description('Leave application form · Section I')
            ->body(view('ehrms.leave.apply', [
                'me' => $me,
                'balance' => LeaveRules::annualBalance($me),
                'last' => LeaveRules::lastLeaveTaken($me),
                'route' => LeaveWorkflow::buildRoute($me),
                'colleagues' => User::where('status', 'Active')->where('id', '!=', $me->id)->where('is_demo', $me->isDemo())
                    ->when($me->department_id, fn ($q) => $q->where('department_id', $me->department_id))
                    ->orderBy('name')->get(['id', 'name', 'position']),
            ]));
    }

    public function store(Request $request)
    {
        /** @var User $me */
        $me = Admin::user();
        $request->validate([
            'leave_type' => 'required|in:' . implode(',', Leave::FORM_TYPES),
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'required|string|min:3|max:1000',
            'acting_user_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('is_demo', $me->isDemo())->where('status', 'Active')],
            'contact_address' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:40',
        ], [], ['leave_type' => 'type of leave', 'acting_user_id' => 'staff left in charge']);

        [$days, $year, $errors] = LeaveRules::check($me, $request->input('leave_type'), $request->input('start_date'), $request->input('end_date'));
        if ($errors) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $errors[0], 'errors' => ['start_date' => $errors]], 422);
            }

            return back()->withInput()->withErrors(['dates' => $errors]);
        }

        $leave = new Leave([
            'user_id' => $me->id,
            'department_id' => $me->department_id,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'days' => $days,
            'leave_year' => $year,
            'return_date' => LeaveRules::returnDateAfter($request->input('end_date')),
            'reason' => trim($request->input('reason')),
            'acting_user_id' => $request->input('acting_user_id') ?: null,
            'contact_address' => $request->input('contact_address'),
            'contact_phone' => $request->input('contact_phone'),
        ]);
        $leave->setRelation('user', $me);
        $leave = LeaveWorkflow::submit($leave, $me);

        $message = 'Your application was submitted and is with the ' . $leave->stageLabel() . '.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => admin_url('leave/' . $leave->id)]);
        }
        admin_toastr($message);

        return redirect(admin_url('leave/' . $leave->id));
    }

    /**
     * Live check while the form is filled in: working days, return date,
     * balance after this request, and anything that would stop it.
     */
    public function check(Request $request)
    {
        /** @var User $me */
        $me = Admin::user();
        $type = (string) $request->input('leave_type', 'annual');
        $start = $request->input('start_date');
        $end = $request->input('end_date');
        if (!$start || !$end) {
            return response()->json(['ready' => false]);
        }

        [$days, $year, $errors] = LeaveRules::check($me, $type, $start, $end);
        $balance = $year ? LeaveRules::annualBalance($me, $year) : null;

        return response()->json([
            'ready' => true,
            'days' => $days,
            'return_date' => $days ? LeaveRules::returnDateAfter($end)->format('l j F Y') : null,
            'year' => $year ? LeaveRules::yearLabel($year) : null,
            'available' => $balance ? $balance->available() : null,
            'after' => $balance && $type === Leave::ANNUAL ? $balance->available() - $days : null,
            'errors' => $errors,
        ]);
    }
}
