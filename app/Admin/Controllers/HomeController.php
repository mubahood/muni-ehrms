<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Leave;
use App\Models\SystemConfiguration; // <-- IMPORT THIS
use App\Models\User;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Column;
use Encore\Admin\Layout\Content;
use Encore\Admin\Layout\Row;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Content $content)
    {


        $user = Admin::user();
        $config = SystemConfiguration::first(); // <-- GET SYSTEM CONFIG
        $companyName = $config->company_name ?? 'Your Company';
        $TIME_NOW = Carbon::now()->format('H:i:s'); // <-- CURRENT TIME

        $content
            ->title('TIME: ' . $TIME_NOW)
            ->description("Welcome to the {$companyName} Attendance Portal.");

        // --- Date variables ---
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        /*
        |--------------------------------------------------------------------------
        | Row 1: KPI Cards
        |--------------------------------------------------------------------------
        */
        $content->row(function (Row $row) use ($startOfWeek, $endOfWeek) {

            // Card 1: Present This Week
            $row->column(3, function (Column $column) use ($startOfWeek, $endOfWeek) {
                $count = AttendanceRecord::whereBetween('attendance_date', [$startOfWeek, $endOfWeek])->where('status', 'Present')->count();
                $column->append(view('admin.widgets.kpi_card', [
                    'title'  => 'Present This Week',
                    'number' => $count,
                    'icon'   => 'fa-check-square-o',
                    'color_class' => 'bg-primary-green',
                ]));
            });

            // Card 2: Absences This Week
            $row->column(3, function (Column $column) use ($startOfWeek, $endOfWeek) {
                $count = AttendanceRecord::whereBetween('attendance_date', [$startOfWeek, $endOfWeek])->where('status', 'Absent')->count();
                $column->append(view('admin.widgets.kpi_card', [
                    'title'  => 'Absences This Week',
                    'number' => $count,
                    'icon'   => 'fa-user-times',
                    'color_class' => 'bg-red-accent',
                ]));
            });

            // Card 3: Late Arrivals This Week
            $row->column(3, function (Column $column) use ($startOfWeek, $endOfWeek) {
                $count = AttendanceRecord::whereBetween('attendance_date', [$startOfWeek, $endOfWeek])->where('is_late', 'Yes')->count();
                $column->append(view('admin.widgets.kpi_card', [
                    'title'  => 'Late Arrivals This Week',
                    'number' => $count,
                    'icon'   => 'fa-clock-o',
                    'color_class' => 'bg-yellow-accent',
                ]));
            });

            // Card 4: On Leave This Week
            $row->column(3, function (Column $column) use ($startOfWeek, $endOfWeek) {
                $count = Leave::where('start_date', '<=', $endOfWeek)->where('end_date', '>=', $startOfWeek)->count();
                $column->append(view('admin.widgets.kpi_card', [
                    'title'  => 'On Leave This Week',
                    'number' => $count,
                    'icon'   => 'fa-suitcase',
                    'color_class' => 'bg-aqua-accent',
                ]));
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Row 2: Charts for Visual Trends
        |--------------------------------------------------------------------------
        */
        $content->row(function (Row $row) use ($startOfMonth, $endOfMonth, $thirtyDaysAgo) {

            // Pie Chart
            $row->column(6, function (Column $column) use ($startOfMonth, $endOfMonth) {
                // ... same logic as before ...
                $presentCount = AttendanceRecord::whereBetween('attendance_date', [$startOfMonth, $endOfMonth])->where('status', 'Present')->count();
                $absentCount = AttendanceRecord::whereBetween('attendance_date', [$startOfMonth, $endOfMonth])->where('status', 'Absent')->count();
                $leaveCount = Leave::where('start_date', '<=', $endOfMonth)->where('end_date', '>=', $startOfMonth)->count();
                $pieChartData = ['labels' => ['Present', 'Absent', 'On Leave'], 'data' => [$presentCount, $absentCount, $leaveCount]];
                $column->append(view('admin.charts.attendance_pie_chart', $pieChartData));
            });

            // In HomeController.php

            // Bar Chart: Weekly Attendance Summary
            $row->column(6, function (Column $column) use ($thirtyDaysAgo) {
                // 1. UPDATE THE QUERY to select the new "on_time_count"
                $issuesData = AttendanceRecord::where('attendance_date', '>=', $thirtyDaysAgo)
                    ->select(
                        'day',
                        DB::raw("SUM(CASE WHEN status = 'Present' AND is_late = 'No' THEN 1 ELSE 0 END) as on_time_count"),
                        DB::raw("SUM(CASE WHEN is_late = 'Yes' THEN 1 ELSE 0 END) as late_count"),
                        DB::raw("SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count")
                    )
                    ->groupBy('day')
                    ->orderByRaw("FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')")
                    ->get();

                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

                // 2. INITIALIZE the new array for on-time counts
                $onTimeCounts = array_fill_keys($days, 0);
                $lateCounts = array_fill_keys($days, 0);
                $absentCounts = array_fill_keys($days, 0);

                // 3. POPULATE the new array
                foreach ($issuesData as $data) {
                    $onTimeCounts[$data->day] = (int)$data->on_time_count;
                    $lateCounts[$data->day] = (int)$data->late_count;
                    $absentCounts[$data->day] = (int)$data->absent_count;
                }

                // 4. PASS the new data to the view
                $barChartData = [
                    'labels'        => $days,
                    'on_time_data'  => array_values($onTimeCounts),
                    'late_data'     => array_values($lateCounts),
                    'absent_data'   => array_values($absentCounts),
                ];
                $column->append(view('admin.charts.weekly_issues_bar_chart', $barChartData));
            });
        });

        /*
        |--------------------------------------------------------------------------
        | Row 3: Actionable Lists
        |--------------------------------------------------------------------------
        */
        $content->row(function (Row $row) use ($thirtyDaysAgo) {

            // Top Absentees
            $row->column(6, function (Column $column) use ($thirtyDaysAgo) {
                // ... same logic as before ...
                $topAbsentees = User::withCount(['attendanceRecords' => function ($query) use ($thirtyDaysAgo) {
                    $query->where('status', 'Absent')->where('attendance_date', '>=', $thirtyDaysAgo);
                }])->orderBy('attendance_records_count', 'desc')->take(5)->get();
                $column->append(view('admin.lists.top_employees', ['title' => 'Top Absentees (Last 30 Days)', 'users' => $topAbsentees, 'count_field' => 'attendance_records_count', 'count_label' => 'Days', 'count_class' => 'count-danger']));
            });

            // Top Latecomers
            $row->column(6, function (Column $column) use ($thirtyDaysAgo) {
                // ... same logic as before ...
                $topLatecomers = User::withCount(['attendanceRecords' => function ($query) use ($thirtyDaysAgo) {
                    $query->where('is_late', 'Yes')->where('attendance_date', '>=', $thirtyDaysAgo);
                }])->orderBy('attendance_records_count', 'desc')->take(5)->get();
                $column->append(view('admin.lists.top_employees', ['title' => 'Top Late Arrivals (Last 30 Days)', 'users' => $topLatecomers, 'count_field' => 'attendance_records_count', 'count_label' => 'Times', 'count_class' => 'count-warning']));
            });
        });

        return $content;
    }
}
