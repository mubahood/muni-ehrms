<?php

namespace App\Services;

use App\Models\EventLog;
use App\Models\Leave;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out each person's attendance for a day from the raw clock-ins.
 *
 * A day is always rebuilt from scratch — the clock-ins, approved leave and the
 * calendar — so running it again gives the same answer, and approving leave,
 * recalling someone or adding a holiday corrects the days concerned. Records
 * corrected by Human Resource, and records imported from files, are left as
 * they are.
 *
 * The rules (the University's, as in the reference system):
 *  - First capture of the day is the arrival; the last is the departure.
 *  - A capture less than `repeat_capture_minutes` after the arrival is a
 *    repeat at the door, not a departure.
 *  - Late when the arrival, to the minute, is after the person's late time
 *    (08:30 → 08:30:59 is on time, 08:31 is late). Only on working days.
 *  - Absent on a working day with no clock-in and no leave in force; for today
 *    that means "not in yet".
 *  - On leave on a working day covered by approved leave. A clock-in on a
 *    leave day still counts as present.
 *  - Weekends and public holidays are never absent or late; a clock-in on
 *    one is recorded as present.
 *  - Hours = departure − arrival. With no departure the day is credited as a
 *    half day (half the standard day), except today, which is still in progress.
 */
class AttendanceEngine
{
    public const PRESENT = 'Present';
    public const ABSENT = 'Absent';
    public const ON_LEAVE = 'On Leave';

    /** Hikvision access-control (major 5) results that mean "this person authenticated". */
    public const CLOCK_IN_MINORS = [
        1,  // valid card
        38, // fingerprint matched
        75, // face recognised
    ];

    /**
     * Rebuild every person's record for one day.
     *
     * @param  int[]|null  $userIds  only these people (null = everyone expected)
     * @return array<string, int> number of records by status
     */
    public function processDay($date, ?array $userIds = null, ?WorkCalendar $calendar = null): array
    {
        $date = Carbon::parse($date)->startOfDay();
        if ($date->gt(today())) {
            return [];
        }
        $day = $date->toDateString();
        $calendar = $calendar ?: new WorkCalendar($day, $day);

        $users = $this->expectedUsers($date, $userIds);
        if ($users->isEmpty()) {
            return [];
        }
        $ids = $users->pluck('id')->all();

        $punches = $this->clockInsOn($day, $ids);
        $onLeave = array_flip(Leave::inForceOn($day)->whereIn('user_id', $ids)->pluck('user_id')->all());
        $existing = DB::table('attendance_records')
            ->where('attendance_date', $day)
            ->whereIn('user_id', $ids)
            ->get()
            ->keyBy('user_id');

        $counts = [];
        $now = now();
        foreach ($users as $user) {
            $record = $existing->get($user->id);
            if ($record && $this->isProtected($record)) {
                $counts[$record->status] = ($counts[$record->status] ?? 0) + 1;
                continue;
            }

            $values = $this->evaluate($user, $date, $punches[$user->id] ?? [], isset($onLeave[$user->id]), $calendar);

            if ($values === null) {
                if ($record) {
                    DB::table('attendance_records')->where('id', $record->id)->delete();
                }
                continue;
            }

            $counts[$values['status']] = ($counts[$values['status']] ?? 0) + 1;
            if ($record) {
                if ($this->differs($record, $values)) {
                    DB::table('attendance_records')->where('id', $record->id)->update($values + ['updated_at' => $now]);
                }
            } else {
                DB::table('attendance_records')->insertOrIgnore($values + [
                    'user_id' => $user->id,
                    'attendance_date' => $day,
                    'is_imported' => 'No',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->refreshTotalHours($ids);

        return $counts;
    }

    /**
     * Rebuild a range of days (future days are ignored).
     *
     * @return int number of days processed
     */
    public function processRange($from, $to, ?array $userIds = null): int
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay()->min(today());
        if ($from->gt($to)) {
            return 0;
        }
        $calendar = new WorkCalendar($from, $to);
        $days = 0;
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $this->processDay($day, $userIds, $calendar);
            $days++;
        }

        return $days;
    }

    /**
     * The record a person should have for a day, or null if they should have none
     * (a day off with no clock-in, or before attendance tracking began).
     *
     * @param  Carbon[]|string[]  $times  the day's clock-in times
     */
    public function evaluate(User $user, Carbon $date, array $times, bool $onLeave, WorkCalendar $calendar): ?array
    {
        $config = SystemConfiguration::current();
        $working = $calendar->isWorkingDayFor($user, $date);
        $times = collect($times)->map(fn ($t) => Carbon::parse($t))->sort()->values();

        $base = [
            'day' => $date->format('l'),
            'is_working_day' => $working,
            'holiday_name' => $calendar->holidayName($date),
            'punch_count' => $times->count(),
        ];

        if ($times->isNotEmpty()) {
            $arrival = $times->first();
            $last = $times->last();
            $leftAt = $last->diffInSeconds($arrival) >= $config->repeat_capture_minutes * 60 ? $last : null;
            $lateMinutes = $working ? $this->minutesLate($arrival, $user->lateTime()) : 0;

            if ($leftAt) {
                $hours = round($leftAt->diffInSeconds($arrival) / 3600, 2);
                $halfDay = false;
            } elseif ($date->isToday()) {
                $hours = 0;      // still in progress
                $halfDay = false;
            } else {
                $hours = round($config->full_day_hours / 2, 2);
                $halfDay = true; // never seen leaving
            }

            return $base + [
                'status' => self::PRESENT,
                'check_in_time' => $arrival->format('H:i:s'),
                'check_out_time' => $leftAt ? $leftAt->format('H:i:s') : null,
                'is_late' => $lateMinutes > 0 ? 'Yes' : 'No',
                'late_minutes' => $lateMinutes,
                'is_half_day' => $halfDay,
                'hours' => $hours,
                'source' => 'device',
            ];
        }

        if (!$working) {
            return null;
        }
        if ($config->start_date && $date->lt(Carbon::parse($config->start_date)->startOfDay())) {
            return null;
        }

        return $base + [
            'status' => $onLeave ? self::ON_LEAVE : self::ABSENT,
            'check_in_time' => null,
            'check_out_time' => null,
            'is_late' => 'No',
            'late_minutes' => 0,
            'is_half_day' => false,
            'hours' => 0,
            'source' => 'system',
        ];
    }

    /** Whole minutes after the late time (08:30:59 against 08:30 is 0). */
    public function minutesLate(Carbon $arrival, string $lateTime): int
    {
        [$h, $m] = array_map('intval', explode(':', $lateTime));

        return max(0, ($arrival->hour * 60 + $arrival->minute) - ($h * 60 + $m));
    }

    /**
     * Whether a device event is a clock-in. The Hikvision bridge forwards
     * recognised people without codes (a terminal only knows who someone is
     * when they authenticate), so an event with an employee and no code counts;
     * with codes, only successful authentications do.
     */
    public static function isClockIn(EventLog $event): bool
    {
        if (empty($event->user_id) && empty($event->employee_no)) {
            return false;
        }
        if ($event->major === null || $event->major === '') {
            return true;
        }

        return (int) $event->major === 5 && in_array((int) $event->minor, self::CLOCK_IN_MINORS, true);
    }

    /** Applies isClockIn() as a query condition. */
    public static function whereClockIn(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('major')
                ->orWhere(function (Builder $r) {
                    $r->where('major', 5)->whereIn('minor', self::CLOCK_IN_MINORS);
                });
        });
    }

    /** A person is expected from the day they started, while their record is active. */
    public function isExpected(User $user, Carbon $date): bool
    {
        if ($user->status !== 'Active') {
            return false;
        }

        return !$user->start_working_date || Carbon::parse($user->start_working_date)->startOfDay()->lte($date);
    }

    private function expectedUsers(Carbon $date, ?array $userIds): Collection
    {
        $query = User::query()
            ->where('status', 'Active')
            ->where(function ($q) use ($date) {
                $q->whereNull('start_working_date')->orWhere('start_working_date', '<=', $date->toDateString());
            });
        if ($userIds !== null) {
            $query->whereIn('id', $userIds);
        }

        return $query->get(['id', 'status', 'start_working_date', 'work_days', 'custom_late_time']);
    }

    /**
     * @return array<int, string[]> user id => clock-in datetimes
     */
    private function clockInsOn(string $day, array $userIds): array
    {
        $query = DB::table('event_logs')
            ->whereIn('user_id', $userIds)
            ->whereBetween('event_time', ["{$day} 00:00:00", "{$day} 23:59:59"]);

        $byUser = [];
        foreach (self::whereClockIn($query)->orderBy('event_time')->get(['user_id', 'event_time']) as $row) {
            $byUser[$row->user_id][] = $row->event_time;
        }

        return $byUser;
    }

    /** Corrected by HR, or imported from a file: never rebuilt. */
    private function isProtected(object $record): bool
    {
        return (bool) $record->is_manual
            || $record->source === 'import'
            || $record->is_imported === 'Yes';
    }

    private function differs(object $record, array $values): bool
    {
        foreach ($values as $key => $value) {
            $current = $record->{$key} ?? null;
            if (is_bool($value)) {
                $current = (bool) $current;
            } elseif (is_float($value) || $key === 'hours') {
                $current = round((float) $current, 2);
                $value = round((float) $value, 2);
            } elseif (is_int($value)) {
                $current = (int) $current;
            }
            if ($current !== $value) {
                return true;
            }
        }

        return false;
    }

    /** users.hours keeps each person's total hours, as before. */
    private function refreshTotalHours(array $userIds): void
    {
        DB::table('users')->whereIn('id', $userIds)->update([
            'hours' => DB::raw('(SELECT COALESCE(ROUND(SUM(hours)), 0) FROM attendance_records WHERE attendance_records.user_id = users.id)'),
        ]);
    }
}
