<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>@include('pdf._style')</head>
<body>
@include('pdf._frame')
@php
    $hm = fn ($h) => \App\Services\Reports::hm((float) $h);
    $s = $summary;
    $totalHours = collect($days)->sum(fn ($d) => $d['record'] && $d['record']->status === 'Present' ? (float) $d['record']->hours : 0);
@endphp
<h1>{{ $title }}</h1>
<div class="sub">{{ \App\Services\Reports::range($from, $to) }}</div>

<table class="dl two" style="margin-bottom:10px">
    <tr><td class="k">Name</td><td><b>{{ $user->name }}</b></td><td class="k">Staff no.</td><td>{{ $user->employee_no ?: '–' }}</td></tr>
    <tr><td class="k">Job title</td><td>{{ $user->position ?: '–' }}</td><td class="k">Department</td><td>{{ optional($user->department)->name ?: '–' }}</td></tr>
    <tr><td class="k">Late after</td><td>{{ substr($user->lateTime(), 0, 5) }}</td><td class="k">Average arrival</td><td>{{ $avgArrival ?: '–' }}</td></tr>
</table>

@include('pdf._kpis', ['s' => $s, 'single' => true])

<h2>Day by day</h2>
<table class="t">
    <thead><tr><th style="width:74px">Date</th><th>Status</th><th class="r">First seen</th><th class="r">Last seen</th><th class="r">Hours worked</th><th class="r">Late by</th><th>Note</th></tr></thead>
    <tbody>
    @foreach ($days as $d)
        @php $r = $d['record']; @endphp
        @if (!$r)
            <tr class="off"><td>{{ $d['date']->format('D d M') }}</td><td colspan="6">{{ $d['off'] }}</td></tr>
        @else
            <tr>
                <td>{{ $d['date']->format('D d M') }}</td>
                <td><span class="st st-{{ $r->statusKey() }}">{{ $r->status === 'Absent' && $d['date']->isToday() ? 'Not in yet' : $r->statusLabel() }}</span></td>
                <td class="r">{{ $r->check_in_time ? substr($r->check_in_time, 0, 5) : '–' }}</td>
                <td class="r">{{ $r->check_out_time ? substr($r->check_out_time, 0, 5) : '–' }}</td>
                <td class="r">{{ $r->status === 'Present' ? $hm($r->hours) : '–' }}</td>
                <td class="r">{{ $r->late_minutes ? $hm($r->late_minutes / 60) : '–' }}</td>
                <td class="muted">
                    @if ($r->is_half_day) Half day – not seen leaving
                    @elseif ($r->status === 'Present' && !$r->check_out_time && $d['date']->isToday()) In progress
                    @elseif (!$r->is_working_day) {{ $r->holiday_name ?: 'Day off' }} – came in
                    @endif
                    @if ($r->is_manual) Corrected by HR @endif
                </td>
            </tr>
        @endif
    @endforeach
    <tr class="total"><td colspan="4">Total time on site</td><td class="r">{{ $hm($totalHours) }}</td><td class="r">{{ $s['late_minutes'] ? $hm($s['late_minutes'] / 60) : '–' }}</td><td></td></tr>
    </tbody>
</table>
<div class="small muted" style="margin-top:6px">Hours worked = time last seen by the terminal − time first seen. A day without a departure is credited as a half day ({{ $hm($fullDay / 2) }}).</div>

@if ($leave->isNotEmpty())
    <h2>Leave in this period</h2>
    <table class="t">
        <thead><tr><th>Reference</th><th>Type</th><th>From</th><th>To</th><th class="r">Days</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($leave as $l)
            <tr><td>{{ $l->reference }}</td><td>{{ $l->typeLabel() }}</td><td>{{ $l->start_date->format('d M Y') }}</td><td>{{ $l->end_date->format('d M Y') }}</td><td class="r">{{ $l->daysTaken() }}</td><td>{{ $l->statusLabel() }}</td></tr>
        @endforeach
        </tbody>
    </table>
@endif
@include('pdf._pagenum')
</body></html>
