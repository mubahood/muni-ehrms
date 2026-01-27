<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Log Processing - Muni EHRMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            font-size: 36px;
            color: #667eea;
            margin-bottom: 10px;
        }
        .stat-card p {
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-card.warning h3 {
            color: #f59e0b;
        }
        .stat-card.success h3 {
            color: #10b981;
        }
        .stat-card.danger h3 {
            color: #ef4444;
        }
        .actions {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .actions h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin-right: 10px;
            margin-bottom: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .button:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .button.secondary {
            background: #6b7280;
        }
        .button.secondary:hover {
            background: #4b5563;
        }
        .events-table {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        .events-table h2 {
            margin-bottom: 20px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f9fafb;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        table tr:hover {
            background: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-default {
            background: #e5e7eb;
            color: #374151;
        }
        #processResult {
            margin-top: 20px;
            padding: 15px;
            border-radius: 6px;
            display: none;
        }
        #processResult.success {
            background: #d1fae5;
            color: #065f46;
            display: block;
        }
        #processResult.error {
            background: #fee2e2;
            color: #991b1b;
            display: block;
        }
        .loading {
            display: none;
            margin-left: 10px;
        }
        .loading.active {
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Event Log Processing</h1>
            <p>Manage and process attendance event logs from access control devices</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>{{ $stats['total'] }}</h3>
                <p>Total Events</p>
            </div>
            <div class="stat-card warning">
                <h3>{{ $stats['unprocessed'] }}</h3>
                <p>Unprocessed</p>
            </div>
            <div class="stat-card success">
                <h3>{{ $stats['processed'] }}</h3>
                <p>Processed</p>
            </div>
            <div class="stat-card danger">
                <h3>{{ $stats['failed'] }}</h3>
                <p>Failed</p>
            </div>
            <div class="stat-card">
                <h3>{{ $stats['skipped'] }}</h3>
                <p>Skipped</p>
            </div>
            <div class="stat-card">
                <h3>{{ $stats['today'] }}</h3>
                <p>Today</p>
            </div>
        </div>

        <div class="actions">
            <h2>Actions</h2>
            <button onclick="processEvents(100)" class="button">
                Process 100 Events
                <span class="loading" id="loading1">⏳</span>
            </button>
            <button onclick="processEvents(500)" class="button">
                Process 500 Events
                <span class="loading" id="loading2">⏳</span>
            </button>
            <button onclick="processEvents(1000)" class="button">
                Process 1000 Events
                <span class="loading" id="loading3">⏳</span>
            </button>
            <a href="{{ url('event-logs/statistics') }}" class="button secondary" target="_blank">View Statistics (JSON)</a>
            <button onclick="location.reload()" class="button secondary">Refresh Page</button>
            
            <div id="processResult"></div>
        </div>

        @if($failedEvents->count() > 0)
        <div class="events-table">
            <h2>❌ Failed Events (Last 10)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee No</th>
                        <th>Employee Name</th>
                        <th>Event Time</th>
                        <th>Status</th>
                        <th>Error Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($failedEvents as $event)
                    <tr>
                        <td>{{ $event->id }}</td>
                        <td>{{ $event->employee_no }}</td>
                        <td>{{ $event->employee_name }}</td>
                        <td>{{ $event->event_time }}</td>
                        <td><span class="badge badge-danger">{{ $event->process_status }}</span></td>
                        <td>{{ $event->process_error }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="events-table">
            <h2>📝 Recent Events (Last 20)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee No</th>
                        <th>Employee Name</th>
                        <th>Event Time</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentEvents as $event)
                    <tr>
                        <td>{{ $event->id }}</td>
                        <td>{{ $event->employee_no }}</td>
                        <td>{{ $event->employee_name }}</td>
                        <td>{{ $event->event_time }}</td>
                        <td>{{ $event->user ? $event->user->name : '-' }}</td>
                        <td>
                            @if($event->process_status == 'processed')
                                <span class="badge badge-success">{{ $event->process_status }}</span>
                            @elseif($event->process_status == 'failed')
                                <span class="badge badge-danger">{{ $event->process_status }}</span>
                            @elseif($event->process_status == 'unprocessed')
                                <span class="badge badge-warning">{{ $event->process_status }}</span>
                            @else
                                <span class="badge badge-default">{{ $event->process_status }}</span>
                            @endif
                        </td>
                        <td>{{ $event->attendanceRecord ? 'ID: ' . $event->attendanceRecord->id : '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function processEvents(limit) {
            const resultDiv = document.getElementById('processResult');
            const loadingId = limit === 100 ? 'loading1' : (limit === 500 ? 'loading2' : 'loading3');
            const loading = document.getElementById(loadingId);
            
            resultDiv.style.display = 'none';
            loading.classList.add('active');

            fetch(`/process-event-logs?limit=${limit}`)
                .then(response => response.json())
                .then(data => {
                    loading.classList.remove('active');
                    resultDiv.className = 'success';
                    resultDiv.innerHTML = `
                        <strong>✅ Processing Complete!</strong><br>
                        Processed: ${data.results.processed} | 
                        Failed: ${data.results.failed} | 
                        Skipped: ${data.results.skipped} | 
                        Total: ${data.results.total}
                        <br><br>
                        Before: Unprocessed ${data.before.unprocessed} → After: Unprocessed ${data.after.unprocessed}
                    `;
                    
                    // Refresh page after 3 seconds
                    setTimeout(() => location.reload(), 3000);
                })
                .catch(error => {
                    loading.classList.remove('active');
                    resultDiv.className = 'error';
                    resultDiv.innerHTML = `<strong>❌ Error:</strong> ${error.message}`;
                });
        }
    </script>
</body>
</html>
