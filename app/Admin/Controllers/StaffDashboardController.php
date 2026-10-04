<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Leave;
use App\Models\User;
use App\Services\AttendanceStats;
use App\Services\LeaveRules;
use App\Services\Scope;
use App\Services\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * One person's attendance: the month in figures and as a calendar, arrival
 * times over the last 30 days, their leave balance and requests.
 * Everyone has their own (/me); Heads, Deans and HR can open it for the
 * people in their scope (/staff/{id}).
 */
class StaffDashboardController extends Controller
{
    public function mine(Content $content, Request $request)
    {
        return $this->render($content, $request, Admin::user(), true);
    }

    public function show(Content $content, Request $request, $userId)
    {
        $user = User::with('department')->findOrFail($userId);
        abort_unless(Scope::canSee(Admin::user(), (int) $user->id), 403);

        return $this->render($content, $request, $user, (int) $user->id === (int) Admin::user()->id);
    }

    private function render(Content $content, Request $request, User $user, bool $isSelf)
    {
        try {
            $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth() : today()->startOfMonth();
        } catch (\Throwable $e) {
            $month = today()->startOfMonth();
        }
        if ($month->gt(today())) {
            $month = today()->startOfMonth();
        }
        $monthEnd = $month->copy()->endOfMonth();

        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$month->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->keyBy(fn ($r) => substr($r->attendance_date, 0, 10));

        $calendar = new WorkCalendar($month, $monthEnd);
        $gridStart = $month->copy()->startOfWeek();
        $gridEnd = $monthEnd->copy()->endOfWeek();
        $days = [];
        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $day) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $day->copy(),
                'in_month' => $day->month === $month->month,
                'record' => $records[$key] ?? null,
                'working' => $calendar->isWorkingDayFor($user, $day),
                'holiday' => $calendar->holidayName($day),
                'future' => $day->gt(today()),
            ];
        }

        $balance = LeaveRules::annualBalance($user);
        $leaves = Leave::where('user_id', $user->id)->orderByDesc('start_date')->limit(6)->get();
        $onLeaveNow = Leave::inForceOn(today())->where('user_id', $user->id)->first();

        $data = [
            'user' => $user,
            'isSelf' => $isSelf,
            'month' => $month,
            'summary' => AttendanceStats::summary([$user->id], $month, $monthEnd),
            'avgArrival' => optional(AttendanceStats::perPerson([$user->id], $month, $monthEnd)->first())->avg_arrival,
            'days' => $days,
            'arrivals' => AttendanceStats::arrivals($user->id, 30),
            'lateTime' => substr($user->lateTime(), 0, 5),
            'recent' => AttendanceRecord::where('user_id', $user->id)->where('attendance_date', '<=', today()->toDateString())
                ->orderByDesc('attendance_date')->limit(10)->get(),
            'balance' => $balance,
            'leaves' => $leaves,
            'onLeaveNow' => $onLeaveNow,
        ];

        $title = $isSelf ? 'My attendance' : $user->displayName();
        $description = collect([$user->position, optional($user->department)->name, $user->employee_no ? 'Staff no. ' . $user->employee_no : null])
            ->filter()->implode(' · ');

        if (!$isSelf) {
            $content->breadcrumb(['text' => 'Employees', 'url' => 'users'], ['text' => $user->displayName()]);
        }

        return $content->title($title)->description($description ?: $user->roleLabel())
            ->body(view('ehrms.staff-dashboard', $data));
    }
}
