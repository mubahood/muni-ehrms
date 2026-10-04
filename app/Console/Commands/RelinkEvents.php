<?php

namespace App\Console\Commands;

use App\Models\EventLog;
use App\Services\AttendanceEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Re-checks clock-ins matched through the old id/username fallback (people
 * with no Terminal ID): any whose terminal name is not the person's is
 * detached and marked failed, and the affected days are rebuilt.
 */
class RelinkEvents extends Command
{
    protected $signature = 'ehrms:relink-events {--dry-run : List the changes without making them}';

    protected $description = 'Detach clock-ins that were matched to the wrong person';

    public function handle(AttendanceEngine $engine): int
    {
        $wrong = EventLog::with('user')->whereNotNull('user_id')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', \App\Support\DemoManifest::EVENT_SOURCE))
            ->whereHas('user', fn ($q) => $q->where(fn ($w) => $w->whereNull('employee_no')->orWhere('employee_no', '')))
            ->get()
            ->filter(fn (EventLog $e) => $e->user && (string) $e->employee_no !== (string) $e->user->employee_no && !EventLog::sameName($e->employee_name, $e->user));

        if ($wrong->isEmpty()) {
            $this->info('Every matched clock-in belongs to the right person.');

            return 0;
        }
        $this->table(['Event', 'Terminal ID', 'Terminal name', 'Was matched to', 'Time'], $wrong->map(fn ($e) => [
            $e->id, $e->employee_no, $e->employee_name, $e->user->name, $e->getRawOriginal('event_time'),
        ])->all());
        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was changed.');

            return 0;
        }

        $days = [];
        foreach ($wrong as $e) {
            $days[$e->user_id][Carbon::parse($e->getRawOriginal('event_time'))->toDateString()] = true;
            $e->forceFill(['user_id' => null, 'attendance_record_id' => null, 'process_status' => 'failed',
                'process_error' => "Terminal name \"{$e->employee_name}\" is not {$e->user->name}; set the right employee's Terminal ID to {$e->employee_no}."])->save();
        }
        foreach ($days as $userId => $dates) {
            foreach (array_keys($dates) as $date) {
                $engine->processDay($date, [$userId]);
            }
        }
        $this->info($wrong->count() . ' clock-in(s) detached; ' . array_sum(array_map('count', $days)) . ' day(s) rebuilt.');

        return 0;
    }
}
