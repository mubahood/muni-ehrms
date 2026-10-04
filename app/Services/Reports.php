<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Leave;
use App\Models\SystemConfiguration;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * The PDF reports. Each takes its figures from AttendanceStats — the same
 * source as the dashboards — so a report and the screen always agree.
 */
class Reports
{
    /*
    |--------------------------------------------------------------------------
    | Choosing what a report covers
    |--------------------------------------------------------------------------
    */

    /**
     * The groups a person may report on: the whole university, faculties,
     * administrative units, departments — limited to their scope.
     *
     * @return array<string, string> value => label
     */
    public static function scopeOptions(User $viewer): array
    {
        $options = [];
        if (Scope::level($viewer) === Scope::UNIVERSITY) {
            $options['university'] = 'Whole university';
            $options['admin'] = 'Administrative units';
        }
        foreach (Scope::faculties($viewer) as $f) {
            $options['faculty:' . $f->id] = $f->name;
        }
        foreach (Scope::departments($viewer) as $d) {
            $options['department:' . $d->id] = $d->name;
        }

        return $options;
    }

    /**
     * [label, user ids] for a scope value, never wider than the viewer's scope.
     *
     * @return array{0:string, 1:?array}
     */
    public static function resolveScope(User $viewer, string $value): array
    {
        $options = self::scopeOptions($viewer);
        if (!isset($options[$value])) {
            abort(403, 'You cannot report on that group.');
        }
        $allowed = Scope::userIds($viewer);
        $narrow = function (array $departmentIds) use ($allowed) {
            $ids = User::whereIn('department_id', $departmentIds ?: [0])->pluck('id')->all();

            return $allowed === null ? $ids : array_values(array_intersect($ids, $allowed));
        };

        if ($value === 'university') {
            return [$options[$value], $allowed];
        }
        if ($value === 'admin') {
            return [$options[$value], $narrow(Department::where('type', Department::ADMINISTRATIVE)->pluck('id')->all())];
        }
        [$kind, $id] = explode(':', $value);
        if ($kind === 'faculty') {
            return [$options[$value], $narrow(Department::where('faculty_id', (int) $id)->pluck('id')->all())];
        }

        return [$options[$value], $narrow([(int) $id])];
    }

