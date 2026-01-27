<?php

namespace App\Http\Controllers;

use App\Models\GeneralReport;
use App\Services\GeneralReportService;
use Illuminate\Http\Request;

class ReportGenerationController extends Controller
{
    protected $service;

    public function __construct(GeneralReportService $service)
    {
        $this->service = $service;
    }

    /**
     * Generate a general attendance report.
     */
    public function generateGeneralReport(Request $request)
    {
        $reportId = $request->input('id');
        
        if (!$reportId) {
            return response()->json([
                'success' => false,
                'message' => 'Report ID is required'
            ], 400);
        }

        $report = GeneralReport::findOrFail($reportId);
        
        // Generate the report
        $result = $this->service->generateReport($report);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'report_id' => $report->id,
                    'file_path' => $result['file_path'],
                    'generation_time' => $result['generation_time'] . 's',
                    'download_url' => url('api/reports/download/' . $report->id)
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 500);
    }

    /**
     * Download a generated report.
     */
    public function downloadReport($id)
    {
        $report = GeneralReport::findOrFail($id);
        
        if (!$report->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Report not yet generated or file not found'
            ], 404);
        }

        return $this->service->streamPDF($report);
    }

    /**
     * Get report statistics.
     */
    public function getStatistics($id)
    {
        $report = GeneralReport::findOrFail($id);
        $stats = $this->service->getReportStatistics($report);
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Regenerate an existing report.
     */
    public function regenerateReport($id)
    {
        $report = GeneralReport::findOrFail($id);
        
        // Delete old file if exists
        $report->deleteFile();
        
        // Reset status
        $report->update([
            'is_generated' => 'No',
            'file_path' => null
        ]);
        
        // Generate new report
        $result = $this->service->generateReport($report);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['success'] ? [
                'report_id' => $report->id,
                'generation_time' => $result['generation_time'] . 's'
            ] : null
        ]);
    }
}
