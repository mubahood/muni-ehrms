<?php

namespace App\Http\Controllers;

use App\Services\AttendanceProcessingService;
use App\Models\EventLog;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceProcessingService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Process unprocessed event logs
     */
    public function processEventLogs(Request $request)
    {
        $limit = $request->input('limit', 100);
        $format = $request->input('format', 'json'); // json or html

        // Get statistics before processing
        $beforeStats = [
            'unprocessed' => EventLog::unprocessed()->count(),
            'processed' => EventLog::processed()->count(),
            'failed' => EventLog::failed()->count(),
        ];

        // Process events
        $results = $this->attendanceService->processBatchUnprocessedEvents($limit);

        // Get statistics after processing
        $afterStats = [
            'unprocessed' => EventLog::unprocessed()->count(),
            'processed' => EventLog::processed()->count(),
            'failed' => EventLog::failed()->count(),
        ];

        $response = [
            'success' => true,
            'message' => 'Event logs processed successfully',
            'before' => $beforeStats,
            'after' => $afterStats,
            'results' => $results
        ];

        if ($format === 'html') {
            return view('event-logs.result', $response);
        }

        return response()->json($response);
    }

    /**
     * Get event logs statistics
     */
    public function statistics()
    {
        $stats = [
            'total' => EventLog::count(),
            'unprocessed' => EventLog::unprocessed()->count(),
            'processed' => EventLog::processed()->count(),
            'failed' => EventLog::failed()->count(),
            'skipped' => EventLog::where('process_status', EventLog::STATUS_SKIPPED)->count(),
            'today' => EventLog::whereDate('created_at', today())->count(),
            'last_event' => EventLog::latest()->first(),
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * View for processing event logs (web interface)
     */
    public function processPage()
    {
        $stats = [
            'total' => EventLog::count(),
            'unprocessed' => EventLog::unprocessed()->count(),
            'processed' => EventLog::processed()->count(),
            'failed' => EventLog::failed()->count(),
            'skipped' => EventLog::where('process_status', EventLog::STATUS_SKIPPED)->count(),
            'today' => EventLog::whereDate('created_at', today())->count(),
        ];

        $recentEvents = EventLog::with('user', 'attendanceRecord')
            ->latest()
            ->limit(20)
            ->get();

        $failedEvents = EventLog::failed()
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('event-logs.process', compact('stats', 'recentEvents', 'failedEvents'));
    }
}