    /**
     * Named periods offered on the reports page.
     *
     * @return array{0:Carbon, 1:Carbon}
     */
    public static function period(?string $preset, $from = null, $to = null): array
    {
        $today = today();
        switch ($preset) {
            case 'this_week':
                return [$today->copy()->startOfWeek(), $today];
            case 'last_week':
                return [$today->copy()->subWeek()->startOfWeek(), $today->copy()->subWeek()->endOfWeek()];
            case 'last_month':
                return [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()];
            case 'last_30':
                return [$today->copy()->subDays(29), $today];
            case 'this_year':
                return [$today->copy()->startOfYear(), $today];
            case 'leave_year':
                $config = SystemConfiguration::current();
                [$start] = $config->leaveYearBounds($config->leaveYearOf($today));

                return [$start->copy()->startOfDay(), $today];
            case 'custom':
                $start = $from ? Carbon::parse($from)->startOfDay() : $today->copy()->startOfMonth();
                $end = $to ? Carbon::parse($to)->startOfDay() : $today;
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }

                return [$start, $end->min($today)];
            default:
                return [$today->copy()->startOfMonth(), $today];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The reports
    |--------------------------------------------------------------------------
    */

    /** One person, day by day: first and last seen, hours, status. */
    public static function individual(User $user, Carbon $from, Carbon $to): array
    {
        $to = $to->copy()->min(today());
        $records = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get()->keyBy(fn ($r) => substr($r->attendance_date, 0, 10));
        $calendar = new WorkCalendar($from, $to);

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $record = $records[$key] ?? null;
            if (!$record && !$calendar->isWorkingDayFor($user, $day)) {
                $days[] = ['date' => $day->copy(), 'record' => null, 'off' => $calendar->holidayName($day) ?: ($day->isWeekend() ? 'Weekend' : 'Day off')];
                continue;
            }
            $days[] = ['date' => $day->copy(), 'record' => $record, 'off' => $record ? null : 'Not tracked'];
        }

        $person = AttendanceStats::perPerson([$user->id], $from, $to)->first();

        return [
            'title' => 'Individual Attendance Report',
            'subtitle' => $user->displayName() . ' · ' . self::range($from, $to),
            'user' => $user->loadMissing('department.faculty'),
            'from' => $from,
            'to' => $to,
            'summary' => AttendanceStats::summary([$user->id], $from, $to),
            'avgArrival' => optional($person)->avg_arrival,
            'days' => $days,
            'leave' => Leave::where('user_id', $user->id)->whereIn('status', [Leave::APPROVED, Leave::RECALLED])
                ->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())
                ->orderBy('start_date')->get(),
            'fullDay' => SystemConfiguration::current()->full_day_hours,
        ];
    }

    /** A group over a period: KPIs and one row per person, grouped by department. */
    public static function summary(string $label, ?array $userIds, Carbon $from, Carbon $to): array
    {
        $to = $to->copy()->min(today());
        $people = AttendanceStats::perPerson($userIds, $from, $to);
        $departments = $people->groupBy('department')->map(function (Collection $rows, $name) {
            $working = $rows->sum('working');
            $leave = $rows->sum('on_leave');
            $present = $rows->sum('present');

            return (object) [
                'name' => $name,
                'rows' => $rows,
                'staff' => $rows->count(),
                'rate' => $working - $leave > 0 ? round(100 * $present / ($working - $leave), 1) : null,
            ];
        })->sortBy('name')->values();

        return [
            'title' => 'Attendance Summary Report',
            'subtitle' => $label . ' · ' . self::range($from, $to) . ' · ' . (new WorkCalendar($from, $to))->countWorkingDays($from, $to) . ' working day(s)',
            'from' => $from,
            'to' => $to,
            'summary' => AttendanceStats::summary($userIds, $from, $to),
            'departments' => $departments,
            'label' => $label,
            'daily' => self::normalDays(AttendanceStats::daily($userIds, $from, $to)),
            'lowest' => $people->filter(fn ($p) => $p->working >= 3 && $p->rate !== null)->sortBy('rate')->take(6)->values(),
            'latest' => $people->filter(fn ($p) => $p->late > 0)->sortByDesc(fn ($p) => [$p->late, $p->late_minutes])->take(6)->values(),
        ];
    }

    /**
     * Days on which the usual number of people were expected (at least half
     * the busiest day), so a Saturday with two people on a six-day pattern
     * does not appear as a nearly empty day.
     */
    public static function normalDays(array $daily): array
    {
        $usual = max(array_map('array_sum', $daily) ?: [0]);

        return array_filter($daily, fn ($d) => array_sum($d) > 0 && array_sum($d) >= $usual / 2);
    }

    /** One day: everyone in the group with arrival, departure and status. */
    public static function daily(string $label, ?array $userIds, Carbon $date): array
    {
        $date = $date->copy()->min(today());
        $records = AttendanceStats::records($userIds, $date, $date)->get()->keyBy('user_id');
        $people = User::with('department')->whereIn('id', $records->keys())->get()
            ->map(function (User $u) use ($records) {
                $r = new AttendanceRecord((array) $records[$u->id]);

                return (object) ['user' => $u, 'record' => $r, 'department' => optional($u->department)->name ?: 'No department'];
            })
            ->sortBy(fn ($p) => [$p->department, $p->user->name])
            ->groupBy('department');

        return [
            'title' => 'Daily Attendance Register',
            'subtitle' => $label . ' · ' . $date->format('l j F Y'),
            'date' => $date,
            'summary' => AttendanceStats::summary($userIds, $date, $date),
            'groups' => $people,
            'holiday' => (new WorkCalendar($date, $date))->holidayName($date),
        ];
    }

    /** Leave over a period: requests by type and status, and annual leave by person. */
    public static function leave(string $label, ?array $userIds, Carbon $from, Carbon $to): array
    {
        $leaves = Leave::with('user.department')
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds ?: [0]))
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->orderBy('start_date')
            ->get();

        $byType = collect(Leave::TYPES)->map(function ($typeLabel, $type) use ($leaves) {
            $rows = $leaves->where('leave_type', $type);

            return (object) [
                'label' => $typeLabel,
                'requests' => $rows->count(),
                'approved' => $rows->whereIn('status', [Leave::APPROVED, Leave::RECALLED])->count(),
                'pending' => $rows->where('status', Leave::PENDING)->count(),
                'declined' => $rows->where('status', Leave::REJECTED)->count(),
                'days' => $rows->whereIn('status', [Leave::APPROVED, Leave::RECALLED])->sum(fn ($l) => $l->daysTaken()),
            ];
        })->filter(fn ($r) => $r->requests > 0)->values();

        $year = LeaveRules::leaveYearOf($to);
        $people = User::with('department')->where('status', 'Active')
            ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds ?: [0]))
            ->get()
            ->map(fn (User $u) => (object) ['user' => $u, 'balance' => LeaveRules::annualBalance($u, $year)])
            ->sortBy(fn ($r) => [optional($r->user->department)->name, $r->user->name])
            ->values();

        return [
            'title' => 'Leave Report',
            'subtitle' => $label . ' · ' . self::range($from, $to),
            'from' => $from,
            'to' => $to,
            'leaves' => $leaves,
            'byType' => $byType,
            'people' => $people,
            'year' => $year,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    public static function pdf(string $view, array $data, string $orientation, string $generatedBy)
    {
        return Pdf::loadView($view, $data + [
            'config' => SystemConfiguration::current(),
            'generatedBy' => $generatedBy,
            'generatedAt' => now(),
        ])->setPaper('a4', $orientation)->setOption('isPhpEnabled', true)->setOption('isRemoteEnabled', false)
            // Embed only the characters used: a one-page report stays small.
            ->setOption('isFontSubsettingEnabled', true);
    }

    /**
     * A report as CSV: a title block, then one row per person (or day, or
     * request). UTF-8 with a byte-order mark so Excel shows names correctly.
     */
    public static function csv(string $type, array $data, string $filename)
    {
        $pct = fn ($v) => $v === null ? '' : number_format($v, 1);
        $rows = [[$data['title']], [$data['subtitle']], ['Generated ' . now()->format('d M Y H:i')], []];

        switch ($type) {
            case 'summary':
                $rows[] = ['Department', 'Staff no.', 'Name', 'Job title', 'Working days', 'Present', 'On time', 'Late', 'Absent', 'On leave', 'Half days', 'Late (min)', 'Hours', 'Avg arrival', 'Attendance %', 'Punctuality %'];
                foreach ($data['departments'] as $d) {
                    foreach ($d->rows as $p) {
                        $rows[] = [$d->name, $p->user->employee_no, $p->user->name, $p->user->position, $p->working, $p->present, $p->on_time, $p->late, $p->absent,
                            $p->on_leave, $p->half_days, $p->late_minutes, number_format($p->hours, 2, '.', ''), $p->avg_arrival, $pct($p->rate), $pct($p->punctuality)];
                    }
                }
                $t = $data['summary'];
                $rows[] = ['All', '', $t['people'] . ' staff', '', $t['working'], $t['present'], $t['on_time'], $t['late'], $t['absent'], $t['on_leave'], $t['half_days'],
                    $t['late_minutes'], number_format($t['hours'], 2, '.', ''), '', $pct($t['rate']), $pct($t['punctuality'])];
                break;
            case 'daily':
                $rows[] = ['Department', 'Staff no.', 'Name', 'Job title', 'Status', 'First seen', 'Last seen', 'Late (min)', 'Hours'];
                foreach ($data['groups'] as $department => $people) {
                    foreach ($people as $p) {
                        $r = $p->record;
                        $rows[] = [$department, $p->user->employee_no, $p->user->name, $p->user->position, $r->statusLabel(),
                            $r->check_in_time ? substr($r->check_in_time, 0, 5) : '', $r->check_out_time ? substr($r->check_out_time, 0, 5) : '',
                            (int) $r->late_minutes, number_format((float) $r->hours, 2, '.', '')];
                    }
                }
                break;
            case 'individual':
                $rows[] = ['Name', $data['user']->name, 'Staff no.', $data['user']->employee_no];
                $rows[] = [];
                $rows[] = ['Date', 'Day', 'Status', 'First seen', 'Last seen', 'Late (min)', 'Hours', 'Note'];
                foreach ($data['days'] as $d) {
                    $r = $d['record'];
                    $rows[] = [$d['date']->toDateString(), $d['date']->format('D'), $r ? $r->statusLabel() : $d['off'],
                        $r && $r->check_in_time ? substr($r->check_in_time, 0, 5) : '', $r && $r->check_out_time ? substr($r->check_out_time, 0, 5) : '',
                        $r ? (int) $r->late_minutes : '', $r ? number_format((float) $r->hours, 2, '.', '') : '', $r && $r->is_half_day ? 'Half day: not seen leaving' : ''];
                }
                break;
            case 'leave':
                $rows[] = ['Reference', 'Staff no.', 'Name', 'Department', 'Leave type', 'From', 'To', 'Working days', 'Status'];
                foreach ($data['leaves'] as $l) {
                    $rows[] = [$l->reference, $l->user->employee_no, $l->user->name, optional($l->user->department)->name, $l->typeLabel(),
                        $l->start_date->toDateString(), $l->end_date->toDateString(), $l->days, $l->statusLabel()];
                }
                break;
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                // Cells starting with = + - @ are prefixed so a spreadsheet never runs them as formulas.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function range(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('d M Y');
        }

        return $from->format('d M Y') . ' – ' . $to->format('d M Y');
    }

    /** "7h 15m" from hours. */
    public static function hm(float $hours): string
    {
        return \App\Admin\Controllers\AttendanceRecordController::hm($hours);
    }
}
