<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EventLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Turns device events into attendance.
 *
 * An event only has to be linked to a person and recognised as a clock-in;
 * the day itself is then rebuilt by the AttendanceEngine from all of that
 * person's clock-ins, so the order events arrive in, duplicates and late
 * deliveries from the bridge make no difference to the result.
 */
class AttendanceProcessingService
{
    private AttendanceEngine $engine;

    public function __construct(AttendanceEngine $engine)
    {
        $this->engine = $engine;
    }

    public function processEventToAttendance(EventLog $eventLog): bool
    {
        try {
            if (!$eventLog->user_id && !$eventLog->linkUser()) {
                $eventLog->markAsFailed("No employee has Terminal ID {$eventLog->employee_no}" . ($eventLog->employee_name ? " (the terminal knows this person as {$eventLog->employee_name})" : '') . '. Set the Terminal ID on the employee record.');

                return false;
            }

            if (!AttendanceEngine::isClockIn($eventLog)) {
                $eventLog->markAsSkipped('Not a clock-in: the terminal did not authenticate this person');

                return false;
            }

            $user = $eventLog->user()->first();
            $date = Carbon::parse($eventLog->event_time)->startOfDay();

            if (!$this->engine->isExpected($user, $date)) {
                $eventLog->markAsSkipped($user->status !== 'Active'
                    ? "User is not active (Status: {$user->status})"
                    : "Date is before user's start working date");

                return false;
            }

            $this->engine->processDay($date, [$user->id]);

            $record = AttendanceRecord::where('user_id', $user->id)
                ->where('attendance_date', $date->toDateString())
                ->first();

            $eventLog->attendance_record_id = optional($record)->id;
            $eventLog->markAsProcessed();

            return true;
        } catch (\Throwable $e) {
            $eventLog->markAsFailed($e->getMessage());
            Log::error('Event processing failed', [
                'event_id' => $eventLog->id,
                'employee_no' => $eventLog->employee_no,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process events that have not been processed yet, oldest first.
     *
     * @return array{processed:int, failed:int, skipped:int, total:int}
     */
    public function processBatchUnprocessedEvents(int $limit = 100): array
    {
        $events = EventLog::unprocessed()->orderBy('event_time')->limit($limit)->get();
        $results = ['processed' => 0, 'failed' => 0, 'skipped' => 0, 'total' => $events->count()];

        foreach ($events as $event) {
            $this->processEventToAttendance($event);
            $event->refresh();
            $key = [
                EventLog::STATUS_PROCESSED => 'processed',
                EventLog::STATUS_FAILED => 'failed',
                EventLog::STATUS_SKIPPED => 'skipped',
            ][$event->process_status] ?? null;
            if ($key) {
                $results[$key]++;
            }
        }

        return $results;
    }
}
