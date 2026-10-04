@php
    $tab = fn ($s) => admin_url('leave/all') . '?' . http_build_query(array_filter(array_merge($filters, ['status' => $s])));
    $total = $counts->sum();
@endphp
<div class="ehr">
    <form method="get" action="{{ admin_url('leave/all') }}" class="ehr-toolbar">
        <div class="ehr-toolbar" style="margin:0">
            <div class="ehr-field"><label for="q">Search</label><input id="q" name="q" class="form-control" placeholder="Name or reference" value="{{ $filters['q'] ?? '' }}"></div>
            <div class="ehr-field"><label for="type">Type</label>
                <select id="type" name="type" class="form-control"><option value="">All types</option>
                    @foreach (\App\Models\Leave::TYPES as $k => $v)<option value="{{ $k }}" {{ ($filters['type'] ?? '') === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                </select></div>
            @if ($departments->count() > 1)
                <div class="ehr-field"><label for="department">Department</label>
                    <select id="department" name="department" class="form-control"><option value="">All</option>
                        @foreach ($departments as $d)<option value="{{ $d->id }}" {{ (string) ($filters['department'] ?? '') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>@endforeach
                    </select></div>
            @endif
            <div class="ehr-field"><label for="year">Leave year</label>
                <select id="year" name="year" class="form-control"><option value="">All</option>
                    @foreach ($years as $y)<option value="{{ $y }}" {{ (string) ($filters['year'] ?? '') === (string) $y ? 'selected' : '' }}>{{ \App\Services\LeaveRules::yearLabel($y) }}</option>@endforeach
                </select></div>
            <div class="ehr-field"><label for="when">When</label>
                <select id="when" name="when" class="form-control"><option value="">Any time</option>
                    <option value="now" {{ ($filters['when'] ?? '') === 'now' ? 'selected' : '' }}>Away today</option>
                    <option value="upcoming" {{ ($filters['when'] ?? '') === 'upcoming' ? 'selected' : '' }}>Approved, not started</option>
                </select></div>
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <button class="btn btn-primary">Filter</button>
            @if (array_filter($filters))<a href="{{ admin_url('leave/all') }}" class="btn btn-default">Clear</a>@endif
        </div>
        @if (\App\Services\AccessPolicy::allows(Admin::user(), 'leave.manage'))
            <a href="{{ admin_url('leave/record') }}" class="btn btn-default"><i class="fa fa-pencil-square-o"></i>&nbsp; Record leave</a>
        @endif
    </form>

    <nav class="ehr-tabs">
        <a href="{{ $tab(null) }}" class="{{ $status ? '' : 'on' }}">All<span class="count">{{ $total }}</span></a>
        @foreach (\App\Models\Leave::STATUSES as $k => $label)
            <a href="{{ $tab($k) }}" class="{{ $status === $k ? 'on' : '' }}">{{ $label }}<span class="count">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </nav>

    <div class="ehr-panel">
        <div class="ehr-panel-body flush ehr-table-wrap">
            @if ($leaves->isEmpty())
                <div class="ehr-empty">No leave matches these filters.</div>
            @else
                @include('ehrms.leave._table', ['leaves' => $leaves, 'showPerson' => true])
            @endif
        </div>
        @if ($leaves->hasPages())
            <div class="ehr-panel-foot">{{ $leaves->links() }}</div>
        @endif
    </div>
</div>
