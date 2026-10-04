<?php

namespace App\Services;

use App\Models\PublicHoliday;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Answers "is this a working day?" for a date range with one query for the
 * public holidays in it.
 *
 * Two questions, deliberately kept apart:
 *  - isWorkingDay(): the institution's calendar — configured working weekdays,
 *    minus public holidays. Leave is counted in these days, as on the form.
 *  - isWorkingDayFor(): whether one person is expected in — their own work days
 *    if their staff record has them, minus public holidays. Attendance uses this.
 */
class WorkCalendar
{
    /** @var array<string, string> Y-m-d => holiday name */
    private array $holidays;

    /** @var int[] ISO weekdays, Monday = 1 */
    private array $weekdays;

    public function __construct($start = null, $end = null)
    {
        $query = PublicHoliday::query();
        if ($start) {
            $query->where('date', '>=', Carbon::parse($start)->toDateString());
        }
        if ($end) {
            $query->where('date', '<=', Carbon::parse($end)->toDateString());
        }
        $this->holidays = $query->pluck('name', 'date')
            ->mapWithKeys(fn ($name, $date) => [substr((string) $date, 0, 10) => $name])
            ->all();
        $this->weekdays = SystemConfiguration::current()->workingWeekdays();
    }

    public function holidayName($date): ?string
    {
        return $this->holidays[Carbon::parse($date)->toDateString()] ?? null;
    }

    public function isHoliday($date): bool
    {
        return $this->holidayName($date) !== null;
    }

    public function isWorkingDay($date): bool
    {
        $date = Carbon::parse($date);

        return in_array($date->isoWeekday(), $this->weekdays, true) && !$this->isHoliday($date);
    }

    public function isWorkingDayFor(User $user, $date): bool
    {
        $date = Carbon::parse($date);

        return in_array($date->isoWeekday(), $user->expectedWeekdays(), true) && !$this->isHoliday($date);
    }

    /**
     * @return string[] Y-m-d of the institution's working days from $start to $end inclusive
     */
    public function workingDays($start, $end): array
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($end->lt($start)) {
            return [];
        }
        $days = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if ($this->isWorkingDay($day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    public function countWorkingDays($start, $end): int
    {
        return count($this->workingDays($start, $end));
    }

    /**
     * @return string[] Y-m-d of the days $user is expected in, from $start to $end inclusive
     */
    public function workingDaysFor(User $user, $start, $end): array
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($end->lt($start)) {
            return [];
        }
        $days = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if ($this->isWorkingDayFor($user, $day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }
}
