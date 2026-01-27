<?php

namespace App\Http\Controllers;

use App\Models\GeneralReport;
use App\Services\GeneralReportService;
use Illuminate\Http\Request;

class GeneralReportPrintController extends Controller
{
    protected $reportService;

    public function __construct(GeneralReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Generate and stream a general report PDF
     */
    public function generate(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['error' => 'Report ID is missing'], 400);
        }

        try {
            $report = GeneralReport::findOrFail($request->id);
            
            // Generate the report
            $result = $this->reportService->generateReport($report);
            
            if (!$result['success']) {
                return response()->json(['error' => $result['message']], 500);
            }
            
            // Stream the PDF
            return $this->reportService->streamPDF($report);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * View existing report or generate if not ready
     */
    public function view(Request $request)
    {
        if (!$request->has('id')) {
            return response()->json(['error' => 'Report ID is missing'], 400);
        }

        try {
            $report = GeneralReport::findOrFail($request->id);
            
            // Check if report is ready
            if (!$report->isReady()) {
                // Generate it if not ready
                $result = $this->reportService->generateReport($report);
                
                if (!$result['success']) {
                    return response()->json(['error' => $result['message']], 500);
                }
            }
            
            // Stream the existing PDF
            return $this->reportService->streamPDF($report);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
