{{-- Quick filters above the attendance records grid: one click for the common questions. --}}
@php
    $q = request()->except(['page', '_pjax']);
    $range = $q['attendance_date'] ?? [];
    $url = function (array $change) use ($q) {
        $next = array_merge($q, $change);
        $next = array_filter($next, fn ($v) => $v !== null && $v !== '' && $v !== []);

        return admin_url('attendance-records') . ($next ? '?' . http_build_query($next) : '');
    };
    $span = fn ($from, $to) => ['start' => $from->toDateString(), 'end' => $to->toDateString()];
    $days = [
        'Last working day' => $span($lastDay, $lastDay),
        'This week' => $span(today()->startOfWeek(), today()),
        'This month' => $span(today()->startOfMonth(), today()),
        'Last month' => $span(today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()),
    ];
    $current = fn ($r) => ($range['start'] ?? null) === $r['start'] && ($range['end'] ?? null) === $r['end'];
    $status = $q['status_key'] ?? '';
@endphp
<div class="ehr-chips">
    <div class="ehr-chip-group" aria-label="Period">
        <a href="{{ $url(['attendance_date' => null]) }}" class="{{ empty($range['start']) && empty($range['end']) ? 'on' : '' }}">All dates</a>
        @foreach ($days as $label => $r)
            <a href="{{ $url(['attendance_date' => $r]) }}" class="{{ $current($r) ? 'on' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="ehr-chip-group" aria-label="Status">
        <a href="{{ $url(['status_key' => null]) }}" class="{{ $status === '' ? 'on' : '' }}">Every status</a>
        @foreach ($statuses as $key => $label)
            <a href="{{ $url(['status_key' => $key]) }}" class="c-{{ $key }} {{ $status === $key ? 'on' : '' }}"><i></i>{{ $label }}</a>
        @endforeach
    </div>
</div>
