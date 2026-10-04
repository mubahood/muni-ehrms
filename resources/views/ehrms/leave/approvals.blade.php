@php
    $verbs = ['hod' => 'Recommend', 'dean' => 'Recommend', 'hr' => 'Verify and forward', 'us' => 'Approve leave'];
@endphp
<div class="ehr">
    <div class="ehr-panel accent">
        <div class="ehr-panel-head">
            <h3>Waiting for you · <span data-approvals-count>{{ $waiting->count() }}</span></h3>
            <span class="hint hide-xs">oldest first · decide here or open a request for its full trail</span>
        </div>
        <div class="ehr-panel-body flush ehr-table-wrap">
            <table class="ehr-table" id="approvals-table">
                <thead><tr><th>Reference</th><th>Employee</th><th class="hide-xs">Leave</th><th class="hide-xs">Dates</th><th class="r">Days</th><th class="hide-xs">As</th><th class="hide-xs">Waiting</th><th></th></tr></thead>
                <tbody>
                @foreach ($waiting as $l)
                    <tr id="leave-row-{{ $l->id }}">
                        <td style="white-space:nowrap"><a class="row-link" href="{{ admin_url('leave/' . $l->id) }}">{{ $l->reference }}</a></td>
                        <td>{{ $l->user->name }}<span class="cell-sub">{{ collect([$l->user->position, optional($l->user->department)->name])->filter()->implode(' · ') }}</span></td>
                        <td class="hide-xs">{{ $l->typeLabel() }}</td>
                        <td class="hide-xs" style="white-space:nowrap">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M Y') }}</td>
                        <td class="r"><b>{{ $l->days }}</b></td>
                        <td class="hide-xs">{{ $l->stageLabel() }}</td>
                        <td class="ehr-muted hide-xs" style="white-space:nowrap">{{ optional($l->submitted_at)->diffForHumans(null, true) }}</td>
                        <td class="r">
                            <button type="button" class="btn btn-primary btn-sm js-decide"
                                    data-id="{{ $l->id }}" data-url="{{ admin_url('leave/' . $l->id) }}" data-verb="{{ $verbs[$l->stage] ?? 'Approve' }}"
                                    data-name="{{ $l->user->name }}"
                                    data-summary="{{ $l->reference }} · {{ $l->typeLabel() }} · {{ $l->days }} day{{ $l->days === 1 ? '' : 's' }} · {{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M Y') }}"
                                    data-reason="{{ $l->reason }}">Decide</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="ehr-empty" id="approvals-empty" @if ($waiting->isNotEmpty()) style="display:none" @endif>
                <i class="fa fa-check-circle-o"></i>Nothing is waiting for you. New requests appear here and under the bell.
            </div>
        </div>
    </div>

    <div class="ehr-panel">
        <div class="ehr-panel-head"><h3>Your recent decisions</h3></div>
        <div class="ehr-panel-body flush">
            @forelse ($recent as $a)
                @if ($loop->first)<ul class="ehr-list">@endif
                <li>
                    <span class="who"><b><a href="{{ admin_url('leave/' . $a->leave_id) }}" style="color:inherit">{{ optional($a->leave)->reference }} · {{ optional(optional($a->leave)->user)->name }}</a></b>
                        <span>{{ optional($a->leave)->typeLabel() }}{{ $a->comment ? ' · “' . \Illuminate\Support\Str::limit($a->comment, 80) . '”' : '' }}</span></span>
                    <span style="text-align:right;white-space:nowrap"><span class="st st-{{ $a->action === 'rejected' ? 'rejected' : 'approved' }}">{{ $a->label() }}</span>
                        <span class="cell-sub">{{ $a->created_at->format('d M Y') }}</span></span>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <div class="ehr-empty">You have not decided on any requests yet.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="modal fade" id="m-decide" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form class="modal-content" method="post" data-ajax>
            @csrf
            <input type="hidden" name="stay" value="1">
            <div class="modal-header"><h4 class="modal-title"><span class="js-d-name"></span><small class="js-d-summary"></small></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
            <div class="modal-body">
                <div class="ehr-note" style="margin-bottom:10px"><b>Reason given:</b> <span class="js-d-reason"></span></div>
                <label class="ehr-label" for="decide-comment">Comment <span class="ehr-muted" style="font-weight:400">(required if you do not approve)</span></label>
                <textarea id="decide-comment" name="comment" rows="3" class="form-control"></textarea>
            </div>
            <div class="modal-footer">
                <a class="ehr-modal-hint js-d-open" href="#">Open the full request</a>
                <button type="submit" class="btn btn-danger js-d-reject"><i class="fa fa-times"></i> Do not approve</button>
                <button type="submit" class="btn btn-primary js-d-approve"><i class="fa fa-check"></i> <span class="js-d-verb"></span></button>
            </div>
        </form>
    </div>
</div>

<script>
$(function () {
    var $m = $('#m-decide');
    $(document).off('click.decide').on('click.decide', '.js-decide', function () {
        var d = this.dataset;
        $m.find('.js-d-name').text(d.name);
        $m.find('.js-d-summary').text(d.summary);
        $m.find('.js-d-reason').text(d.reason || '—');
        $m.find('.js-d-verb').text(d.verb);
        $m.find('.js-d-open').attr('href', d.url);
        $m.find('.js-d-approve').attr('formaction', d.url + '/approve');
        $m.find('.js-d-reject').attr('formaction', d.url + '/reject');
        $m.find('form').attr('data-remove', '#leave-row-' + d.id).find('textarea').val('');
        $m.modal('show');
    });
    $(document).off('ehr:removed.decide').on('ehr:removed.decide', function () {
        if (!$('#approvals-table tbody tr').length) { $('#approvals-table').hide(); $('#approvals-empty').show(); }
    });
    if (!$('#approvals-table tbody tr').length) { $('#approvals-table').hide(); }
});
</script>
