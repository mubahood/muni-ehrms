@php
    use App\Models\Leave;
    $applicant = $leave->user;
    $verb = $leave->stage ? [
        Leave::STAGE_HOD => 'Recommend', Leave::STAGE_DEAN => 'Recommend',
        Leave::STAGE_HR => 'Verify and forward', Leave::STAGE_US => 'Approve leave',
    ][$leave->stage] : null;
    $hrFrozen = $leave->hr_days_due !== null;
    $url = admin_url('leave/' . $leave->id);
    $summary = $leave->typeLabel() . ' · ' . $leave->days . ' working day' . ($leave->days === 1 ? '' : 's') . ' · '
        . $leave->start_date->format('d M') . ' – ' . $leave->end_date->format('d M Y');
@endphp
<div class="ehr">

    {{-- Status and actions --}}
    <div class="ehr-toolbar">
        <div class="ehr-actions">
            <span class="st st-{{ $leave->status }}" style="font-size:12px;padding:4px 10px">{{ $leave->statusLabel() }}</span>
            @if ($leave->status === Leave::PENDING && $leave->stage)
                <span class="ehr-muted">with the <b style="color:var(--ink)">{{ $leave->stageLabel() }}</b>@if ($leave->submitted_at) · submitted {{ $leave->submitted_at->format('d M Y') }}@endif</span>
            @elseif ($leave->decided_at)
                <span class="ehr-muted">decided {{ $leave->decided_at->format('d M Y') }}</span>
            @endif
        </div>
        <div class="ehr-actions">
            @if ($canAct)
                <button class="btn btn-danger" data-toggle="modal" data-target="#m-reject"><i class="fa fa-times"></i> Do not approve</button>
                <button class="btn btn-primary" data-toggle="modal" data-target="#m-approve"><i class="fa fa-check"></i> {{ $verb }}</button>
            @endif
            @if ($canWithdraw)
                <button class="btn btn-default" data-toggle="modal" data-target="#m-withdraw"><i class="fa fa-undo"></i> Withdraw</button>
            @endif
            @if ($canCancel)
                <button class="btn btn-danger" data-toggle="modal" data-target="#m-cancel"><i class="fa fa-ban"></i> Cancel leave</button>
            @endif
            @if ($canRecall)
                <button class="btn btn-default" data-toggle="modal" data-target="#m-recall"><i class="fa fa-reply"></i> Recall</button>
            @endif
            <a href="{{ $url }}/form.pdf" target="_blank" class="btn btn-default"><i class="fa fa-file-pdf-o"></i> Leave form</a>
        </div>
    </div>

    @if ($canAct)
        <div class="ehr-note" style="margin-bottom:12px">
            <b>This request is waiting for you</b> as {{ $leave->stageLabel() }}.
            @if ($leave->stage === Leave::STAGE_HR && $leave->isAnnual() && $balance)
                {{ $balance->available() }} annual day(s) are available for {{ $balance->label() }}; this request is for {{ $leave->days }}.
            @endif
        </div>
    @endif

    @if ($leave->routeList())
        <div class="ehr-steps" style="margin-bottom:16px">
            @foreach ($steps as $step)
                <div class="ehr-step s-{{ $step['state'] }}">
                    <div class="k">{{ $step['label'] }}</div>
                    @if ($step['key'] === 'applicant')
                        <div class="who">{{ $applicant->name }}</div>
                        <div class="when">Applied {{ optional(optional($step['action'])->created_at)->format('d M Y') }}</div>
                    @elseif ($step['state'] === 'done')
                        <div class="who">{{ $step['action']->actor_name }}</div>
                        <div class="when">{{ $step['action']->label() }} {{ $step['action']->created_at->format('d M Y') }}</div>
                    @elseif ($step['state'] === 'rejected')
                        <div class="who">{{ $step['action']->actor_name }}</div>
                        <div class="when" style="color:var(--absent)">Not approved {{ $step['action']->created_at->format('d M Y') }}</div>
                    @elseif ($step['state'] === 'current')
                        <div class="who">{{ $step['approvers']->isEmpty() ? 'No one holds this post yet' : $step['approvers']->pluck('name')->implode(', ') }}</div>
                        <div class="when">Waiting now</div>
                    @elseif ($step['state'] === 'skipped')
                        <div class="who">Passed on</div><div class="when">No one in this post</div>
                    @elseif ($step['state'] === 'waiting')
                        <div class="who">Next</div><div class="when">After the stage before</div>
                    @else
                        <div class="who">—</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($leave->status === Leave::RECALLED)
        <div class="ehr-note info" style="margin-bottom:16px">
            <b>Recalled from leave.</b> Resumed duty on {{ $leave->recall_date->format('l j F Y') }}; {{ $leave->days_restored }} working day(s) returned to the balance.
            Recalled by {{ optional($leave->recalledBy)->name }}: {{ $leave->recall_reason }}
        </div>
    @endif

    <div class="ehr-grid">
        <div class="span-8">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Section I · The application</h3>
                    @if ($leave->source === Leave::SOURCE_HR)<span class="hint">Recorded by Human Resource</span>@endif
                </div>
                <div class="ehr-panel-body">
                    <dl class="ehr-dl">
                        <dt>Applicant</dt><dd><b>{{ $applicant->name }}</b>@if ($applicant->employee_no) <span class="ehr-muted">· {{ $applicant->employee_no }}</span>@endif</dd>
                        <dt>Designation</dt><dd>{{ $applicant->position ?: '—' }}</dd>
                        <dt>Department</dt><dd>{{ optional($applicant->department)->name ?: '—' }}@if (optional(optional($applicant->department)->faculty)->name) <span class="ehr-muted">· {{ $applicant->department->faculty->name }}</span>@endif</dd>
                        <dt>Type of leave</dt><dd>{{ $leave->typeLabel() }}</dd>
                        <dt>Dates</dt><dd>{{ $leave->start_date->format('D j M Y') }} – {{ $leave->end_date->format('D j M Y') }}</dd>
                        <dt>Working days</dt><dd><b>{{ $leave->days }}</b>@if ($leave->leave_year) <span class="ehr-muted">· leave year {{ \App\Services\LeaveRules::yearLabel($leave->leave_year) }}</span>@endif</dd>
                        <dt>Date of return</dt><dd>{{ optional($leave->return_date)->format('D j M Y') ?: '—' }}</dd>
                        <dt>Left in charge</dt><dd>{{ optional($leave->actingUser)->name ?: 'Not applicable' }}</dd>
                        <dt>Contact while away</dt><dd>{{ collect([$leave->contact_address, $leave->contact_phone])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt>Last leave taken</dt><dd>@if ($last){{ $last->typeLabel() }}, {{ $last->start_date->format('d M') }} – {{ $last->end_date->format('d M Y') }}@else None recorded @endif</dd>
                        <dt>Reason</dt><dd>{{ $leave->reason }}</dd>
                    </dl>
                </div>
                @if ($leave->isAnnual() && ($hrFrozen || $balance))
                    @php
                        $due = $hrFrozen ? $leave->hr_days_due : $balance->daysDue;
                        $cf = $hrFrozen ? $leave->hr_carried_forward : $balance->carriedForward;
                        $taken = $hrFrozen ? $leave->hr_days_taken : $balance->taken;
                        $bal = $hrFrozen ? $leave->hr_balance : $balance->balance();
                    @endphp
                    <div class="ehr-panel-head" style="border-top:1px solid var(--line-soft)"><h3>Section II · Computation of leave</h3>
                        <span class="hint">{{ $hrFrozen ? 'as verified by Human Resource' : 'current figures, not yet verified' }}</span></div>
                    <div class="ehr-kpis" style="border:0;margin:0">
                        <div class="ehr-kpi"><div class="v">{{ $due }}</div><div class="l">(a) Days due</div></div>
                        <div class="ehr-kpi"><div class="v">{{ $cf }}</div><div class="l">(b) Carried forward</div></div>
                        <div class="ehr-kpi"><div class="v">{{ $taken }}</div><div class="l">(c) Taken</div></div>
                        <div class="ehr-kpi k-brand"><div class="v">{{ $bal }}</div><div class="l">(d) Balance</div></div>
                        <div class="ehr-kpi {{ $bal - $leave->days < 0 ? 'k-absent' : 'k-present' }}"><div class="v">{{ $bal - $leave->days }}</div><div class="l">Left after this</div></div>
                    </div>
                @endif
            </div>

            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Trail</h3><span class="hint">every step, with comments</span></div>
                <div class="ehr-panel-body flush">
                    <ul class="ehr-trail">
                        @foreach ($leave->actions as $a)
                            <li>
                                <div class="when">{{ $a->created_at->format('d M Y') }}<br>{{ $a->created_at->format('H:i') }}</div>
                                <div class="what">
                                    <b>{{ $a->label() }}</b>@if ($a->stage)<span class="ehr-muted"> · {{ Leave::STAGES[$a->stage] ?? $a->stage }}</span>@endif
                                    <div class="by">{{ $a->actor_name }}@if ($a->actor_title) <span class="ehr-muted">— {{ $a->actor_title }}</span>@endif</div>
                                    @if ($a->comment)<blockquote>{{ $a->comment }}</blockquote>@endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="span-4">
            <div class="ehr-panel accent">
                <div class="ehr-panel-head"><h3>At a glance</h3><span class="hint">{{ $leave->reference }}</span></div>
                <div class="ehr-panel-body">
                    <dl class="ehr-dl" style="grid-template-columns:1fr auto">
                        <dt>Type</dt><dd>{{ $leave->typeLabel() }}</dd>
                        <dt>Working days</dt><dd><b>{{ $leave->days }}</b></dd>
                        <dt>Away</dt><dd>{{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M') }}</dd>
                        <dt>Back on</dt><dd>{{ optional($leave->return_date)->format('D d M') }}</dd>
                        @if ($leave->isAnnual() && $balance && $balance->hasAllocation)
                            <dt>Annual left now</dt><dd>{{ $balance->available() }} of {{ $balance->total() }}</dd>
                        @endif
                    </dl>
                    @if ($leave->isAnnual() && $balance && $balance->hasAllocation)
                        <div class="ehr-meter">
                            <span class="taken" style="width: {{ 100 * $balance->taken / max(1, $balance->total()) }}%"></span>
                            <span class="pending" style="width: {{ 100 * $balance->pending / max(1, $balance->total()) }}%"></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ---------------------------------------------------------- modals --}}
@if ($canAct)
    <div class="modal fade" id="m-approve" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document"><form class="modal-content" method="post" action="{{ $url }}/approve" data-ajax>
            @csrf
            <div class="modal-header"><h4 class="modal-title">{{ $verb }}<small>{{ $applicant->name }} · {{ $summary }}</small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                @if ($leave->stage === Leave::STAGE_HR && $leave->isAnnual() && $balance)
                    <div class="ehr-note {{ $leave->days > $balance->available() ? 'bad' : 'good' }}" style="margin-bottom:10px">
                        {{ $balance->available() }} annual day(s) available; this request is for {{ $leave->days }}. Verifying freezes Section II with these figures.
                    </div>
                @endif
                <label class="ehr-label" for="approve-comment">Comment <span class="ehr-muted" style="font-weight:400">(optional)</span></label>
                <textarea id="approve-comment" name="comment" rows="3" class="form-control" placeholder="For example: duties covered by Mr Ocen"></textarea>
            </div>
            <div class="modal-footer"><span class="ehr-modal-hint">The applicant is told at once.</span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> {{ $verb }}</button></div>
        </form></div>
    </div>
    <div class="modal fade ehr-danger" id="m-reject" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document"><form class="modal-content" method="post" action="{{ $url }}/reject" data-ajax>
            @csrf
            <div class="modal-header"><h4 class="modal-title">Do not approve<small>{{ $applicant->name }} · {{ $summary }}</small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <label class="ehr-label" for="reject-comment">Reason</label>
                <textarea id="reject-comment" name="comment" rows="3" class="form-control" required placeholder="The applicant sees this reason"></textarea>
            </div>
            <div class="modal-footer"><span class="ehr-modal-hint">This ends the request.</span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Do not approve</button></div>
        </form></div>
    </div>
@endif
@if ($canWithdraw)
    <div class="modal fade" id="m-withdraw" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document"><form class="modal-content" method="post" action="{{ $url }}/withdraw" data-ajax>
            @csrf
            <div class="modal-header"><h4 class="modal-title">Withdraw request<small>{{ $leave->reference }}</small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <label class="ehr-label" for="withdraw-comment">Reason <span class="ehr-muted" style="font-weight:400">(optional)</span></label>
                <textarea id="withdraw-comment" name="comment" rows="2" class="form-control"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Keep it</button>
                <button type="submit" class="btn btn-primary">Withdraw</button></div>
        </form></div>
    </div>
@endif
@if ($canCancel)
    <div class="modal fade ehr-danger" id="m-cancel" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document"><form class="modal-content" method="post" action="{{ $url }}/cancel" data-ajax>
            @csrf
            <div class="modal-header"><h4 class="modal-title">Cancel approved leave<small>{{ $applicant->name }} · {{ $summary }}</small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <p class="ehr-muted" style="margin-top:0">The leave has not started. All {{ $leave->days }} day(s) return to the balance and {{ $applicant->name }} is told.</p>
                <label class="ehr-label" for="cancel-comment">Reason</label>
                <textarea id="cancel-comment" name="comment" rows="2" class="form-control" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Keep the leave</button>
                <button type="submit" class="btn btn-danger">Cancel leave</button></div>
        </form></div>
    </div>
@endif
@if ($canRecall)
    <div class="modal fade" id="m-recall" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document"><form class="modal-content" method="post" action="{{ $url }}/recall" data-ajax>
            @csrf
            <div class="modal-header"><h4 class="modal-title">Recall from leave<small>{{ $applicant->name }} · {{ $summary }}</small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <div class="ehr-form" style="grid-template-columns:1fr">
                    <div><label for="resume_date">Resumes duty on</label>
                        <input type="text" id="resume_date" name="resume_date" class="form-control" required data-date
                               data-min="{{ $leave->start_date->copy()->addDay()->toDateString() }}" data-max="{{ $leave->end_date->toDateString() }}"
                               value="{{ $nextWorkingDay->min($leave->end_date)->toDateString() }}">
                        <div class="hint">Unused working days from this date return to the balance; attendance counts from it.</div></div>
                    <div><label for="recall-reason">Reason</label>
                        <textarea id="recall-reason" name="comment" rows="2" class="form-control" required></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><span class="ehr-modal-hint">The employee and their Head are told at once.</span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Recall employee</button></div>
        </form></div>
    </div>
@endif
