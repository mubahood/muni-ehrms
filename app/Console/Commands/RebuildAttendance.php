<?php

namespace App\Console\Commands;

use App\Services\AttendanceEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rebuild attendance for a range of days from the clock-ins — after a terminal
 * was offline and caught up, a holiday was added late, or the rules changed.
 * Records corrected by HR and imported records are left as they are.
 */
class RebuildAttendance extends Command
{
    protected $signature = 'attendance:rebuild
        {--from= : First day (default: first of this month)}
        {--to= : Last day (default: today)}
        {--user=* : Only these user ids}';

    protected $description = 'Rebuild attendance records for a range of days from the clock-ins';

    public function handle(AttendanceEngine $engine)
    {
        $from = Carbon::parse($this->option('from') ?: now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($this->option('to') ?: today())->startOfDay();
        $users = array_map('intval', (array) $this->option('user')) ?: null;

        if ($from->gt($to)) {
            $this->error('--from is after --to.');

            return 1;
        }

        $days = $engine->processRange($from, $to, $users);
        $this->info("Rebuilt {$days} day(s), {$from->format('d M Y')} – {$to->min(today())->format('d M Y')}"
            . ($users ? ' for ' . count($users) . ' person(s)' : '') . '.');
        Log::info('Attendance rebuilt', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'users' => $users]);

        return 0;
    }
}
