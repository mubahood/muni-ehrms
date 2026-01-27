<?php

namespace App\Observers;

use App\Models\EventLog;
use App\Services\AttendanceProcessingService;
use Illuminate\Support\Facades\Log;

class EventLogObserver
{
    /**
     * @var AttendanceProcessingService
     */
    protected $attendanceService;

    public function __construct(AttendanceProcessingService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Handle the EventLog "created" event.
     *
     * @param  EventLog  $eventLog
     * @return void
     */
    public function created(EventLog $eventLog)
    {
        // Auto-process new event logs
        try {
            $this->attendanceService->processEventToAttendance($eventLog);
        } catch (\Exception $e) {
            Log::error("EventLog Observer: Failed to process event on creation", [
                'event_id' => $eventLog->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the EventLog "updated" event.
     * Only process if status changed from failed/skipped to unprocessed (manual retry)
     *
     * @param  EventLog  $eventLog
     * @return void
     */
    public function updated(EventLog $eventLog)
    {
        // Check if process_status was changed to unprocessed (manual retry scenario)
        if ($eventLog->isDirty('process_status') && 
            $eventLog->process_status == EventLog::STATUS_UNPROCESSED &&
            in_array($eventLog->getOriginal('process_status'), [
                EventLog::STATUS_FAILED, 
                EventLog::STATUS_SKIPPED
            ])) {
            
            try {
                Log::info("EventLog Observer: Reprocessing event after status change", [
                    'event_id' => $eventLog->id,
                    'old_status' => $eventLog->getOriginal('process_status'),
                    'new_status' => $eventLog->process_status
                ]);
                
                $this->attendanceService->processEventToAttendance($eventLog);
            } catch (\Exception $e) {
                Log::error("EventLog Observer: Failed to reprocess event on update", [
                    'event_id' => $eventLog->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
