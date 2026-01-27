<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EventLog;
use App\Models\Leave;
use App\Models\SystemConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceProcessingService
{
    /**
     * Process an event log and update the corresponding attendance record
     *
     * @param EventLog $eventLog
     * @return bool
     */
    public function processEventToAttendance(EventLog $eventLog): bool
    {
        DB::beginTransaction();
        
        try {
            // Step 1: Validate user link
            if (!$eventLog->user_id) {
                if (!$eventLog->linkUser()) {
                    $eventLog->markAsFailed("No user found for employee_no: {$eventLog->employee_no}");
                    DB::commit();
                    return false;
                }
            }

            // Step 2: Validate event type (Access Granted events only)
            if (!$this->isAttendanceEvent($eventLog)) {
                $eventLog->markAsSkipped('Event type not applicable for attendance');
                DB::commit();
                return false;
            }

            // Step 3: Extract date and time
            $eventDate = Carbon::parse($eventLog->event_time)->toDateString();
            $eventTimeOnly = Carbon::parse($eventLog->event_time)->format('H:i:s');
            $user = $eventLog->user;

            // Step 4: Check user availability
            if (!$user->isAvailableOnDay($eventDate)) {
                $reason = $this->getUnavailabilityReason($user, $eventDate);
                $eventLog->markAsSkipped($reason);
                DB::commit();
                return false;
            }

            // Step 5: Get or create attendance record with row locking
            $attendance = AttendanceRecord::lockForUpdate()
                ->where('user_id', $eventLog->user_id)
                ->where('attendance_date', $eventDate)
                ->first();

            if (!$attendance) {
                $attendance = AttendanceRecord::create([
                    'user_id' => $eventLog->user_id,
                    'attendance_date' => $eventDate,
                    'status' => 'Absent',
                    'day' => Carbon::parse($eventDate)->format('l'),
                    'is_late' => 'No',
                    'is_imported' => 'No',
                    'source' => 'device',
                    'hours' => 0
                ]);
            }

            // Step 6: Determine clock-in or clock-out
            if (empty($attendance->check_in_time)) {
                // First scan of the day = clock-in
                $attendance->check_in_time = $eventTimeOnly;
                $attendance->is_late = $this->checkIfLate($user, $eventDate, $eventTimeOnly) ? 'Yes' : 'No';
                $attendance->source = 'device';
            } else {
                // Subsequent scan = clock-out (always update to latest)
                $attendance->check_out_time = $eventTimeOnly;
                $attendance->source = 'device';
            }

            // Step 7: Calculate status
            $attendance->status = $this->calculateStatus($user, $attendance, $eventDate);

            // Step 8: Calculate hours if present
            if ($attendance->status == 'Present' && $attendance->check_in_time && $attendance->check_out_time) {
                $attendance->hours = $this->calculateHours($attendance->check_in_time, $attendance->check_out_time);
            } elseif ($attendance->status == 'Half Day' && $attendance->check_in_time) {
                // For half day, calculate hours up to now or end of work day
                $checkOutTime = $attendance->check_out_time ?? Carbon::now()->format('H:i:s');
                $attendance->hours = $this->calculateHours($attendance->check_in_time, $checkOutTime);
            } else {
                $attendance->hours = 0;
            }

            $attendance->save();

            // Step 9: Link event to attendance
            $eventLog->attendance_record_id = $attendance->id;
            $eventLog->user_id = $user->id;
            $eventLog->markAsProcessed();

            DB::commit();

            Log::info("Event processed successfully", [
                'event_id' => $eventLog->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'attendance_id' => $attendance->id,
                'status' => $attendance->status,
                'check_in' => $attendance->check_in_time,
                'check_out' => $attendance->check_out_time
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            $eventLog->markAsFailed($e->getMessage());
            
            Log::error("Event processing failed", [
                'event_id' => $eventLog->id,
                'employee_no' => $eventLog->employee_no,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Check if the event is an attendance event (Access Granted)
     *
     * @param EventLog $eventLog
     * @return bool
     */
    protected function isAttendanceEvent(EventLog $eventLog): bool
    {
        // Major event type 5 = Access Control, Minor types 75/76/77 = Access Granted
        return $eventLog->major_event_type == 5 && 
               in_array($eventLog->minor_event_type, [75, 76, 77]);
    }

    /**
     * Get the reason why a user is unavailable on a specific date
     *
     * @param User $user
     * @param string $date
     * @return string
     */
    protected function getUnavailabilityReason(User $user, string $date): string
    {
        if ($user->status != 'Active') {
            return "User is not active (Status: {$user->status})";
        }

        $carbonDate = Carbon::parse($date);
        
        if ($user->start_working_date && $carbonDate->lt(Carbon::parse($user->start_working_date))) {
            return "Date is before user's start working date";
        }

        $dayOfWeek = $carbonDate->format('l');
        $workDays = is_array($user->work_days) ? $user->work_days : json_decode($user->work_days, true);
        
        if (!in_array($dayOfWeek, $workDays ?? [])) {
            return "Not a work day for this user (Day: {$dayOfWeek})";
        }

        $leave = Leave::where('user_id', $user->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if ($leave) {
            return "User has active leave ({$leave->leave_type})";
        }

        return "User is unavailable for unknown reason";
    }

    /**
     * Check if the clock-in time is late
     *
     * @param User $user
     * @param string $date
     * @param string $checkInTime
     * @return bool
     */
    protected function checkIfLate(User $user, string $date, string $checkInTime): bool
    {
        // Use user-specific late time if set, otherwise use system configuration
        $lateThreshold = $user->custom_late_time;
        
        if (!$lateThreshold) {
            $systemConfig = SystemConfiguration::first();
            $lateThreshold = $systemConfig ? $systemConfig->late_time : '08:30:00';
        }

        return $checkInTime > $lateThreshold;
    }

    /**
     * Calculate the attendance status based on check-in/check-out times and leave status
     *
     * @param User $user
     * @param AttendanceRecord $attendance
     * @param string $date
     * @return string
     */
    protected function calculateStatus(User $user, AttendanceRecord $attendance, string $date): string
    {
        // Check if user has active leave for this date
        $hasLeave = Leave::where('user_id', $user->id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();

        if ($hasLeave) {
            return 'On Leave';
        }

        // Check attendance based on clock-in and clock-out times
        if ($attendance->check_in_time && $attendance->check_out_time) {
            return 'Present';
        }

        if ($attendance->check_in_time && !$attendance->check_out_time) {
            // If it's still today and before 5 PM, status is pending (keep as current)
            // If it's past 5 PM or a past date, mark as Half Day
            $now = Carbon::now();
            $attendanceDate = Carbon::parse($date);
            
            if ($attendanceDate->isToday() && $now->hour < 17) {
                // Still working, status pending
                return $attendance->status == 'Present' ? 'Present' : 'Half Day';
            } else {
                // Day is over or past date without clock-out
                return 'Half Day';
            }
        }

        // No check-in and no check-out
        return 'Absent';
    }

    /**
     * Calculate hours worked between check-in and check-out times
     * Deducts break time if applicable
     *
     * @param string $checkInTime
     * @param string $checkOutTime
     * @return float
     */
    protected function calculateHours(string $checkInTime, string $checkOutTime): float
    {
        $checkIn = Carbon::parse($checkInTime);
        $checkOut = Carbon::parse($checkOutTime);

        // Calculate total minutes worked
        $totalMinutes = $checkOut->diffInMinutes($checkIn);

        // Convert to hours with 2 decimal places
        $hours = $totalMinutes / 60;

        // Deduct break time if worked more than 4 hours (assumes 1-hour lunch break)
        if ($hours > 4) {
            $hours -= 1; // Deduct 1 hour for lunch break
        }

        return round($hours, 2);
    }

    /**
     * Process multiple unprocessed events in batch
     *
     * @param int $limit
     * @return array
     */
    public function processBatchUnprocessedEvents(int $limit = 100): array
    {
        $events = EventLog::unprocessed()
            ->orderBy('event_time', 'asc')
            ->limit($limit)
            ->get();

        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total' => $events->count()
        ];

        foreach ($events as $event) {
            $success = $this->processEventToAttendance($event);
            
            // Refresh to get updated status
            $event->refresh();
            
            if ($event->process_status == EventLog::STATUS_PROCESSED) {
                $results['processed']++;
            } elseif ($event->process_status == EventLog::STATUS_FAILED) {
                $results['failed']++;
            } elseif ($event->process_status == EventLog::STATUS_SKIPPED) {
                $results['skipped']++;
            }
        }

        return $results;
    }
}
