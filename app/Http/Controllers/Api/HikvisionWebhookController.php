<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Models\User;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HikvisionWebhookController extends Controller
{
    /**
     * Validate webhook token
     */
    protected function validateToken(Request $request): bool
    {
        $token = $request->header('X-Webhook-Token') ?? $request->bearerToken();
        $expectedToken = config('services.hikvision.webhook_token') ?? env('HIKVISION_WEBHOOK_TOKEN');
        
        if (empty($expectedToken)) {
            Log::warning('Hikvision webhook: HIKVISION_WEBHOOK_TOKEN not configured');
            return false;
        }
        
        return hash_equals($expectedToken, $token ?? '');
    }

    /**
     * Receive single event from Hikvision bridge
     * 
     * POST /api/hikvision/events
     */
    public function receiveEvent(Request $request)
    {
        // Validate token
        if (!$this->validateToken($request)) {
            Log::warning('Hikvision webhook: Invalid token', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Validate event data
        $validator = Validator::make($request->all(), [
            'event_serial_no' => 'required|string',
            'device_serial' => 'required|string',
            'event_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check for duplicates
            if (EventLog::isDuplicate($request->device_serial, $request->event_serial_no)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Event already exists (duplicate)',
                    'duplicate' => true,
                ], 200);
            }

            // Create event log
            $eventLog = EventLog::createFromHikvisionEvent($request->all());

            // Try to auto-link user based on employee_no
            $this->autoLinkUser($eventLog);

            // Optionally auto-process to attendance
            if (config('services.hikvision.auto_process', false)) {
                $this->processEventToAttendance($eventLog);
            }

            Log::info('Hikvision event received', [
                'event_id' => $eventLog->id,
                'employee_no' => $eventLog->employee_no,
                'event_time' => $eventLog->event_time,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event received successfully',
                'event_id' => $eventLog->id,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Hikvision webhook error', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Receive batch events from Hikvision bridge
     * 
     * POST /api/hikvision/events/batch
     */
    public function receiveBatch(Request $request)
    {
        // Validate token
        if (!$this->validateToken($request)) {
            Log::warning('Hikvision webhook: Invalid token for batch', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Validate batch data
        $validator = Validator::make($request->all(), [
            'events' => 'required|array|min:1',
            'events.*.event_serial_no' => 'required|string',
            'events.*.device_serial' => 'required|string',
            'events.*.event_time' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $events = $request->input('events', []);
        $batchId = $request->input('batch_id', Str::uuid()->toString());
        
        $results = [
            'total' => count($events),
            'created' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        foreach ($events as $index => $eventData) {
            try {
                // Check for duplicates
                $deviceSerial = $eventData['device_serial'] ?? '';
                $eventSerialNo = $eventData['event_serial_no'] ?? '';

                if (EventLog::isDuplicate($deviceSerial, $eventSerialNo)) {
                    $results['duplicates']++;
                    continue;
                }

                // Add batch ID
                $eventData['batch_id'] = $batchId;

                // Create event log
                $eventLog = EventLog::createFromHikvisionEvent($eventData);

                // Auto-link user
                $this->autoLinkUser($eventLog);

                // Auto-process if enabled
                if (config('services.hikvision.auto_process', false)) {
                    $this->processEventToAttendance($eventLog);
                }

                $results['created']++;

            } catch (\Exception $e) {
                $results['errors']++;
                if (config('app.debug')) {
                    $results['error_details'][] = [
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ];
                }
                Log::error('Hikvision batch event error', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'data' => $eventData,
                ]);
            }
        }

        Log::info('Hikvision batch received', [
            'batch_id' => $batchId,
            'results' => $results,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch processed',
            'batch_id' => $batchId,
            'results' => $results,
        ], 200);
    }

    /**
     * Auto-link event to user based on employee_no
     */
    protected function autoLinkUser(EventLog $eventLog): bool
    {
        if (!$eventLog->employee_no || $eventLog->user_id) {
            return false;
        }

        // Try to find user by employee_no (check multiple possible columns)
        $user = User::where('employee_no', $eventLog->employee_no)
            ->orWhere('staff_id', $eventLog->employee_no)
            ->orWhere('badge_number', $eventLog->employee_no)
            ->first();

        if ($user) {
            $eventLog->linkUser($user);
            return true;
        }

        return false;
    }

    /**
     * Process event to attendance record
     * This is optional and can be customized based on business logic
     */
    protected function processEventToAttendance(EventLog $eventLog): bool
    {
        try {
            // Skip if no user linked
            if (!$eventLog->user_id) {
                $eventLog->markAsFailed('No user linked to this employee number');
                return false;
            }

            // Skip if event type is not access granted (major=5, minor=75)
            // Customize this based on your device configuration
            if ($eventLog->major != 5 || !in_array($eventLog->minor, [75, 76, 77])) {
                $eventLog->markAsSkipped('Event type not applicable for attendance');
                return false;
            }

            $eventDate = Carbon::parse($eventLog->event_time)->toDateString();
            $eventTime = Carbon::parse($eventLog->event_time)->format('H:i:s');

            // Check if attendance record exists for this date
            $attendance = AttendanceRecord::where('user_id', $eventLog->user_id)
                ->whereDate('date', $eventDate)
                ->first();

            if ($attendance) {
                // Update clock out time if later than existing
                if (!$attendance->clock_out_time || $eventTime > $attendance->clock_out_time) {
                    $attendance->update([
                        'clock_out_time' => $eventTime,
                        'source' => 'hikvision',
                    ]);
                }
            } else {
                // Create new attendance record
                $attendance = AttendanceRecord::create([
                    'user_id' => $eventLog->user_id,
                    'date' => $eventDate,
                    'clock_in_time' => $eventTime,
                    'source' => 'hikvision',
                ]);
            }

            // Link attendance record to event
            $eventLog->update(['attendance_record_id' => $attendance->id]);
            $eventLog->markAsProcessed();

            return true;

        } catch (\Exception $e) {
            $eventLog->markAsFailed($e->getMessage());
            Log::error('Failed to process event to attendance', [
                'event_id' => $eventLog->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get status of event logs
     * 
     * GET /api/hikvision/status
     */
    public function status(Request $request)
    {
        if (!$this->validateToken($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $today = today();
        
        return response()->json([
            'success' => true,
            'status' => [
                'total_events' => EventLog::count(),
                'today_events' => EventLog::whereDate('event_time', $today)->count(),
                'unprocessed' => EventLog::unprocessed()->count(),
                'processed' => EventLog::processed()->count(),
                'failed' => EventLog::failed()->count(),
                'last_event_time' => EventLog::max('event_time'),
                'last_received' => EventLog::max('created_at'),
            ],
        ]);
    }

    /**
     * Test endpoint - no auth required
     * 
     * GET /api/hikvision/ping
     */
    public function ping()
    {
        return response()->json([
            'success' => true,
            'message' => 'Hikvision webhook endpoint is active',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
