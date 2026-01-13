<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * Receive events from Hikvision Bridge (Batch)
     * POST /api/webhook/events
     */
    public function receiveEvents(Request $request)
    {
        // Validate webhook token - support both X-API-Key and X-Webhook-Token
        $token = $request->header('X-API-Key') ?? $request->header('X-Webhook-Token') ?? $request->input('token');
        $expectedToken = env('HIKVISION_WEBHOOK_TOKEN', config('app.webhook_token'));
        
        if ($token !== $expectedToken) {
            Log::warning('Invalid webhook token attempt', [
                'ip' => $request->ip(),
                'provided_token' => $token,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid webhook token',
            ], 401);
        }

        // Validate request data - support both old and new field names
        $validator = Validator::make($request->all(), [
            'events' => 'required|array',
            'events.*.event_serial' => 'required|string',
            'events.*.employee_no' => 'required|string',
            'events.*.employee_name' => 'nullable|string',
            'events.*.event_time' => 'required|string',
            'events.*.device_name' => 'nullable|string',
            'events.*.device_ip' => 'nullable|string',
            'events.*.door_name' => 'nullable|string',
            'events.*.verification_method' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $events = $request->input('events', []);
        $processed = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($events as $eventData) {
            try {
                // Support both old and new field names
                $eventSerial = $eventData['event_serial'] ?? $eventData['serialNo'] ?? null;
                $employeeNo = $eventData['employee_no'] ?? $eventData['employeeNoString'] ?? null;
                $employeeName = $eventData['employee_name'] ?? $eventData['name'] ?? null;
                $eventTime = $eventData['event_time'] ?? $eventData['time'] ?? null;
                $doorName = $eventData['door_name'] ?? $eventData['doorName'] ?? null;
                $verificationMethod = $eventData['verification_method'] ?? $eventData['verificationMode'] ?? null;
                $deviceName = $eventData['device_name'] ?? null;
                $deviceIp = $eventData['device_ip'] ?? null;
                $majorEventType = $eventData['major_event_type'] ?? null;
                $minorEventType = $eventData['minor_event_type'] ?? null;
                $rawData = $eventData['raw_data'] ?? $eventData;
                
                // Check for duplicates using event_serial
                $existing = EventLog::where('event_serial', $eventSerial)->first();

                if ($existing) {
                    $duplicates++;
                    continue;
                }

                // Parse event time
                $eventTimeParsed = \Carbon\Carbon::parse($eventTime);

                // Create new event log
                EventLog::create([
                    'event_serial' => $eventSerial,
                    'employee_no' => $employeeNo,
                    'employee_name' => $employeeName,
                    'event_time' => $eventTimeParsed,
                    'door_name' => $doorName,
                    'major_event_type' => $majorEventType,
                    'minor_event_type' => $minorEventType,
                    'verification_method' => $verificationMethod,
                    'device_name' => $deviceName,
                    'device_ip' => $deviceIp,
                    'raw_data' => $rawData,
                    'process_status' => 'unprocessed',
                ]);

                $processed++;

            } catch (\Exception $e) {
                $errors[] = [
                    'event' => $eventData['event_serial'] ?? $eventData['serialNo'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to process event', [
                    'event' => $eventData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Events received successfully',
            'stats' => [
                'total' => count($events),
                'processed' => $processed,
                'duplicates' => $duplicates,
                'errors' => count($errors),
            ],
            'errors' => $errors,
        ], 200);
    }

    /**
     * Receive single event (backward compatibility)
     * POST /api/webhook/event
     */
    public function receiveEvent(Request $request)
    {
        // Convert single event to batch format
        $eventData = $request->all();
        
        // Remove token from event data if present
        unset($eventData['token']);
        
        $request->merge([
            'events' => [$eventData]
        ]);

        return $this->receiveEvents($request);
    }

    /**
     * Health check endpoint
     * GET /api/webhook/health
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'webhook',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
