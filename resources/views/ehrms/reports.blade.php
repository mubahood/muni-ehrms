@php
    $periods = [
        'this_month' => 'This month', 'last_month' => 'Last month', 'this_week' => 'This week',
        'last_week' => 'Last week', 'last_30' => 'Last 30 days', 'this_year' => 'This year to date',
        'leave_year' => 'Leave year ' . \App\Models\SystemConfiguration::current()->leaveYearLabel(\App\Models\SystemConfiguration::current()->leaveYearOf(today())) . ' to date',
        'custom' => 'Choose dates…',
    ];
@endphp
<div class="ehr">
    <div class="ehr-grid">
        @foreach ([
            ['summary', 'Attendance summary', 'Staff, present, late, absent and on leave for a group, with each person\'s attendance rate, grouped by department.', 'fa-table', 'landscape'],
            ['individual', 'Individual attendance', 'One person, day by day: first and last seen, hours worked (half days marked), lateness, and their leave.', 'fa-user', 'portrait'],
            ['daily', 'Daily register', 'Everyone in a group on one day: arrival, departure and status, grouped by department.', 'fa-calendar-o', 'portrait'],
            ['leave', 'Leave report', 'Requests by type and status for a period, and every person\'s annual leave balance.', 'fa-plane', 'landscape'],
        ] as [$type, $title, $text, $icon, $paper])
            <div class="span-6">
                <form class="ehr-panel" method="get" action="{{ admin_url('reports/' . $type . '.pdf') }}" target="_blank" style="display:block;height:100%">
                    <div class="ehr-panel-head"><h3><i class="fa {{ $icon }}" style="color:var(--maroon);margin-right:6px"></i>{{ $title }}</h3><span class="ehr-small">PDF · A4 {{ $paper }}</span></div>
                    <div class="ehr-panel-body">
                        <p class="ehr-muted" style="margin-top:0">{{ $text }}</p>
                        <div class="ehr-form">
                            @if ($type === 'individual')
                                <div class="full"><label>Employee</label>
                                    <select name="user" class="form-control" required data-people data-placeholder="Search by name or staff number">
                                        <option value="{{ Admin::user()->id }}" selected>{{ Admin::user()->displayName() }}</option>
                                    </select></div>
                            @else
                                <div class="full"><label>Group</label>
                                    <select name="scope" class="form-control">
                                        @foreach ($scopes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select></div>
                            @endif
                            @if ($type === 'daily')
                                <div class="full"><label>Day</label><input type="text" name="date" class="form-control" data-date data-max="today" value="{{ $lastWorkingDay->toDateString() }}"></div>
                            @else
                                <div><label>Period</label>
                                    <select name="period" class="form-control js-period">
                                        @foreach ($periods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                    </select></div>
                                <div class="js-dates" style="display:none">
                                    <label>From – to</label>
                                    <div style="display:flex;gap:6px">
                                        <input type="text" name="from" class="form-control" data-date data-max="today" value="{{ today()->startOfMonth()->toDateString() }}">
                                        <input type="text" name="to" class="form-control" data-date data-max="today" value="{{ today()->toDateString() }}">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="ehr-panel-foot ehr-actions" style="justify-content:flex-end">
                        <button class="btn btn-default" formaction="{{ admin_url('reports/' . $type . '.csv') }}" formtarget="_self" title="Download the same figures as a spreadsheet"><i class="fa fa-file-excel-o"></i>&nbsp; Excel (CSV)</button>
                        <button class="btn btn-primary"><i class="fa fa-file-pdf-o"></i>&nbsp; Open PDF</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
    <p class="ehr-muted ehr-small" style="margin-top:6px">Reports only include the people you are responsible for, and every download is recorded in the audit log.</p>
</div>
<script>
$(function () {
    $('.js-period').each(function () {
        var select = this, dates = $(select).closest('.ehr-form').find('.js-dates')[0];
        var sync = function () {
            var custom = select.value === 'custom';
            dates.style.display = custom ? '' : 'none';
            $(dates).find('input').prop('disabled', !custom);
        };
        select.addEventListener('change', sync);
        sync();
    });
});
</script>
