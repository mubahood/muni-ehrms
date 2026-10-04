<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title }}</title>@include('pdf._style')</head>
<body>
@include('pdf._frame')
<h1>{{ $title }}</h1>
<div class="sub">{{ $subtitle }}</div>

<table class="kpis"><tr>
    <td class="k-brand"><div class="v">{{ $leaves->count() }}</div><div class="l">Requests</div></td>
    <td class="k-present"><div class="v">{{ $leaves->whereIn('status', ['approved', 'recalled'])->count() }}</div><div class="l">Approved</div></td>
    <td class="k-late"><div class="v">{{ $leaves->where('status', 'pending')->count() }}</div><div class="l">Awaiting approval</div></td>
    <td class="k-absent"><div class="v">{{ $leaves->where('status', 'rejected')->count() }}</div><div class="l">Not approved</div></td>
    <td class="k-leave"><div class="v">{{ $byType->sum('days') }}</div><div class="l">Working days taken</div></td>
</tr></table>

<h2>By type of leave</h2>
@if ($byType->isEmpty())
    <div class="note">No leave falls in this period for this group.</div>
@else
    <table class="t">
        <thead><tr><th>Type</th><th class="r">Requests</th><th class="r">Approved</th><th class="r">Awaiting</th><th class="r">Not approved</th><th class="r">Days taken</th></tr></thead>
        <tbody>
        @foreach ($byType as $t)
            <tr><td>{{ $t->label }}</td><td class="r">{{ $t->requests }}</td><td class="r">{{ $t->approved }}</td><td class="r">{{ $t->pending }}</td><td class="r">{{ $t->declined }}</td><td class="r">{{ $t->days }}</td></tr>
        @endforeach
        <tr class="total"><td>All types</td><td class="r">{{ $byType->sum('requests') }}</td><td class="r">{{ $byType->sum('approved') }}</td><td class="r">{{ $byType->sum('pending') }}</td><td class="r">{{ $byType->sum('declined') }}</td><td class="r">{{ $byType->sum('days') }}</td></tr>
        </tbody>
    </table>

    <h2>Requests</h2>
    <table class="t">
        <thead><tr><th>Reference</th><th>Employee</th><th>Department</th><th>Type</th><th>From</th><th>To</th><th class="r">Days</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($leaves as $l)
            <tr>
                <td>{{ $l->reference }}</td><td>{{ $l->user->name }}</td><td class="muted">{{ optional($l->user->department)->name }}</td>
                <td>{{ $l->typeLabel() }}</td><td>{{ $l->start_date->format('d M Y') }}</td><td>{{ $l->end_date->format('d M Y') }}</td>
                <td class="r">{{ $l->days }}</td>
                <td>{{ $l->statusLabel() }}@if ($l->status === 'pending' && $l->stage) <span class="muted small">({{ $l->stageLabel() }})</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Annual leave balances · {{ \App\Services\LeaveRules::yearLabel($year) }}</h2>
<table class="t">
    <thead><tr><th>Employee</th><th>Department</th><th class="r">Days due</th><th class="r">Carried fwd</th><th class="r">Taken</th><th class="r">Awaiting</th><th class="r">Balance</th></tr></thead>
    <tbody>
    @foreach ($people as $p)
        <tr>
            <td>{{ $p->user->name }}</td><td class="muted">{{ optional($p->user->department)->name }}</td>
            @if ($p->balance->hasAllocation)
                <td class="r">{{ $p->balance->daysDue }}</td><td class="r">{{ $p->balance->carriedForward }}</td>
                <td class="r">{{ $p->balance->taken }}</td><td class="r">{{ $p->balance->pending }}</td><td class="r"><b>{{ $p->balance->balance() }}</b></td>
            @else
                <td class="r muted" colspan="5">No allocation</td>
            @endif
        </tr>
    @endforeach
    </tbody>
</table>
@include('pdf._pagenum')
</body></html>
