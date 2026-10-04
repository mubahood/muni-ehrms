<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $leave->reference }}</title>
@include('pdf._style')
<style>
    .form-title { text-align: center; font-size: 12pt; letter-spacing: .6pt; margin: 0 0 1px; }
    .section { text-align: center; font-size: 8.6pt; margin: 6px 0 2px; }
    .bar-title { background: #800000; color: #fff; text-align: center; font-size: 8pt; padding: 2px 0; margin-top: 4px; }
    table.f { width: 100%; border-collapse: collapse; }
    table.f td { padding: 2px 3px; font-size: 7.9pt; vertical-align: bottom; }
    .fill { border-bottom: .6px dotted #8a8a8a; }
    .lbl { white-space: nowrap; width: 1%; padding-right: 6px !important; }
    .box { display: inline-block; width: 9px; height: 9px; border: .7px solid #1a1a1a; text-align: center; font-size: 7pt; line-height: 9px; margin-right: 4px; }
    table.grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.grid td, table.grid th { border: .6px solid #9a9a9a; padding: 3px 5px; font-size: 7.8pt; text-align: left; font-weight: normal; vertical-align: top; }
    .status-tag { float: right; text-align: right; font-size: 7.2pt; color: #4a4a4a; }
    .ref { float: left; font-size: 7.2pt; color: #4a4a4a; }
</style>
</head>
<body>
@include('pdf._frame', ['footerNote' => $leave->reference . ' · Comments and approvals were given electronically; the full trail is kept in the EHRMS.'])
@php
    use App\Models\Leave;
    $u = $leave->user;
    $note = function ($act, $vacantText = 'Not applicable') use ($leave) {
        if ($act) {
            return ($act->action === 'skipped' ? $act->comment : ($act->comment ?: $act->label())) ;
        }

        return $leave->status === Leave::PENDING ? 'Awaiting comment' : $vacantText;
    };
    $isAcademic = optional($u->department)->isAcademic();
    $statusText = $leave->status === Leave::PENDING && $leave->stage
        ? 'IN PROGRESS – with the ' . $leave->stageLabel()
        : mb_strtoupper($leave->statusLabel());
    $sectionTwo = $leave->hr_days_due !== null;
@endphp

<table style="width:100%;border-collapse:collapse"><tr>
    <td style="width:30%;font-size:7.2pt;color:#4a4a4a;vertical-align:middle">Ref: {{ $leave->reference }}</td>
    <td style="text-align:center"><div class="form-title">LEAVE APPLICATION FORM</div></td>
    <td style="width:30%;font-size:7.2pt;color:#4a4a4a;text-align:right;vertical-align:middle">{{ $statusText }}</td>
</tr></table>

<div class="section">Section I: [Completed by the applicant]</div>
<table class="f">
    <tr><td class="lbl">To:</td><td class="fill" colspan="3">UNIVERSITY SECRETARY / ACCOUNTING OFFICER</td></tr>
    <tr><td class="lbl">Thru: Head of Department (Comment):</td><td class="fill" colspan="3">{{ $note($hod, $leave->source === Leave::SOURCE_HR ? 'Recorded by Human Resource' : 'Not applicable') }}</td></tr>
    <tr><td class="lbl">Date and signature:</td><td class="fill" colspan="3">{{ $hod && $hod->action !== 'skipped' ? $hod->created_at->format('d M Y') . ' — signed electronically by ' . $hod->actor_name : '' }}</td></tr>
    @if ($isAcademic)
        <tr><td class="lbl">Thru: Faculty Dean (Comment):</td><td class="fill" colspan="3">{{ $note($dean) }}</td></tr>
        <tr><td class="lbl">Date and signature:</td><td class="fill" colspan="3">{{ $dean && $dean->action !== 'skipped' ? $dean->created_at->format('d M Y') . ' — signed electronically by ' . $dean->actor_name : '' }}</td></tr>
    @endif
    <tr><td class="lbl">Name of the applicant:</td><td class="fill">{{ $u->name }}</td><td class="lbl">Designation:</td><td class="fill">{{ $u->position }}</td></tr>
    <tr><td class="lbl">Department / Unit / Section:</td><td class="fill" colspan="3">{{ optional($u->department)->name }}@if (optional(optional($u->department)->faculty)->name), {{ $u->department->faculty->name }}@endif @if ($u->employee_no) <span style="white-space:nowrap">(Staff no. {{ $u->employee_no }})</span>@endif</td></tr>
    <tr><td class="lbl">Name of the staff left to take charge:</td><td class="fill" colspan="3">{{ optional($leave->actingUser)->name }}</td></tr>
</table>

<div class="bar-title">Leave Request Information</div>
<table class="grid">
    <tr>
        <td style="width:50%">Date of last leave taken: {{ $last ? $last->start_date->format('d M Y') . ' to ' . $last->end_date->format('d M Y') : 'None recorded' }}</td>
        <td>Type of leave last taken: {{ $last ? $last->typeLabel() : '–' }}</td>
    </tr>
    <tr><td colspan="2">
        Specify the type of leave you are applying for, the dates on which to take it and the total leave days.<br>TYPE OF LEAVE:
        <table style="width:100%;margin-top:4px">
            @foreach (array_chunk(Leave::FORM_TYPES, 5) as $chunk)
                <tr>
                    @foreach ($chunk as $type)
                        @php $n = array_search($type, Leave::FORM_TYPES) + 1; @endphp
                        <td style="border:0;padding:2px 0;font-size:7.8pt;width:20%"><span class="box">{{ $leave->leave_type === $type ? 'X' : '' }}</span>{{ $n }}. {{ str_replace([' leave', ' of absence'], ['', ' Leave of Absence'], Leave::TYPES[$type]) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        @if (!in_array($leave->leave_type, Leave::FORM_TYPES, true))
            <div style="margin-top:3px">Recorded as: {{ $leave->typeLabel() }}</div>
        @endif
    </td></tr>
</table>
<table class="grid">
    <tr><th>Type of leave</th><th>No. of days applied for</th><th>From date</th><th>To date</th><th>Date of return</th></tr>
    <tr><td>{{ $leave->typeLabel() }}</td><td>{{ $leave->days }} working day(s)</td><td>{{ $leave->start_date->format('d M Y') }}</td><td>{{ $leave->end_date->format('d M Y') }}</td><td>{{ optional($leave->return_date)->format('d M Y') }}</td></tr>
</table>
<table class="f" style="margin-top:4px">
    <tr><td class="lbl">Contact address while on leave:</td><td class="fill">{{ $leave->contact_address }}</td><td class="lbl">Tel No.:</td><td class="fill">{{ $leave->contact_phone }}</td></tr>
    <tr><td class="lbl">Date and signature of applicant:</td><td class="fill" colspan="3">{{ optional($leave->submitted_at)->format('d M Y') }} — {{ $submitted && $submitted->action === 'submitted' ? 'submitted electronically by ' . $u->name : 'recorded by Human Resource' }}</td></tr>
    <tr><td class="lbl">Reason / details:</td><td class="fill" colspan="3">{{ $leave->reason }}</td></tr>
</table>

<div class="section">Section II: [To be computed by Human Resource Department]</div>
<div class="bar-title">Computation of leave</div>
<table class="f">
    @php
        $due = $sectionTwo ? $leave->hr_days_due : optional($balance)->daysDue;
        $cf = $sectionTwo ? $leave->hr_carried_forward : optional($balance)->carriedForward;
        $taken = $sectionTwo ? $leave->hr_days_taken : optional($balance)->taken;
        $bal = $sectionTwo ? $leave->hr_balance : ($balance ? $balance->balance() : null);
    @endphp
    <tr><td style="width:62%">a) Leave days due in a year ({{ $leave->leave_year ? \App\Services\LeaveRules::yearLabel($leave->leave_year) : '–' }})</td><td class="fill">{{ $due ?? '–' }}</td></tr>
    <tr><td>b) Add: leave days carried forward</td><td class="fill">{{ $cf ?? '–' }}</td></tr>
    <tr><td>c) Less: leave days taken</td><td class="fill">{{ $taken ?? '–' }}</td></tr>
    <tr><td>d) Leave days' balance @if (!$leave->isAnnual())<span class="muted small">(annual leave, for reference – {{ strtolower($leave->typeLabel()) }} is not deducted)</span>@endif</td><td class="fill"><b>{{ $bal ?? '–' }}</b></td></tr>
</table>
<table class="f">
    <tr><td class="lbl">Comments by HR:</td><td class="fill" colspan="3">{{ $hr ? ($hr->comment ?: $hr->label()) : ($leave->status === Leave::PENDING ? 'Awaiting Human Resource' : ($leave->source === Leave::SOURCE_HR ? 'Recorded by Human Resource' : '')) }}</td></tr>
    <tr><td class="lbl">Computed by:</td><td class="fill">{{ optional($hr)->actor_name }}</td><td class="lbl">Designation:</td><td class="fill">{{ optional($hr)->actor_title }}</td></tr>
    <tr><td class="lbl">Signature:</td><td class="fill">{{ $hr ? 'Signed electronically' : '' }}</td><td class="lbl">Date:</td><td class="fill">{{ $hr ? $hr->created_at->format('d M Y') : '' }}</td></tr>
</table>

<div class="section">Section III: [To be completed by the University Secretary]</div>
<div class="bar-title">Approval</div>
@php
    $decision = $us
        ? ($us->action === 'rejected' ? 'NOT APPROVED' : 'APPROVED')
        : ($leave->status === Leave::REJECTED ? 'NOT APPROVED (at an earlier stage)' : ($leave->source === Leave::SOURCE_HR ? 'APPROVED (recorded by Human Resource)' : 'awaiting decision'));
@endphp
<table class="f">
    <tr><td colspan="4">Your application for leave from {{ $leave->start_date->format('d F Y') }} to {{ $leave->end_date->format('d F Y') }} is <b>{{ $decision }}</b>.</td></tr>
    <tr><td class="lbl">Reason for approval / disapproval:</td><td class="fill" colspan="3">{{ $us ? $us->comment : optional($leave->actions->firstWhere('action', 'rejected'))->comment }}</td></tr>
    <tr><td class="lbl">Date:</td><td class="fill">{{ $us ? $us->created_at->format('d M Y') : '' }}</td><td class="lbl"></td><td style="text-align:center">{{ $us ? 'Signed electronically by ' . $us->actor_name : '' }}<br>UNIVERSITY SECRETARY</td></tr>
</table>
@if ($leave->status === Leave::RECALLED)
    <div class="note">Recalled from leave: resumed duty on {{ $leave->recall_date->format('l j F Y') }}; {{ $leave->days_restored }} working day(s) restored. Reason: {{ $leave->recall_reason }}</div>
@endif
@if ($leave->status === Leave::CANCELLED)
    <div class="note">This leave was cancelled before it started; all days were returned to the balance.</div>
@endif
@include('pdf._pagenum')
</body></html>
