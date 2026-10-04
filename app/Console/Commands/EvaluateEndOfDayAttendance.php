<?php

namespace App\Console\Commands;

use App\Services\AttendanceEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Settles the day that has just ended: anyone not seen leaving becomes a half
 * day, and nobody is "not in yet" any more — they are absent.
 */
class EvaluateEndOfDayAttendance extends Command
{
    protected $signature = 'attendance:evaluate-eod {date? : Day to settle (default: yesterday)}';

    protected $description = 'Settle attendance for a day that has ended';

    public function handle(AttendanceEngine $engine)
    {
        $date = Carbon::parse($this->argument('date') ?: Carbon::yesterday())->startOfDay();
        $counts = $engine->processDay($date);

        $this->info('Attendance settled for ' . $date->format('l j F Y'));
        $this->table(['Status', 'People'], collect($counts)->map(fn ($n, $status) => [$status, $n])->values()->all());
        Log::info('End-of-day attendance settled', ['date' => $date->toDateString(), 'counts' => $counts]);

        return 0;
    }
}
