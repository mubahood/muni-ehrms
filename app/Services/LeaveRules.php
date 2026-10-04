<?php

namespace App\Services;

use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\Carbon;

/**
 * Leave rules: counting days, leave years, annual leave balances, and the
 * checks every new request must pass (online application or HR entry).
 *
 * Days are counted on the University's calendar — weekends and public holidays
 * excluded — exactly as on the leave form.
 */
class LeaveRules
{
    public static function workingDays($start, $end): int
    {
        if (!$start || !$end) {
            return 0;
        }

        return (new WorkCalendar($start, $end))->countWorkingDays($start, $end);
    }

    /** First working day after the leave ends — the "date of return" on the form. */
    public static function returnDateAfter($end): Carbon
    {
        $day = Carbon::parse($end)->addDay()->startOfDay();
        $calendar = new WorkCalendar($day, $day->copy()->addDays(40));
        for ($i = 0; $i < 40; $i++) {
            if ($calendar->isWorkingDay($day)) {
                return $day;
            }
            $day->addDay();
        }

        return Carbon::parse($end)->addDay();
    }

    public static function leaveYearOf($date): int
    {
        return SystemConfiguration::current()->leaveYearOf($date);
    }

    public static function currentLeaveYear(): int
    {
        return self::leaveYearOf(today());
    }

    public static function yearLabel(int $year): string
    {
        return SystemConfiguration::current()->leaveYearLabel($year);
    }

    /**
     * Annual leave for one leave year. $exclude leaves one request out of the
     * totals — used when re-checking a request that is itself pending.
     */
    public static function annualBalance(User $user, ?int $year = null, ?Leave $exclude = null): LeaveBalance
    {
        $year = $year ?? self::currentLeaveYear();
        $entitlement = LeaveEntitlement::where('user_id', $user->id)->where('leave_year', $year)->first();

        $query = Leave::where('user_id', $user->id)->where('leave_type', Leave::ANNUAL)->where('leave_year', $year);
        if ($exclude && $exclude->exists) {
            $query->where('id', '!=', $exclude->id);
        }
        $leaves = $query->get(['id', 'status', 'days', 'days_restored']);

        $taken = $leaves->whereIn('status', [Leave::APPROVED, Leave::RECALLED])->sum(fn ($l) => $l->daysTaken());
        $pending = $leaves->where('status', Leave::PENDING)->sum('days');

        return new LeaveBalance(
            $year,
            $entitlement !== null,
            $entitlement ? $entitlement->days_due : 0,
            $entitlement ? $entitlement->carried_forward : 0,
            (int) $taken,
            (int) $pending
        );
    }

    /** The most recent leave actually taken before $before (for Section I of the form). */
    public static function lastLeaveTaken(User $user, $before = null): ?Leave
    {
        $query = Leave::where('user_id', $user->id)->whereIn('status', [Leave::APPROVED, Leave::RECALLED]);
        if ($before) {
            $query->where('start_date', '<', Carbon::parse($before)->toDateString());
        }

        return $query->orderByDesc('start_date')->first();
    }

    /**
     * Check a request. Returns [working days, leave year, list of problems].
     * An empty list of problems means the request may go ahead.
     *
     * @return array{0:int, 1:?int, 2:string[]}
     */
    public static function check(
        User $user,
        string $type,
        $start,
        $end,
        ?Leave $exclude = null,
        bool $allowPast = false,
        bool $byHr = false
    ): array {
        if (!$start || !$end) {
            return [0, null, ['Choose the first and last day of leave.']];
        }
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();
        if ($end->lt($start)) {
            return [0, null, ['The last day cannot be before the first day.']];
        }

        $errors = [];
        if (!array_key_exists($type, Leave::TYPES) || (!$byHr && !in_array($type, Leave::FORM_TYPES, true))) {
            $errors[] = 'Choose one of the types of leave on the form.';
        }
        if (!$allowPast && $start->lt(today())) {
            $errors[] = 'Leave cannot start in the past. Ask Human Resource to record leave that has already been taken.';
        }
        if ($start->diffInDays($end) > 400) {
            $errors[] = 'A single request cannot be longer than a year.';
        }
        $days = self::workingDays($start, $end);
        if ($days === 0) {
            $errors[] = 'These dates contain no working days (weekends and public holidays are not counted).';
        }

        $config = SystemConfiguration::current();
        $year = $config->leaveYearOf($start);

        $clash = self::overlapping($user, $start, $end, $exclude);
        if ($clash) {
            $errors[] = sprintf(
                'These dates overlap your %s from %s to %s (%s).',
                strtolower($clash->typeLabel()),
                $clash->start_date->format('d M'),
                $clash->end_date->format('d M Y'),
                strtolower($clash->statusLabel())
            );
        }

        if ($type === Leave::ANNUAL && $days > 0) {
            if ($config->leaveYearOf($end) !== $year) {
                [, $yearEnd] = $config->leaveYearBounds($year);
                $errors[] = sprintf(
                    'Annual leave must fall within one leave year (%s ends on %s). Split it into two requests.',
                    $config->leaveYearLabel($year),
                    $yearEnd->format('d M Y')
                );
            }
            $balance = self::annualBalance($user, $year, $exclude);
            if (!$balance->hasAllocation) {
                $errors[] = $byHr
                    ? "No annual leave allocation for {$balance->label()}. Set it under Leave planning first."
                    : "No annual leave has been allocated to you for {$balance->label()} yet. Please contact Human Resource.";
            } elseif ($balance->available() <= 0) {
                $errors[] = "Annual leave for {$balance->label()} is used up ({$balance->total()} days allocated, "
                    . "{$balance->taken} taken, {$balance->pending} awaiting approval).";
            } elseif ($days > $balance->available()) {
                $errors[] = "{$days} working days were requested but only {$balance->available()} annual leave days "
                    . "remain for {$balance->label()}.";
            }
        }

        return [$days, $year, $errors];
    }

    /**
     * Another request of this person that blocks these dates: awaiting
     * approval, approved, or recalled (only up to the day before they resumed).
     */
    public static function overlapping(User $user, Carbon $start, Carbon $end, ?Leave $exclude = null): ?Leave
    {
        $query = Leave::where('user_id', $user->id)
            ->where('start_date', '<=', $end->toDateString())
            ->where(function ($q) use ($start) {
                $q->where(function ($r) use ($start) {
                    $r->whereIn('status', [Leave::PENDING, Leave::APPROVED])
                        ->where('end_date', '>=', $start->toDateString());
                })->orWhere(function ($r) use ($start) {
                    $r->where('status', Leave::RECALLED)->where('recall_date', '>', $start->toDateString());
                });
            });
        if ($exclude && $exclude->exists) {
            $query->where('id', '!=', $exclude->id);
        }

        return $query->orderBy('start_date')->first();
    }
}
