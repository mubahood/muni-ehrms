@php
    $routeText = collect($route)->map(fn ($s) => \App\Models\Leave::STAGES[$s])->implode(' → ');
@endphp
<div class="ehr">
    <form method="post" action="{{ admin_url('my-leave') }}" id="apply-form" data-ajax novalidate>
        @csrf
        <div class="ehr-grid">
            <div class="span-8">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Type of leave</h3><span class="hint">Section I of the University leave form</span></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-types" role="radiogroup">
                            @foreach (\App\Models\Leave::FORM_TYPES as $i => $type)
                                <label class="{{ $type === 'annual' ? 'on' : '' }}">
                                    <input type="radio" name="leave_type" value="{{ $type }}" {{ $type === 'annual' ? 'checked' : '' }}>
                                    {{ $i + 1 }}. {{ \App\Models\Leave::TYPES[$type] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="ehr-panel-head" style="border-top:1px solid var(--line-soft)"><h3>Dates and details</h3></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-form">
                            <div><label for="start_date">First day of leave</label>
                                <input type="text" id="start_date" name="start_date" class="form-control" required data-date data-min="today" placeholder="Choose a date"></div>
                            <div><label for="end_date">Last day of leave</label>
                                <input type="text" id="end_date" name="end_date" class="form-control" required data-date data-min="today" placeholder="Choose a date"></div>
                            <div class="full"><label for="reason">Reason / details</label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" maxlength="1000" required placeholder="For example: annual rest with family in Koboko"></textarea></div>
                            <div class="full"><label for="acting_user_id">Staff left to take charge</label>
                                <select id="acting_user_id" name="acting_user_id" class="form-control" data-people data-colleagues="1" data-placeholder="Search a colleague in your department (optional)"></select>
                                <div class="hint">They are told once the leave is approved.</div></div>
                            <div><label for="contact_address">Contact address while on leave</label>
                                <input type="text" id="contact_address" name="contact_address" class="form-control" maxlength="255" placeholder="Town or address"></div>
                            <div><label for="contact_phone">Telephone while on leave</label>
                                <input type="tel" id="contact_phone" name="contact_phone" class="form-control" maxlength="40" value="{{ $me->phone_number }}"></div>
                        </div>
                    </div>
                    <div class="ehr-panel-foot ehr-actions" style="justify-content:space-between">
                        <span><i class="fa fa-random"></i>&nbsp; Goes to: <b style="color:var(--ink)">{{ $routeText }}</b></span>
                        <span class="ehr-actions">
                            <a href="{{ admin_url('my-leave') }}" class="btn btn-default">Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit application</button>
                        </span>
                    </div>
                </div>
            </div>

            <div class="span-4">
                <div class="ehr-panel accent">
                    <div class="ehr-panel-head"><h3>This request</h3><span class="hint">updates as you type</span></div>
                    <div class="ehr-panel-body">
                        <div id="live-empty" class="ehr-muted">Choose the dates to see the working days and your date of return.</div>
                        <div id="live-body" style="display:none">
                            <div style="display:flex;align-items:baseline;gap:6px">
                                <span id="live-days" style="font-size:30px;font-weight:800;letter-spacing:-.02em">0</span>
                                <span class="ehr-muted">working days</span>
                            </div>
                            <dl class="ehr-dl" style="grid-template-columns:1fr auto;margin-top:6px">
                                <dt>Date of return</dt><dd id="live-return">—</dd>
                                <dt>Leave year</dt><dd id="live-year">—</dd>
                                <dt id="live-after-label">Annual leave left after</dt><dd id="live-after">—</dd>
                            </dl>
                            <div id="live-errors" class="ehr-note bad" style="display:none;margin-top:8px"></div>
                        </div>
                    </div>
                    <div class="ehr-panel-head" style="border-top:1px solid var(--line-soft)"><h3>From your record</h3></div>
                    <div class="ehr-panel-body">
                        <dl class="ehr-dl" style="grid-template-columns:96px 1fr">
                            <dt>Name</dt><dd>{{ $me->displayName() }}</dd>
                            <dt>Designation</dt><dd>{{ $me->position ?: '—' }}</dd>
                            <dt>Department</dt><dd>{{ optional($me->department)->name ?: '—' }}</dd>
                            <dt>Last leave</dt><dd>@if ($last){{ $last->typeLabel() }}, {{ $last->start_date->format('d M') }} – {{ $last->end_date->format('d M Y') }}@else None recorded @endif</dd>
                            <dt>Annual left</dt><dd>{{ $balance->hasAllocation ? $balance->available() . ' of ' . $balance->total() . ' days' : 'Not allocated yet' }}</dd>
                        </dl>
                    </div>
                    <div class="ehr-panel-foot">Weekends and public holidays are not counted.</div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(function () {
    var form = document.getElementById('apply-form');
    if (!form) { return; }
    var url = @json(admin_url('my-leave/check'));
    var timer = null;

    function type() { var t = form.querySelector('input[name="leave_type"]:checked'); return t ? t.value : 'annual'; }

    function refresh() {
        var start = form.start_date.value, end = form.end_date.value;
        if (!start || !end) { return; }
        $.getJSON(url, { leave_type: type(), start_date: start, end_date: end }).done(function (r) {
            if (!r.ready) { return; }
            $('#live-empty').hide(); $('#live-body').show();
            $('#live-days').text(r.days);
            $('#live-return').text(r.return_date || '—');
            $('#live-year').text(r.year || '—');
            var annual = type() === 'annual';
            $('#live-after-label, #live-after').toggle(annual);
            $('#live-after').text(r.after === null ? '—' : r.after + ' days');
            var $box = $('#live-errors');
            if (r.errors && r.errors.length) { $box.html(r.errors.map(function (e) { return $('<div>').text(e).html(); }).join('<br>')).show(); }
            else { $box.hide(); }
        });
    }
    function soon() { clearTimeout(timer); timer = setTimeout(refresh, 200); }

    $(form).on('change', 'input[name="leave_type"]', soon);
    form.start_date.addEventListener('change', function () {
        var endPicker = form.end_date._flatpickr;
        if (endPicker) {
            endPicker.set('minDate', form.start_date.value || 'today');
            if (!form.end_date.value || form.end_date.value < form.start_date.value) { endPicker.setDate(form.start_date.value, false); }
        }
        soon();
    });
    form.end_date.addEventListener('change', soon);
});
</script>
