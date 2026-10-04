<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every attendance figure the system shows — dashboards, staff pages and the
 * PDF reports all take their numbers from here, so they always agree.
 *
 * Definitions (as in the reference system):
 *  - Working days: days a person was expected in (a record on a working day).
 *  - Present: clocked in on a working day, on time or late.
 *  - Attendance rate: present ÷ (working days − days on leave).
 *  - Punctuality: on-time days ÷ present days.
 *  - Clock-ins on weekends and holidays are shown, but never count towards
 *    the rates.
 */
class AttendanceStats
{
    /**
     * Totals for a group of people over a period.
     *
     * @param  int[]|null  $userIds  null = everyone
     */
    public static function summary(?array $userIds, $from, $to): array
    {
        $row = self::records($userIds, $from, $to)
            ->selectRaw("COUNT(DISTINCT user_id) AS people")
            ->selectRaw("SUM(is_working_day = 1) AS working")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present') AS present")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present' AND is_late = 'No') AS on_time")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present' AND is_late = 'Yes') AS late")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Absent') AS absent")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'On Leave') AS on_leave")
            ->selectRaw("SUM(is_half_day = 1) AS half_days")
            ->selectRaw("SUM(is_working_day = 0 AND status = 'Present') AS off_day_present")
            ->selectRaw("COALESCE(SUM(late_minutes), 0) AS late_minutes")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'Present' THEN hours ELSE 0 END), 0) AS hours")
            ->first();

        return self::withRates([
            'people' => (int) $row->people,
            'working' => (int) $row->working,
            'present' => (int) $row->present,
            'on_time' => (int) $row->on_time,
            'late' => (int) $row->late,
            'absent' => (int) $row->absent,
            'on_leave' => (int) $row->on_leave,
            'half_days' => (int) $row->half_days,
            'off_day_present' => (int) $row->off_day_present,
            'late_minutes' => (int) $row->late_minutes,
            'hours' => round((float) $row->hours, 2),
        ]);
    }

    /**
     * One row per person, with their department, for tables and reports.
     *
     * @param  int[]|null  $userIds
     */
    public static function perPerson(?array $userIds, $from, $to): Collection
    {
        $rows = self::records($userIds, $from, $to)
            ->select('user_id')
            ->selectRaw("SUM(is_working_day = 1) AS working")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present') AS present")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present' AND is_late = 'No') AS on_time")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present' AND is_late = 'Yes') AS late")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Absent') AS absent")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'On Leave') AS on_leave")
            ->selectRaw("SUM(is_half_day = 1) AS half_days")
            ->selectRaw("COALESCE(SUM(late_minutes), 0) AS late_minutes")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'Present' THEN hours ELSE 0 END), 0) AS hours")
            ->selectRaw("AVG(CASE WHEN status = 'Present' AND is_working_day = 1 THEN TIME_TO_SEC(check_in_time) END) AS avg_arrival")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $people = User::with('department')
            ->when($userIds !== null, fn ($q) => $q->whereIn('id', $userIds))
            ->whereIn('id', $rows->keys())
            ->get();

        return $people->map(function (User $user) use ($rows) {
            $r = $rows[$user->id];

            return (object) array_merge(self::withRates([
                'working' => (int) $r->working,
                'present' => (int) $r->present,
                'on_time' => (int) $r->on_time,
                'late' => (int) $r->late,
                'absent' => (int) $r->absent,
                'on_leave' => (int) $r->on_leave,
                'half_days' => (int) $r->half_days,
                'late_minutes' => (int) $r->late_minutes,
                'hours' => round((float) $r->hours, 2),
            ]), [
                'user' => $user,
                'department' => optional($user->department)->name ?: 'No department',
                'department_id' => $user->department_id,
                'avg_arrival' => $r->avg_arrival !== null ? gmdate('H:i', (int) round($r->avg_arrival)) : null,
            ]);
        })->sortBy(fn ($p) => [$p->department, $p->user->name])->values();
    }

    /**
     * Counts per day, for the month chart and the daily register.
     *
     * @return array<string, array{present:int, late:int, absent:int, on_leave:int}>
     */
    public static function daily(?array $userIds, $from, $to): array
    {
        $rows = self::records($userIds, $from, $to)
            ->where('is_working_day', 1)
            ->select('attendance_date')
            ->selectRaw("SUM(status = 'Present' AND is_late = 'No') AS on_time")
            ->selectRaw("SUM(status = 'Present' AND is_late = 'Yes') AS late")
            ->selectRaw("SUM(status = 'Absent') AS absent")
            ->selectRaw("SUM(status = 'On Leave') AS on_leave")
            ->groupBy('attendance_date')
            ->get()
            ->keyBy('attendance_date');

        $days = [];
        foreach (CarbonPeriod::create(Carbon::parse($from), Carbon::parse($to)->min(today())) as $day) {
            $key = $day->toDateString();
            $r = $rows[$key] ?? null;
            $days[$key] = [
                'on_time' => (int) optional($r)->on_time,
                'late' => (int) optional($r)->late,
                'absent' => (int) optional($r)->absent,
                'on_leave' => (int) optional($r)->on_leave,
            ];
        }

        return $days;
    }

    /**
     * Who has the most absences / late arrivals over a period. People with none
     * are not listed.
     */
    public static function top(?array $userIds, $from, $to, string $what, int $limit = 5): Collection
    {
        $condition = $what === 'late'
            ? "is_working_day = 1 AND status = 'Present' AND is_late = 'Yes'"
            : "is_working_day = 1 AND status = 'Absent'";

        $rows = self::records($userIds, $from, $to)
            ->select('user_id')
            ->selectRaw("SUM({$condition}) AS n")
            ->selectRaw('COALESCE(SUM(late_minutes), 0) AS minutes')
            ->groupBy('user_id')
            ->havingRaw("SUM({$condition}) > 0")
            ->orderByDesc('n')
            ->orderByDesc('minutes')
            ->limit($limit)
            ->get();
        $users = User::with('department')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'user' => $users[$r->user_id] ?? null,
            'count' => (int) $r->n,
            'minutes' => (int) $r->minutes,
        ])->filter(fn ($r) => $r->user)->values();
    }

    /**
     * Today's picture: lists of people in, late, not in yet, and on leave.
     */
    public static function today(?array $userIds, $day = null): array
    {
        $day = $day ? Carbon::parse($day) : today();
        $records = self::records($userIds, $day, $day)
            ->where('is_working_day', 1)
            ->get(['user_id', 'status', 'is_late', 'check_in_time', 'late_minutes']);
        $users = User::with('department')->whereIn('id', $records->pluck('user_id'))->get()->keyBy('id');
        $attach = fn ($rows) => $rows->map(function ($r) use ($users) {
            $r->user = $users[$r->user_id] ?? null;

            return $r;
        })->filter(fn ($r) => $r->user)->sortBy(fn ($r) => $r->check_in_time ?: $r->user->name)->values();

        $present = $records->where('status', 'Present');

        return [
            'expected' => $records->count(),
            'in' => $attach($present),
            'late' => $attach($present->where('is_late', 'Yes')),
            'not_in' => $attach($records->where('status', 'Absent')),
            'on_leave' => $attach($records->where('status', 'On Leave')),
        ];
    }

    /**
     * The day the "today" figures describe: today when anyone is expected in,
     * otherwise (weekend, public holiday) the most recent working day in the
     * last two weeks, so the dashboard never opens on a row of zeros.
     */
    public static function referenceDay(?array $userIds): Carbon
    {
        // Expected head-count per day over the last two weeks. A day counts as
        // a normal working day when at least half the usual number were
        // expected; a Saturday with two people on a six-day pattern does not.
        $counts = self::records($userIds, today()->subDays(14), today())
            ->where('is_working_day', 1)
            ->selectRaw('attendance_date, COUNT(*) AS n')
            ->groupBy('attendance_date')
            ->pluck('n', 'attendance_date');

        if ($counts->isEmpty()) {
            return today();
        }
        $usual = $counts->max();
        $day = $counts->filter(fn ($n) => $n >= $usual / 2)->keys()->sort()->last();

        return Carbon::parse($day);
    }

    /**
     * Attendance rate and punctuality per week (Monday to Sunday) for the last
     * $weeks weeks, the current week included.
     *
     * @return array<int, array{week:string, label:string, rate:?float, punctuality:?float, working:int}>
     */
    public static function weekly(?array $userIds, int $weeks = 12): array
    {
        $start = today()->startOfWeek()->subWeeks($weeks - 1);
        $rows = self::records($userIds, $start, today())
            ->selectRaw("DATE_SUB(attendance_date, INTERVAL WEEKDAY(attendance_date) DAY) AS week")
            ->selectRaw("SUM(is_working_day = 1) AS working")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present') AS present")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'Present' AND is_late = 'No') AS on_time")
            ->selectRaw("SUM(is_working_day = 1 AND status = 'On Leave') AS on_leave")
            ->groupBy('week')
            ->get()
            ->keyBy('week');

        $out = [];
        for ($w = $start->copy(); $w->lte(today()); $w->addWeek()) {
            $r = $rows[$w->toDateString()] ?? null;
            $s = self::withRates([
                'working' => (int) optional($r)->working,
                'present' => (int) optional($r)->present,
                'on_time' => (int) optional($r)->on_time,
                'on_leave' => (int) optional($r)->on_leave,
            ]);
            $out[] = [
                'week' => $w->toDateString(),
                'label' => $w->format('j M'),
                'rate' => $s['rate'],
                'punctuality' => $s['punctuality'],
                'working' => $s['working'],
            ];
        }

        return $out;
    }

    /**
     * How many arrivals fall in each quarter hour, split into on time and
     * late, for working days in the period. Arrivals before 06:30 and from
     * 10:00 are gathered into the first and last bars.
     *
     * @return array<int, array{label:string, on_time:int, late:int}>
     */
    public static function arrivalSpread(?array $userIds, $from, $to): array
    {
        $first = 26; // 06:30 in quarter hours
        $last = 40;  // 10:00
        $rows = self::records($userIds, $from, $to)
            ->where('is_working_day', 1)
            ->where('status', 'Present')
            ->whereNotNull('check_in_time')
            ->selectRaw("LEAST({$last}, GREATEST({$first} - 1, FLOOR(TIME_TO_SEC(check_in_time) / 900))) AS slot")
            ->selectRaw("SUM(is_late = 'No') AS on_time")
            ->selectRaw("SUM(is_late = 'Yes') AS late")
            ->groupBy('slot')
            ->get()
            ->keyBy('slot');

        $out = [];
        for ($slot = $first - 1; $slot <= $last; $slot++) {
            $r = $rows[$slot] ?? null;
            $time = sprintf('%02d:%02d', intdiv(max($slot, $first) * 15, 60), (max($slot, $first) * 15) % 60);
            $out[] = [
                'label' => $slot < $first ? 'before 06:30' : ($slot === $last ? '10:00 +' : $time),
                'on_time' => (int) optional($r)->on_time,
                'late' => (int) optional($r)->late,
            ];
        }

        return $out;
    }

    /** Average arrival over the last $days days, as minutes after midnight per day (for the chart). */
    public static function arrivals(int $userId, int $days = 30): array
    {
        $from = today()->subDays($days - 1);
        $rows = DB::table('attendance_records')
            ->where('user_id', $userId)
            ->whereBetween('attendance_date', [$from->toDateString(), today()->toDateString()])
            ->where('status', 'Present')
            ->where('is_working_day', 1)
            ->orderBy('attendance_date')
            ->get(['attendance_date', 'check_in_time', 'hours']);

        return $rows->map(fn ($r) => [
            'date' => $r->attendance_date,
            'minutes' => (int) substr($r->check_in_time, 0, 2) * 60 + (int) substr($r->check_in_time, 3, 2),
            'hours' => round((float) $r->hours, 2),
        ])->all();
    }

    /** Base query: records in the period (never beyond today) for the given people. */
    public static function records(?array $userIds, $from, $to): Builder
    {
        $to = Carbon::parse($to)->min(today());

        return DB::table('attendance_records')
            ->whereBetween('attendance_date', [Carbon::parse($from)->toDateString(), $to->toDateString()])
            ->when($userIds !== null, fn ($q) => $q->whereIn('user_id', $userIds ?: [0]));
    }

    private static function withRates(array $s): array
    {
        $expected = $s['working'] - $s['on_leave'];
        $s['rate'] = $expected > 0 ? round(100 * $s['present'] / $expected, 1) : null;
        $s['punctuality'] = $s['present'] > 0 ? round(100 * $s['on_time'] / $s['present'], 1) : null;

        return $s;
    }
}
