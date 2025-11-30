<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>General Attendance Report</title>
    <link rel="stylesheet" href="{{ public_path('css/report.css') }}">
</head>
<body>
    <div class="footer">
        {{ $config->company_name ?? 'Attendance System' }} - General Attendance Report - Page <script type="text/php">echo $PAGE_NUM;</script>
    </div>

    <div class="container">
        <!-- Report Header -->
        <table class="header-table">
            <tr>
                <td>
                    @if($config && $config->company_logo)
                        <img src="{{ public_path('storage/' . $config->company_logo) }}" alt="Logo" class="logo">
                    @endif
                </td>
                <td class="company-details">
                    <h1>{{ $config->company_name ?? 'Your Company' }}</h1>
                    <p>{{ $config->company_address ?? '' }}</p>
                    <p>{{ $config->company_phone ?? '' }} | {{ $config->company_email ?? '' }}</p>
                </td>
                <td class="report-info">
                    <h2>General Attendance Report</h2>
                    <p><b>Date Range:</b> {{ \Carbon\Carbon::parse($report->start_date)->format('d M, Y') }} to {{ \Carbon\Carbon::parse($report->end_date)->format('d M, Y') }}</p>
                    <p><b>Generated On:</b> {{ \Carbon\Carbon::now()->format('d M, Y H:i') }}</p>
                </td>
            </tr>
        </table>

        <!-- Executive Summary -->
        <h2 class="section-title">Executive Summary</h2>
        <div class="kpi-container">
            <div class="kpi-box"><p class="number">{{ $summary['present'] }}</p><p class="title">Total Present Days</p></div>
            <div class="kpi-box"><p class="number">{{ $summary['absent'] }}</p><p class="title">Total Absent Days</p></div>
            <div class="kpi-box"><p class="number">{{ $summary['late'] }}</p><p class="title">Total Late Incidents</p></div>
            <div class="kpi-box"><p class="number">{{ $summary['leave'] }}</p><p class="title">Total Leave Days</p></div>
            <div class="kpi-box"><p class="number">{{ $summary['hours'] }}</p><p class="title">Total Hours Worked</p></div>
        </div>

        <!-- Trend Analysis -->
        <h2 class="section-title">Trend Analysis by Day of Week</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Day of Week</th>
                    <th class="text-center">Total Absences</th>
                    <th class="text-center">Total Late Arrivals</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dayOfWeekTrends as $day)
                <tr>
                    <td>{{ $day->day }}</td>
                    <td class="text-center">{{ $day->absent_count }}</td>
                    <td class="text-center">{{ $day->late_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="page-break"></div>

        <!-- Detailed Employee Records -->
        <h2 class="section-title">Detailed Employee Attendance Records</h2>
        @foreach($employeeReports as $employee)
            <h3 style="font-size: 13px; margin-top: 20px; margin-bottom: 10px;">Employee: {{ $employee['name'] }} (ID: {{ $employee['id'] }})</h3>
            <table class="report-table employee-log-table">
                 <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th class="text-center">Check-In</th>
                        <th class="text-center">Check-Out</th>
                        <th class="text-center">Hours</th>
                        <th class="text-center">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employee['log'] as $date => $log)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</td>
                            <td>{{ $log['day'] }}</td>
                            <td class="status-{{ str_replace(' ', '', $log['status']) }}">{{ $log['status'] }}</td>
                            <td class="text-center">{{ $log['check_in'] }}</td>
                            <td class="text-center">{{ $log['check_out'] }}</td>
                            <td class="text-center">{{ $log['hours'] }}</td>
                            <td class="text-center">
                                @if($log['is_late'] == 'Yes')
                                    <span class="text-warning">Late</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                 <tfoot>
                    <tr style="font-weight: bold; background-color: #f2f2f2;">
                        <td colspan="2">Period Totals:</td>
                        <td>Present: {{ $employee['present'] }} | Absent: {{ $employee['absent'] }}</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">{{ $employee['hours'] }}</td>
                        <td class="text-center">Late: {{ $employee['late'] }}</td>
                    </tr>
                </tfoot>
            </table>
        @endforeach
    </div>
</body>
</html>