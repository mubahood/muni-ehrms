<div class="ehr">
    <form method="get" action="{{ admin_url('audit-log') }}" class="ehr-toolbar">
        <div class="ehr-toolbar" style="margin:0">
            <div class="ehr-field"><label for="q">Search</label><input id="q" name="q" class="form-control" placeholder="Person, text or IP" value="{{ $filters['q'] ?? '' }}"></div>
            <div class="ehr-field"><label for="action">What</label>
                <select id="action" name="action" class="form-control"><option value="">Everything</option>
                    @foreach ($groups as $k => $label)<option value="{{ $k }}" {{ ($filters['action'] ?? '') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                    <option value="auth.failed" {{ ($filters['action'] ?? '') === 'auth.failed' ? 'selected' : '' }}>Failed sign-ins only</option>
                </select></div>
            <div class="ehr-field"><label for="from">From</label><input id="from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="ehr-field"><label for="to">To</label><input id="to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
            <button class="btn btn-primary">Filter</button>
            @if (array_filter($filters))<a href="{{ admin_url('audit-log') }}" class="btn btn-default">Clear</a>@endif
        </div>
        @if ($failedToday)
            <a href="{{ admin_url('audit-log') }}?action=auth.failed&from={{ today()->toDateString() }}" class="st st-rejected" style="align-self:center;padding:6px 10px">{{ $failedToday }} failed sign-in{{ $failedToday === 1 ? '' : 's' }} today</a>
        @endif
    </form>
    <div class="ehr-panel">
        <div class="ehr-panel-body flush ehr-table-wrap">
            @if ($logs->isEmpty())
                <div class="ehr-empty">Nothing recorded for these filters.</div>
            @else
                <table class="ehr-table">
                    <thead><tr><th style="width:150px">When</th><th>Who</th><th>What</th><th>Details</th><th>From</th></tr></thead>
                    <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="ehr-small" style="white-space:nowrap">{{ $log->created_at->format('d M Y') }}<span class="cell-sub">{{ $log->created_at->format('H:i:s') }}</span></td>
                            <td>{{ $log->user_name ?: '—' }}</td>
                            <td style="white-space:nowrap"><span class="st {{ $log->action === 'auth.failed' ? 'st-rejected' : 'st-muted' }}">{{ $log->actionLabel() }}</span></td>
                            <td>{{ $log->description }}
                                @if ($log->subject_type === \App\Models\Leave::class)<a href="{{ admin_url('leave/' . $log->subject_id) }}" class="cell-sub">Open request</a>@endif
                                @if ($log->subject_type === \App\Models\AttendanceRecord::class)<a href="{{ admin_url('attendance-records/' . $log->subject_id) }}" class="cell-sub">Open record</a>@endif
                            </td>
                            <td class="ehr-small ehr-muted" style="white-space:nowrap">{{ $log->ip_address }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
        @if ($logs->hasPages())<div class="ehr-panel-foot">{{ $logs->links() }}</div>@endif
    </div>
</div>
