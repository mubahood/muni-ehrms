<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Support\WebhookAuth;
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
        if (!WebhookAuth::accepts($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid webhook token',
            ], 401);
        }

        // Log incoming request for debugging
        $rawContent = $request->getContent();
        $allData = $request->all();
        
        // Get events - handle both direct array and wrapped format
        $events = $request->input('events', []);
        
        // If events is empty, try to parse raw JSON and handle flexible formats
        if (empty($events)) {
            $decoded = json_decode($rawContent, true);
            
            if (is_array($decoded)) {
                if (isset($decoded['events']) && is_array($decoded['events'])) {
                    // Standard format: {"events": [...]}
                    $events = $decoded['events'];
                } elseif (isset($decoded['event_serial']) || isset($decoded['serialNo'])) {
                    // Single event sent as object: {"event_serial": "...", ...}
                    $events = [$decoded];
                } elseif (!empty($decoded) && is_array(reset($decoded))) {
                    // Array of events sent directly: [{...}, {...}]
                    $events = $decoded;
                } else {
                    // Unknown format, try as single event
                    $events = [$decoded];
                }
            }
        } elseif (!is_array($events)) {
            // Single event object received, wrap it
            $events = [$events];
        }

        // Final validation - very relaxed
        if (empty($events) || !is_array($events)) {
            Log::error('No valid events data', [
                'received_data' => $allData,
                'raw_content' => substr($rawContent, 0, 500),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'No events data received or invalid format',
                'debug' => [
                    'received_keys' => array_keys($allData),
                    'events_type' => gettype($events),
                ],
            ], 422);
        }
        $processed = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($events as $eventData) {
            try {
                // Harmonize and normalize data - support multiple field name formats
                $eventSerial = $eventData['event_serial'] 
                    ?? $eventData['serialNo'] 
                    ?? $eventData['serial_no'] 
                    ?? 'EVT-' . time() . '-' . rand(1000, 9999);
                
                $employeeNo = $eventData['employee_no'] 
                    ?? $eventData['employeeNoString'] 
                    ?? $eventData['employeeNo'] 
                    ?? null;
                
                $employeeName = $eventData['employee_name'] 
                    ?? $eventData['name'] 
                    ?? null;
                
                $eventTime = $eventData['event_time'] 
                    ?? $eventData['time'] 
                    ?? $eventData['occur_time']
                    ?? now()->toDateTimeString();
                
                $doorName = $eventData['door_name'] 
                    ?? $eventData['doorName'] 
                    ?? $eventData['door_no'] 
                    ?? $eventData['doorNo']
                    ?? null;
                
                $verificationMethod = $eventData['verification_method'] 
                    ?? $eventData['verificationMode'] 
                    ?? $eventData['verify_mode'] 
                    ?? $eventData['currentVerifyMode']
                    ?? null;
                
                $deviceName = $eventData['device_name'] 
                    ?? $eventData['device_id'] 
                    ?? null;
                
                $deviceIp = $eventData['device_ip'] ?? null;
                
                $major = $eventData['major_event_type'] 
                    ?? $eventData['major'] 
                    ?? null;
                
                $minor = $eventData['minor_event_type'] 
                    ?? $eventData['minor'] 
                    ?? $eventData['sub_event_type'] 
                    ?? null;
                
                // Get event_type
                $eventType = $eventData['event_type'] ?? null;
                
                // Parse raw_json if it's a JSON string (from bridge)
                $rawJson = null;
                if (isset($eventData['raw_json']) && is_string($eventData['raw_json'])) {
                    $rawJson = json_decode($eventData['raw_json'], true);
                    // If raw_json has useful data, merge it into eventData for fallback
                    if ($rawJson && is_array($rawJson)) {
                        // Use raw_json data as fallback if main fields are missing
                        if (!$major && isset($rawJson['major'])) $major = $rawJson['major'];
                        if (!$minor && isset($rawJson['minor'])) $minor = $rawJson['minor'];
                        if (!$verificationMethod && isset($rawJson['currentVerifyMode'])) $verificationMethod = $rawJson['currentVerifyMode'];
                        if (!$employeeNo && isset($rawJson['employeeNoString'])) $employeeNo = $rawJson['employeeNoString'];
                        if (!$employeeName && isset($rawJson['name'])) $employeeName = $rawJson['name'];
                        if (!$eventTime && isset($rawJson['time'])) $eventTime = $rawJson['time'];
                        if (!$doorName && isset($rawJson['doorNo'])) $doorName = $rawJson['doorNo'];
                    }
                }
                
                // Support additional fields from raw_json
                $cardType = $eventData['card_type'] 
                    ?? $eventData['cardType'] 
                    ?? ($rawJson['cardType'] ?? null);
                
                $mask = $eventData['mask'] ?? ($rawJson['mask'] ?? null);
                $helmet = $eventData['helmet'] ?? null;
                $cardNo = $eventData['card_no'] ?? ($eventData['cardNo'] ?? null);
                $cardReaderNo = $eventData['card_reader_no'] ?? ($eventData['cardReaderNo'] ?? ($rawJson['cardReaderNo'] ?? null));
                $userType = $eventData['user_type'] ?? ($eventData['userType'] ?? ($rawJson['userType'] ?? null));
                $pictureUrl = $eventData['picture_url'] ?? ($eventData['pictureURL'] ?? ($rawJson['pictureURL'] ?? null));
                
                // Get raw data - merge all fields including parsed raw_json
                $rawData = $eventData['raw_data'] ?? $eventData;
                
                // If we have parsed raw_json, merge it into raw_data for complete record
                if ($rawJson && is_array($rawJson)) {
                    $rawData['hikvision_raw'] = $rawJson;
                }
                
                // Add additional metadata to raw_data if present
                if (!isset($rawData['card_type']) && $cardType) $rawData['card_type'] = $cardType;
                if (!isset($rawData['mask']) && $mask) $rawData['mask'] = $mask;
                if (!isset($rawData['helmet']) && $helmet) $rawData['helmet'] = $helmet;
                if (!isset($rawData['card_no']) && $cardNo) $rawData['card_no'] = $cardNo;
                if (!isset($rawData['user_type']) && $userType) $rawData['user_type'] = $userType;
                if (!isset($rawData['picture_url']) && $pictureUrl) $rawData['picture_url'] = $pictureUrl;
                if (!isset($rawData['event_type']) && $eventType) $rawData['event_type'] = $eventType;
                
                // Skip if missing critical data
                if (empty($eventSerial)) {
                    $errors[] = [
                        'event' => 'unknown',
                        'error' => 'Missing event serial',
                    ];
                    continue;
                }
                
                // Check for duplicates using event_serial
                $existing = EventLog::where('event_serial', $eventSerial)->first();

                if ($existing) {
                    $duplicates++;
                    continue;
                }

                // Parse event time
                $eventTimeParsed = EventLog::deviceTimeToLocal($eventTime);
                
                // Get additional fields
                $temperature = $eventData['temperature'] 
                    ?? $eventData['currTemperature'] 
                    ?? null;
                
                $maskDetected = isset($eventData['mask']) 
                    ? ($eventData['mask'] === 'yes' || $eventData['mask'] === true || $eventData['mask'] == 1) 
                    : null;
                
                $channelNo = $eventData['channel_no'] 
                    ?? $eventData['channelNo'] 
                    ?? $eventData['channel_id'] 
                    ?? null;

                // Create new event log with comprehensive data
                EventLog::create([
                    'event_serial' => $eventSerial,
                    'employee_no' => $employeeNo,
                    'employee_name' => $employeeName,
                    'event_time' => $eventTimeParsed,
                    'event_time_raw' => is_string($eventTime) ? $eventTime : null,
                    'door_no' => $doorName,
                    'major' => $major,
                    'minor' => $minor,
                    'event_type' => $eventType,
                    'verify_mode' => $verificationMethod,
                    'device_name' => $deviceName,
                    'device_ip' => $deviceIp,
                    'card_no' => $cardNo,
                    'card_type' => $cardType,
                    'picture_url' => $pictureUrl,
                    'temperature' => $temperature,
                    'mask_detected' => $maskDetected,
                    'channel_no' => $channelNo,
                    'raw_data' => $rawData,
                    'process_status' => 'unprocessed',
                ]);

                $processed++;

            } catch (\Exception $e) {
                $errors[] = [
                    'event' => $eventData['event_serial'] ?? $eventData['serialNo'] ?? $eventData['serial_no'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
                
                Log::error('Failed to process event', [
                    'event' => $eventData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Log success summary
        Log::info('Webhook processing complete', [
            'total' => count($events),
            'processed' => $processed,
            'duplicates' => $duplicates,
            'errors' => count($errors),
        ]);

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
