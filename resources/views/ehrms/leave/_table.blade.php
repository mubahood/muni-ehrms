{{-- A list of leave requests. $showPerson adds the employee column. --}}
<table class="ehr-table">
    <thead>
    <tr>
        <th>Reference</th>
        @if ($showPerson)<th>Employee</th>@endif
        <th>Leave</th>
        <th>Dates</th>
        <th class="r">Days</th>
        <th>Status</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($leaves as $l)
        <tr>
            <td><a class="row-link" href="{{ admin_url('leave/' . $l->id) }}">{{ $l->reference ?: '#' . $l->id }}</a>
                @if ($l->source === \App\Models\Leave::SOURCE_HR)<span class="cell-sub">recorded by HR</span>@endif</td>
            @if ($showPerson)
                <td>{{ optional($l->user)->name }}<span class="cell-sub">{{ optional(optional($l->user)->department)->name }}</span></td>
            @endif
            <td>{{ $l->typeLabel() }}</td>
            <td style="white-space:nowrap">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M Y') }}</td>
            <td class="r">{{ $l->days }}@if ($l->days_restored)<span class="cell-sub">{{ $l->days_restored }} restored</span>@endif</td>
            <td>
                <span class="st st-{{ $l->status }}">{{ $l->statusLabel() }}</span>
                @if ($l->status === \App\Models\Leave::PENDING && $l->stage)
                    <span class="cell-sub">with the {{ $l->stageLabel() }}</span>
                @endif
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
