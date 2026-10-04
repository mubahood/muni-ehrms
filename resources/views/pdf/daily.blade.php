<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>@include('pdf._style')</head>
<body>
@include('pdf._frame')
<h1>{{ $title }}</h1>
<div class="sub">{{ $subtitle }}@if ($holiday) · Public holiday: {{ $holiday }}@endif</div>
@include('pdf._kpis', ['s' => $summary])

@if ($groups->isEmpty())
    <div class="note">Nobody in this group was expected or recorded on this day.</div>
@else
    <table class="t">
        <thead><tr><th style="width:62px">Staff no.</th><th>Name</th><th>Job title</th><th>Status</th><th class="r">Arrived</th><th class="r">Left</th><th class="r">Late by</th></tr></thead>
        <tbody>
        @foreach ($groups as $department => $people)
            <tr class="group"><td colspan="7"><b>{{ $department }}</b> &nbsp;·&nbsp; {{ $people->count() }} staff</td></tr>
            @foreach ($people as $p)
                @php $r = $p->record; @endphp
                <tr>
                    <td>{{ $p->user->employee_no ?: '–' }}</td>
                    <td>{{ $p->user->name }}</td>
                    <td class="muted">{{ \Illuminate\Support\Str::limit($p->user->position, 34) }}</td>
                    <td><span class="st st-{{ $r->statusKey() }}">{{ $r->status === 'Absent' && $date->isToday() ? 'Not in yet' : $r->statusLabel() }}</span>@if (!$r->is_working_day) <span class="muted small">day off</span>@endif</td>
                    <td class="r">{{ $r->check_in_time ? substr($r->check_in_time, 0, 5) : '–' }}</td>
                    <td class="r">{{ $r->check_out_time ? substr($r->check_out_time, 0, 5) : '–' }}</td>
                    <td class="r">{{ $r->late_minutes ? \App\Services\Reports::hm($r->late_minutes / 60) : '–' }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
@endif
@include('pdf._pagenum')
</body></html>
