<?php

namespace App\Services;

use App\Models\{AttendanceRecord, GeneralReport, Leave, SystemConfiguration, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class GeneralReportService
{
    /**
     * Generate a general attendance report.
     *
     * @param GeneralReport $report
     * @return array
     */
    public function generateReport(GeneralReport $report): array
    {
        $startTime = microtime(true);
        
        try {
            // Mark as processing
            $report->update([
                'status' => 'processing',
                'is_generated' => 'No',
                'file_path' => null,
            ]);

            // Same figures and layout as the Reports page (App\Services\Reports).
            [$view, $data, $orientation] = $this->buildReport($report);
            $filePath = $this->savePdf($report, $view, $data, $orientation);
            
            // Calculate generation time
            $generationTime = round(microtime(true) - $startTime, 2);
            
            // Mark as completed
            $report->update([
                'status' => 'completed',
                'is_generated' => 'Yes',
                'file_path' => $filePath,
                'total_employees' => $data['summary']['people'] ?? null,
                'total_records' => $data['summary']['working'] ?? null,
                'generation_time' => $generationTime,
            ]);

            return [
                'success' => true,
                'message' => 'Report generated successfully',
                'file_path' => $filePath,
                'generation_time' => $generationTime,
            ];

        } catch (\Exception $e) {
            Log::error('General Report Generation Failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $report->update([
                'status' => 'failed',
                'is_generated' => 'No',
                'error_message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Report generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Gather all data needed for the report.
     *
     * @param GeneralReport $report
     * @return array
     */
    protected function gatherReportData(GeneralReport $report): array
    {
        $config = SystemConfiguration::first();
        $startDate = Carbon::parse($report->start_date);
        $endDate = Carbon::parse($report->end_date);
        $users = $this->getFilteredUsers($report);

        // Executive Summary KPIs
        $summary = $this->calculateSummaryStats($startDate, $endDate);
        
        // Trend Analysis by Day of Week
        $dayOfWeekTrends = $this->calculateDayOfWeekTrends($startDate, $endDate);
        
        // Detailed Employee Records
        $employeeReports = $this->gatherEmployeeReports($users, $startDate, $endDate);

        return [
            'report' => $report,
            'config' => $config,
            'summary' => $summary,
            'dayOfWeekTrends' => $dayOfWeekTrends,
            'employeeReports' => $employeeReports,
            'stats' => [
                'total_employees' => $users->count(),
                'total_records' => count($employeeReports),
            ],
        ];
    }

    /**
     * Calculate summary statistics.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function calculateSummaryStats(Carbon $startDate, Carbon $endDate): array
    {
        $records = AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate]);

        return [
            'present' => (clone $records)->where('status', 'Present')->count(),
            'absent' => (clone $records)->where('status', 'Absent')->count(),
            'half_day' => (clone $records)->where('status', 'Half Day')->count(),
            'late' => (clone $records)->where('is_late', 'Yes')->count(),
            'hours' => round((clone $records)->where('status', 'Present')->sum('hours'), 2),
            'leave' => Leave::where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->count(),
        ];
    }

    /**
     * Calculate day of week trends.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Support\Collection
     */
    protected function calculateDayOfWeekTrends(Carbon $startDate, Carbon $endDate)
    {
        return AttendanceRecord::whereBetween('attendance_date', [$startDate, $endDate])
            ->select(
                'day',
                DB::raw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count"),
                DB::raw("SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw("SUM(CASE WHEN is_late = 'Yes' THEN 1 ELSE 0 END) as late_count")
            )
            ->groupBy('day')
            ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
            ->get();
    }

    /**
     * Gather detailed employee reports.
     *
     * @param \Illuminate\Support\Collection $users
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    protected function gatherEmployeeReports($users, Carbon $startDate, Carbon $endDate): array
    {
        $employeeReports = [];

        foreach ($users as $user) {
            $records = AttendanceRecord::where('user_id', $user->id)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->get()
                ->keyBy('attendance_date');

            $userSummary = [
                'name' => $user->name,
                'id' => $user->id,
                'present' => $records->where('status', 'Present')->count(),
                'absent' => $records->where('status', 'Absent')->count(),
                'half_day' => $records->where('status', 'Half Day')->count(),
                'late' => $records->where('is_late', 'Yes')->count(),
                'hours' => round($records->where('status', 'Present')->sum('hours'), 2),
                'log' => []
            ];

            $period = new \DatePeriod(
                new \DateTime($startDate->toDateString()),
                new \DateInterval('P1D'),
                new \DateTime($endDate->copy()->addDay()->toDateString())
            );

            foreach ($period as $date) {
                $currentDateStr = $date->format('Y-m-d');
                $dayName = $date->format('l');
                $record = $records->get($currentDateStr);

                $userSummary['log'][$currentDateStr] = [
                    'day' => $dayName,
                    'status' => $record->status ?? ($user->isAvailableOnDay($currentDateStr) ? 'Missing' : 'Off Day'),
                    'check_in' => $record->check_in_time ?? '-',
                    'check_out' => $record->check_out_time ?? '-',
                    'hours' => $record->hours ?? '-',
                    'is_late' => $record->is_late ?? 'No',
                ];
            }

            $employeeReports[] = $userSummary;
        }

        return $employeeReports;
    }

    /**
     * Generate PDF from report data.
     *
     * @param GeneralReport $report
     * @param array $data
     * @return string
     */
    protected function generatePDF(GeneralReport $report, array $data): string
    {
        $pdf = PDF::loadView('reports.general-attendance', $data);
        $pdf->setPaper('a4', 'landscape');
        
        $fileName = 'reports/general-attendance-' . $report->id . '-' . time() . '.pdf';
        $fullPath = \App\Models\GeneralReport::storagePath($fileName);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $pdf->save($fullPath);
        
        return $fileName;
    }

    /**
     * Stream PDF for download.
     *
     * @param GeneralReport $report
     * @return \Illuminate\Http\Response
     */
    public function streamPDF(GeneralReport $report)
    {
        if (!$report->isReady()) {
            abort(404, 'Report file not found or not yet generated.');
        }

        $fullPath = \App\Models\GeneralReport::storagePath($report->file_path);
        
        return response()->download($fullPath, 'General-Attendance-Report-' . $report->id . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Get report statistics.
     *
     * @param GeneralReport $report
     * @return array
     */
    public function getReportStatistics(GeneralReport $report): array
    {
        $startDate = Carbon::parse($report->start_date);
        $endDate = Carbon::parse($report->end_date);
        
        return [
            'period' => [
                'start' => $startDate->format('d M, Y'),
                'end' => $endDate->format('d M, Y'),
                'days' => $startDate->diffInDays($endDate) + 1,
            ],
            'summary' => $this->calculateSummaryStats($startDate, $endDate),
            'file_info' => [
                'exists' => $report->isReady(),
                'size' => $report->file_size,
                'generated_at' => $report->updated_at?->format('d M, Y H:i'),
            ],
        ];
    }

    /**
     * Get filtered users based on report type.
     *
     * @param GeneralReport $report
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getFilteredUsers(GeneralReport $report)
    {
        // The older report module serves the real university only (demo accounts cannot open it).
        $query = User::where('status', 'Active')->where('is_demo', false);

        // Filter based on report type
        switch ($report->report_type) {
            case 'user':
                // Single user report
                if ($report->target_user_id) {
                    $query->where('id', $report->target_user_id);
                }
                break;

            case 'department':
                // Department-specific report
                if ($report->target_department_id) {
                    $query->where('department_id', $report->target_department_id);
                }
                break;

            case 'general':
            default:
                // All active users (no additional filtering)
                break;
        }

        return $query->get();
    }

    /**
     * The report's view and data, built exactly as on the Reports page.
     *
     * @return array{0:string, 1:array, 2:string}
     */
    protected function buildReport(GeneralReport $report): array
    {
        $from = Carbon::parse($report->start_date)->startOfDay();
        $to = Carbon::parse($report->end_date)->startOfDay();

        if ($report->report_type === 'user' && $report->target_user_id) {
            $user = User::findOrFail($report->target_user_id);

            return ['pdf.individual', \App\Services\Reports::individual($user, $from, $to), 'portrait'];
        }
        if ($report->report_type === 'department' && $report->target_department_id) {
            $department = \App\Models\Department::findOrFail($report->target_department_id);
            $ids = User::where('department_id', $department->id)->pluck('id')->all();

            return ['pdf.summary', \App\Services\Reports::summary($department->name, $ids, $from, $to), 'landscape'];
        }

        return ['pdf.summary', \App\Services\Reports::summary('Whole university', null, $from, $to), 'landscape'];
    }

    protected function savePdf(GeneralReport $report, string $view, array $data, string $orientation): string
    {
        $creator = User::find($report->user_id);
        $pdf = \App\Services\Reports::pdf($view, $data, $orientation, $creator ? $creator->displayName() : 'EHRMS');
        $fileName = "reports/general-attendance-{$report->id}-" . time() . '.pdf';
        $fullPath = \App\Models\GeneralReport::storagePath($fileName);
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }
        $pdf->save($fullPath);

        return $fileName;
    }
}
