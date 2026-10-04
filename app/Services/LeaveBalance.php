<?php

namespace App\Services;

/**
 * Annual leave for one person and one leave year — Section II of the form.
 */
class LeaveBalance
{
    public int $year;
    public bool $hasAllocation;
    public int $daysDue;
    public int $carriedForward;
    public int $taken;
    public int $pending;

    public function __construct(int $year, bool $hasAllocation, int $daysDue, int $carriedForward, int $taken, int $pending)
    {
        $this->year = $year;
        $this->hasAllocation = $hasAllocation;
        $this->daysDue = $daysDue;
        $this->carriedForward = $carriedForward;
        $this->taken = $taken;
        $this->pending = $pending;
    }

    /** (a) + (b): the most that may be taken this year. */
    public function total(): int
    {
        return $this->daysDue + $this->carriedForward;
    }

    /** (d) on the form: due + carried forward − taken. */
    public function balance(): int
    {
        return $this->total() - $this->taken;
    }

    /** What can still be applied for: requests awaiting approval are reserved. */
    public function available(): int
    {
        return $this->balance() - $this->pending;
    }

    public function usedPercent(): int
    {
        return $this->total() > 0 ? (int) min(100, round(100 * ($this->taken + $this->pending) / $this->total())) : 0;
    }

    public function label(): string
    {
        return LeaveRules::yearLabel($this->year);
    }
}
