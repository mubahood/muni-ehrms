<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>@include('pdf._style')</head>
<body>
@include('pdf._frame')
@php $pct = fn ($v) => $v === null ? '–' : number_format($v, 1) . '%'; @endphp
<h1>{{ $title }}</h1>
<div class="sub">{{ $subtitle }}</div>

@include('pdf._kpis', ['s' => $summary, 'people' => $summary['people']])
<div class="small muted">Attendance rate = days present ÷ (working days − days on leave). Punctuality = on-time days ÷ days present. Working days exclude weekends and public holidays. Half days (not seen leaving): {{ number_format($summary['half_days']) }}.</div>

@if ($departments->isEmpty())
    <div class="note">No attendance was recorded for this group in this period.</div>
@else
    @php
        $tone = fn ($r) => $r === null ? '#9b9b9b' : ($r < 85 ? '#b42318' : ($r < 95 ? '#b97b10' : '#17703f'));
        $ranked = $departments->sortByDesc(fn ($d) => $d->rate ?? -1)->values();
        $peak = max(1, max(array_map('array_sum', $daily) ?: [1]));
    @endphp

    @if (count($daily) > 1 && count($daily) <= 31)
        <h2>Day by day</h2>
        <table class="cols"><tr>
            @foreach ($daily as $date => $d)
                <td>
                    <div class="stack" style="height: 64px;">
                        <div style="height: {{ 64 - round(64 * array_sum($d) / $peak) }}px;"></div>
                        @foreach ([['on_leave', '#1a5aa0'], ['absent', '#b42318'], ['late', '#b97b10'], ['on_time', '#17703f']] as [$k, $colour])
                            @if ($d[$k] > 0)<div style="height: {{ max(1, round(64 * $d[$k] / $peak)) }}px; background: {{ $colour }};"></div>@endif
                        @endforeach
                    </div>
                    <div class="cl">{{ \Carbon\Carbon::parse($date)->format('j') }}<br>{{ \Carbon\Carbon::parse($date)->format('D')[0] }}</div>
                </td>
            @endforeach
        </tr></table>
        <div class="small muted">Each column is one working day: on time, late, absent and on leave, bottom to top, scaled to the busiest day ({{ $peak }} staff).</div>
    @endif

    <h2 style="page-break-before: always">Highlights</h2>
    <table class="split"><tr>
        <td class="half" style="width:50%;padding-right:14px">
            <div class="h3">Attendance rate by department</div>
            <table class="bars">
                @foreach ($ranked as $d)
                    <tr>
                        <td class="bl">{{ \Illuminate\Support\Str::limit($d->name, 30) }}</td>
                        <td class="bt"><div class="track"><div style="width: {{ (float) $d->rate }}%; background: {{ $tone($d->rate) }};"></div></div></td>
                        <td class="bv" style="color: {{ $tone($d->rate) }}">{{ $pct($d->rate) }}</td>
                    </tr>
                @endforeach
            </table>
            <div class="small muted">Green 95% and above · amber 85–95% · red below 85%.</div>
        </td>
        <td class="half" style="width:50%;padding-left:14px">
            <div class="h3">Lowest attendance</div>
            <table class="mini">
                @forelse ($lowest as $p)
                    <tr><td>{{ $p->user->name }}<span class="muted"> · {{ \Illuminate\Support\Str::limit($p->department, 26) }}</span></td>
                        <td class="r">{{ $p->absent }} absent</td><td class="r" style="width:52px;color: {{ $tone($p->rate) }}"><b>{{ $pct($p->rate) }}</b></td></tr>
                @empty
                    <tr><td class="muted">Nobody to report.</td></tr>
                @endforelse
            </table>
            <div class="h3" style="margin-top:10px">Most late arrivals</div>
            <table class="mini">
                @forelse ($latest as $p)
                    <tr><td>{{ $p->user->name }}<span class="muted"> · {{ \Illuminate\Support\Str::limit($p->department, 26) }}</span></td>
                        <td class="r">{{ $p->late }} {{ \Illuminate\Support\Str::plural('time', $p->late) }}</td><td class="r" style="width:52px">{{ \App\Services\Reports::hm($p->late_minutes / 60) }}</td></tr>
                @empty
                    <tr><td class="muted">No late arrivals in this period.</td></tr>
                @endforelse
            </table>
        </td>
    </tr></table>

    <h2 style="page-break-before: always">By department</h2>
    <table class="t">
        <thead>
        <tr>
            <th style="width:62px">Staff no.</th><th>Name</th><th>Job title</th>
            <th class="r">Working days</th><th class="r">Present</th><th class="r">Late</th><th class="r">Absent</th>
            <th class="r">On leave</th><th class="r">Half days</th><th class="r">Late (min)</th><th class="r">Avg arrival</th><th class="r">Rate</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($departments as $d)
            <tr class="group"><td colspan="12"><b>{{ $d->name }}</b> &nbsp;·&nbsp; {{ $d->staff }} staff &nbsp;·&nbsp; attendance {{ $pct($d->rate) }}</td></tr>
            @foreach ($d->rows as $p)
                <tr>
                    <td>{{ $p->user->employee_no ?: '–' }}</td>
                    <td>{{ $p->user->name }}</td>
                    <td class="muted">{{ \Illuminate\Support\Str::limit($p->user->position, 34) }}</td>
                    <td class="r">{{ $p->working }}</td><td class="r">{{ $p->present }}</td>
                    <td class="r">{{ $p->late }}</td><td class="r">{{ $p->absent }}</td>
                    <td class="r">{{ $p->on_leave }}</td><td class="r">{{ $p->half_days }}</td>
                    <td class="r">{{ number_format($p->late_minutes) }}</td><td class="r">{{ $p->avg_arrival ?: '–' }}</td>
                    <td class="r"><b>{{ $pct($p->rate) }}</b></td>
                </tr>
            @endforeach
        @endforeach
        <tr class="total">
            <td colspan="3">All {{ $summary['people'] }} staff</td>
            <td class="r">{{ number_format($summary['working']) }}</td><td class="r">{{ number_format($summary['present']) }}</td>
            <td class="r">{{ number_format($summary['late']) }}</td><td class="r">{{ number_format($summary['absent']) }}</td>
            <td class="r">{{ number_format($summary['on_leave']) }}</td><td class="r">{{ number_format($summary['half_days']) }}</td>
            <td class="r">{{ number_format($summary['late_minutes']) }}</td><td class="r"></td>
            <td class="r">{{ $pct($summary['rate']) }}</td>
        </tr>
        </tbody>
    </table>
@endif
@include('pdf._pagenum')
</body></html>
