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
        <div class="header-section">
            <table class="header-table">
                <tr>
                    <td class="logo-cell">
                        @if($config && $config->company_logo)
                            <img src="{{ public_path('storage/' . $config->company_logo) }}" alt="Logo" class="logo">
                        @endif
                    </td>
                    <td class="company-details">
                        <h1>{{ $config->company_name ?? 'Your Company' }}</h1>
                        <p class="company-info">{{ $config->company_address ?? '' }}</p>
                        <p class="company-info">{{ $config->company_phone ?? '' }} | {{ $config->company_email ?? '' }}</p>
                    </td>
                    <td class="report-info">
                        <h2>General Attendance Report</h2>
                        <p class="date-range"><strong>Date Range:</strong> {{ \Carbon\Carbon::parse($report->start_date)->format('d M, Y') }} to {{ \Carbon\Carbon::parse($report->end_date)->format('d M, Y') }}</p>
                        <p class="generated-date"><strong>Generated On:</strong> {{ \Carbon\Carbon::now()->format('d M, Y H:i') }}</p>
                    </td>
                </tr>
            </table>
            <div class="header-divider">
                <div class="divider-thick"></div>
                <div class="divider-thin"></div>
            </div>
        </div>

        <!-- Executive Summary -->
        <h2 class="section-title">Executive Summary</h2>
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['present'] }}</div>
                <div class="kpi-label">Total Present Days</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['absent'] }}</div>
                <div class="kpi-label">Total Absent Days</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['half_day'] ?? 0 }}</div>
                <div class="kpi-label">Half Day Records</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['late'] }}</div>
                <div class="kpi-label">Total Late Incidents</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['leave'] }}</div>
                <div class="kpi-label">Total Leave Days</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value">{{ $summary['hours'] }}</div>
                <div class="kpi-label">Total Hours Worked</div>
            </div>
        </div>

        <!-- Performance Metrics -->
        @php
            $totalDays = $summary['present'] + $summary['absent'] + ($summary['half_day'] ?? 0);
            $attendanceRate = $totalDays > 0 ? round(($summary['present'] / $totalDays) * 100, 1) : 0;
            $punctualityRate = $summary['present'] > 0 ? round((($summary['present'] - $summary['late']) / $summary['present']) * 100, 1) : 0;
        @endphp
        
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-value">{{ $attendanceRate }}%</div>
                <div class="metric-label">Attendance Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $punctualityRate }}%</div>
                <div class="metric-label">Punctuality Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ round($summary['hours'] / max($summary['present'], 1), 1) }}</div>
                <div class="metric-label">Avg Hours/Day</div>
            </div>
        </div>

        <!-- Trend Analysis -->
        <h2 class="section-title">Trend Analysis by Day of Week</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>Day of Week</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Absences</th>
                    <th class="text-center">Late Arrivals</th>
                    <th class="text-center">Attendance %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dayOfWeekTrends as $day)
                @php
                    $total = $day->present_count + $day->absent_count;
                    $dayRate = $total > 0 ? round(($day->present_count / $total) * 100, 1) : 0;
                @endphp
                <tr>
                    <td><strong>{{ $day->day }}</strong></td>
                    <td class="text-center">{{ $day->present_count }}</td>
                    <td class="text-center">{{ $day->absent_count }}</td>
                    <td class="text-center">{{ $day->late_count }}</td>
                    <td class="text-center">
                        <span style="color: {{ $dayRate >= 90 ? '#059669' : ($dayRate >= 75 ? '#f59e0b' : '#ef4444') }};">
                            {{ $dayRate }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="page-break"></div>

        <!-- Detailed Employee Records -->
        <h2 class="section-title">Detailed Employee Attendance Records</h2>
        @foreach($employeeReports as $employee)
            <h3>
                {{ $employee['name'] }} (ID: {{ $employee['id'] }})
                <span style="float: right; font-size: 9px; font-weight: normal; color: #6b7280;">
                    Present: {{ $employee['present'] }} | Absent: {{ $employee['absent'] }} | Late: {{ $employee['late'] }}
                </span>
            </h3>
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
                            <td>
                                <span class="status-{{ str_replace(' ', '', $log['status']) }}" 
                                      style="padding: 2px 6px; font-size: 8px; font-weight: 600; border-radius: 0;
                                      @if($log['status'] == 'Present') background: #d1fae5; color: #065f46;
                                      @elseif($log['status'] == 'Absent') background: #fee2e2; color: #991b1b;
                                      @elseif($log['status'] == 'Half Day') background: #fef3c7; color: #92400e;
                                      @else background: #e5e7eb; color: #374151; @endif">
                                    {{ $log['status'] }}
                                </span>
                            </td>
                            <td class="text-center">{{ $log['check_in'] }}</td>
                            <td class="text-center">{{ $log['check_out'] }}</td>
                            <td class="text-center">{{ $log['hours'] }}</td>
                            <td class="text-center">
                                @if($log['is_late'] == 'Yes')
                                    <span style="color: #dc2626; font-size: 8px; font-weight: 600;">⚠ Late</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                 <tfoot>
                    <tr style="font-weight: 600; background-color: #f3f4f6;">
                        <td colspan="2" style="font-size: 9px;">Period Summary:</td>
                        <td colspan="2" style="font-size: 9px;">P: {{ $employee['present'] }} | A: {{ $employee['absent'] }} | HD: {{ $employee['half_day'] }}</td>
                        <td class="text-center" colspan="2" style="font-size: 9px;"><strong>{{ $employee['hours'] }} hrs</strong></td>
                        <td class="text-center" style="font-size: 9px;">Late: {{ $employee['late'] }}</td>
                    </tr>
                </tfoot>
            </table>
        @endforeach
    </div>
</body>
</html>