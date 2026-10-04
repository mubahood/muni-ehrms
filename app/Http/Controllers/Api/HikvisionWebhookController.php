<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Support\WebhookAuth;
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
        return WebhookAuth::accepts($request);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Handle webhook test requests (no event data provided)
        if (!$request->has('event_serial_no') || !$request->has('device_serial')) {
            Log::info('Hikvision webhook test received', [
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Webhook endpoint is active',
                'test_mode' => true,
                'timestamp' => now()->toIso8601String(),
                'expected_format' => [
                    'event_serial_no' => 'string (required)',
                    'device_serial' => 'string (required)',
                    'event_time' => 'datetime (required)',
                    'employee_no' => 'string (optional)',
                    'major' => 'integer (optional)',
                    'minor' => 'integer (optional)',
                ],
            ], 200);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Handle webhook test requests (no events provided)
        if (!$request->has('events') || empty($request->input('events'))) {
            Log::info('Hikvision webhook test received', [
                'ip' => $request->ip(),
                'test_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Batch webhook endpoint is active',
                'test_mode' => true,
                'timestamp' => now()->toIso8601String(),
                'expected_format' => [
                    'events' => [
                        [
                            'event_serial_no' => 'string (required)',
                            'device_serial' => 'string (required)',
                            'event_time' => 'datetime (required)',
                            'employee_no' => 'string (optional)',
                            'major' => 'integer (optional)',
                            'minor' => 'integer (optional)',
                        ]
                    ],
                    'batch_id' => 'string (optional)',
                ],
            ], 200);
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
     * Link the event to a person by their employee number (Terminal ID).
     * Uses the same matching as every other path; see EventLog::linkUser().
     */
    protected function autoLinkUser(EventLog $eventLog): bool
    {
        if (!$eventLog->employee_no || $eventLog->user_id) {
            return false;
        }

        return (bool) $eventLog->linkUser();
    }

    /**
     * Turn the event into attendance. Delegates to the shared service so this
     * endpoint follows exactly the same rules as the bridge and the scheduler.
     */
    protected function processEventToAttendance(EventLog $eventLog): bool
    {
        return app(\App\Services\AttendanceProcessingService::class)->processEventToAttendance($eventLog);
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
