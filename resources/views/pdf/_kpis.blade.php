{{-- KPI strip and proportion bar for a summary $s from AttendanceStats. --}}
@php
    $pct = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
    $total = max(1, $s['on_time'] + $s['late'] + $s['absent'] + $s['on_leave']);
    $share = fn ($n) => round(100 * $n / $total, 1);
@endphp
<table class="kpis">
    <tr>
        @isset($people)<td class="k-brand"><div class="v">{{ $people }}</div><div class="l">Staff</div></td>@endisset
        <td class="k-brand"><div class="v">{{ number_format($s['working']) }}</div><div class="l">{{ !empty($single) ? 'Working days' : 'Working staff-days' }}</div></td>
        <td class="k-present"><div class="v">{{ number_format($s['present']) }}</div><div class="l">Present</div></td>
        <td class="k-late"><div class="v">{{ number_format($s['late']) }}</div><div class="l">Late arrivals</div></td>
        <td class="k-absent"><div class="v">{{ number_format($s['absent']) }}</div><div class="l">Absent</div></td>
        <td class="k-leave"><div class="v">{{ number_format($s['on_leave']) }}</div><div class="l">On leave</div></td>
        <td class="k-brand"><div class="v">{{ $pct($s['rate']) }}</div><div class="l">Attendance rate</div></td>
        <td class="k-brand"><div class="v">{{ $pct($s['punctuality']) }}</div><div class="l">Punctuality</div></td>
    </tr>
</table>
@if ($s['working'] > 0)
    <table class="bar"><tr>
        @foreach ([['on_time', '#17703f'], ['late', '#b97b10'], ['absent', '#b42318'], ['on_leave', '#1a5aa0']] as [$k, $colour])
            @if ($s[$k] > 0)<td style="width: {{ $share($s[$k]) }}%; background: {{ $colour }};"></td>@endif
        @endforeach
    </tr></table>
    <div class="legend">
        <span><span class="sw" style="background:#17703f"></span>On time {{ number_format($s['on_time']) }} ({{ $share($s['on_time']) }}%)</span>
        <span><span class="sw" style="background:#b97b10"></span>Late {{ number_format($s['late']) }} ({{ $share($s['late']) }}%)</span>
        <span><span class="sw" style="background:#b42318"></span>Absent {{ number_format($s['absent']) }} ({{ $share($s['absent']) }}%)</span>
        <span><span class="sw" style="background:#1a5aa0"></span>On leave {{ number_format($s['on_leave']) }} ({{ $share($s['on_leave']) }}%)</span>
    </div>
@endif
