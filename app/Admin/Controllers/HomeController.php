<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Leave;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\AttendanceStats;
use App\Services\LeaveWorkflow;
use App\Services\Scope;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The dashboard. People who look after staff see their scope (department,
 * faculty or university), filterable by faculty and department; everyone
 * else sees their own attendance.
 */
class HomeController extends Controller
{
    public function index(Content $content, Request $request)
    {
        /** @var User $me */
        $me = Admin::user();
        if (Scope::level($me) === Scope::SELF && !array_diff(AccessPolicy::rolesOf($me), ['employee'])) {
            return app(StaffDashboardController::class)->mine($content, $request);
        }

        $userIds = $this->filteredUserIds($me, $request);
        $month = $this->month($request);
        $monthEnd = $month->copy()->endOfMonth()->min(today());
        $thirtyDays = today()->subDays(29);
        $weekStart = today()->startOfWeek();

        $day = AttendanceStats::referenceDay($userIds);
        $trendFrom = today()->subDays(41);

        $data = [
            'day' => $day,
            'me' => $me,
            'scopeLabel' => Scope::label($me),
            'faculties' => Scope::faculties($me),
            'departments' => Scope::departments($me),
            'filters' => $request->only(['faculty', 'department', 'month']),
            'month' => $month,
            'today' => AttendanceStats::today($userIds, $day),
            'trendDaily' => AttendanceStats::daily($userIds, $trendFrom, today()),
            'weekly' => AttendanceStats::weekly($userIds, 12),
            'spread' => AttendanceStats::arrivalSpread($userIds, $month, $monthEnd),
            'monthSummary' => AttendanceStats::summary($userIds, $month, $monthEnd),
            'monthDaily' => AttendanceStats::daily($userIds, $month, $monthEnd),
            'week' => AttendanceStats::daily($userIds, $weekStart, today()),
            'topAbsent' => AttendanceStats::top($userIds, $thirtyDays, today(), 'absent'),
            'topLate' => AttendanceStats::top($userIds, $thirtyDays, today(), 'late'),
            'waiting' => AccessPolicy::allows($me, 'leave.approve') ? LeaveWorkflow::pendingFor($me)->limit(6)->get() : collect(),
            'waitingCount' => AccessPolicy::allows($me, 'leave.approve') ? LeaveWorkflow::pendingFor($me)->count() : 0,
            'upcomingLeave' => $this->upcomingLeave($userIds),
            'league' => $this->league($userIds, $month, $monthEnd),
            'setup' => \App\Services\SetupCheck::items($me),
            'offerDemo' => !$me->isDemo() && \App\Services\AccessPolicy::allows($me, 'demo.manage') && \App\Services\DemoSandbox::exists()
                && !\App\Models\AttendanceRecord::whereIn('user_id', $userIds ?? [0])->where('status', 'Present')
                    ->where('attendance_date', '>=', today()->subDays(14)->toDateString())->exists(),
            'greeting' => now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening'),
        ];

        return $content
            ->title($data['greeting'] . ', ' . explode(' ', $me->displayName())[0])
            ->description($data['scopeLabel'] . ' · ' . today()->format('l j F Y'))
            ->body(view('ehrms.dashboard', $data));
    }

    /** People in the viewer's scope, narrowed by the faculty / department filters. */
    private function filteredUserIds(User $me, Request $request): ?array
    {
        $ids = Scope::userIds($me);
        $departmentIds = null;

        if ($request->filled('department')) {
            $departmentIds = [(int) $request->input('department')];
        } elseif ($request->input('faculty') === 'admin') {
            $departmentIds = Department::where('type', Department::ADMINISTRATIVE)->pluck('id')->all();
        } elseif ($request->filled('faculty')) {
            $departmentIds = Department::where('faculty_id', (int) $request->input('faculty'))->pluck('id')->all();
        }

        if ($departmentIds === null) {
            return $ids;
        }
        // A filter can only narrow what the viewer may see.
        $allowed = Scope::departmentIds($me);
        if ($allowed !== null) {
            $departmentIds = array_values(array_intersect($departmentIds, $allowed));
        }
        $filtered = User::whereIn('department_id', $departmentIds ?: [0])->pluck('id')->all();

        return $ids === null ? $filtered : array_values(array_intersect($ids, $filtered));
    }

    private function month(Request $request): Carbon
    {
        try {
            $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth() : today()->startOfMonth();
        } catch (\Throwable $e) {
            $month = today()->startOfMonth();
        }

        return $month->gt(today()) ? today()->startOfMonth() : $month;
    }

    /**
     * Each department's attendance rate for the month, lowest first: where a
     * manager should look. Same figures as the summary report.
     */
    private function league(?array $userIds, Carbon $from, Carbon $to)
    {
        return AttendanceStats::perPerson($userIds, $from, $to)
            ->groupBy('department')
            ->map(function ($rows, $name) {
                $expected = $rows->sum('working') - $rows->sum('on_leave');

                return (object) [
                    'id' => $rows->first()->department_id,
                    'name' => $name,
                    'present' => $rows->sum('present'),
                    'expected' => $expected,
                    'staff' => $rows->count(),
                    'late' => $rows->sum('late'),
                    'rate' => $expected > 0 ? round(100 * $rows->sum('present') / $expected, 1) : null,
                ];
            })
            ->filter(fn ($d) => $d->rate !== null)
            ->sortBy('rate')
            ->values();
    }

    private function upcomingLeave(?array $userIds)
    {
        return Leave::with('user.department')
            ->whereIn('status', [Leave::APPROVED])
            ->where('end_date', '>=', today()->toDateString())
            ->where('start_date', '<=', today()->addDays(14)->toDateString())
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds ?: [0]))
            ->orderBy('start_date')
            ->limit(8)
            ->get();
    }
}
