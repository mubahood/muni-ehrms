@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    $working = old('working_days', $config->workingWeekdays());
    $v = fn ($k, $d = null) => old($k, $config->{$k} ?? $d);
@endphp
<div class="ehr">
    @php $readOnly = Admin::user()->isDemo(); @endphp
    @if ($readOnly)
        <div class="ehr-note warn" style="margin-bottom:12px"><b>Read-only in the demo.</b> These settings belong to the whole University, so demo accounts can look but not change them.</div>
    @endif
    <form method="post" action="{{ admin_url('settings') }}" novalidate>
    <fieldset @if ($readOnly) disabled @endif style="border:0;margin:0;padding:0;min-width:0">
        @csrf
        @method('put')
        @if ($errors->any())
            <div class="ehr-note bad" style="margin-bottom:18px" role="alert"><b>The settings were not saved.</b>
                <ul class="ehr-errors">@foreach ($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul></div>
        @endif
        <div class="ehr-grid">
            <div class="span-6">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>The institution</h3><span class="ehr-small">printed on letterheads and e-mails</span></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-form">
                            <div class="full"><label for="company_name">Name</label><input id="company_name" name="company_name" class="form-control" value="{{ $v('company_name') }}" required></div>
                            <div><label for="office_name">Office (leave form letterhead)</label><input id="office_name" name="office_name" class="form-control" value="{{ $v('office_name') }}"></div>
                            <div><label for="motto">Motto</label><input id="motto" name="motto" class="form-control" value="{{ $v('motto') }}"></div>
                            <div class="full"><label for="system_name">System name</label><input id="system_name" name="system_name" class="form-control" value="{{ $v('system_name') }}" required></div>
                            <div class="full"><label for="company_address">Postal address</label><input id="company_address" name="company_address" class="form-control" value="{{ $v('company_address') }}" required></div>
                            <div><label for="company_phone">Telephone</label><input id="company_phone" name="company_phone" class="form-control" value="{{ $v('company_phone') }}"></div>
                            <div><label for="company_fax">Fax</label><input id="company_fax" name="company_fax" class="form-control" value="{{ $v('company_fax') }}"></div>
                            <div><label for="company_email">E-mail</label><input id="company_email" type="email" name="company_email" class="form-control" value="{{ $v('company_email') }}"></div>
                            <div><label for="company_website">Website</label><input id="company_website" name="company_website" class="form-control" value="{{ $v('company_website') }}"></div>
                            <div class="full"><label for="report_footer">Report footer</label><input id="report_footer" name="report_footer" class="form-control" value="{{ $v('report_footer') }}"><div class="hint">Printed at the foot of every report.</div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="span-6">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Attendance rules</h3></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-form">
                            <div class="full"><label>Working days</label>
                                <div class="ehr-types" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                                    @foreach ($days as $n => $name)
                                        <label class="{{ in_array($n, $working) ? 'on' : '' }}"><input type="checkbox" name="working_days[]" value="{{ $n }}" {{ in_array($n, $working) ? 'checked' : '' }} onchange="this.parentNode.classList.toggle('on', this.checked)"> {{ substr($name, 0, 3) }}</label>
                                    @endforeach
                                </div>
                                <div class="hint">Staff with their own work days on their record keep those.</div></div>
                            <div><label for="late_time">Late after</label><input id="late_time" type="time" name="late_time" class="form-control" value="{{ substr($v('late_time'), 0, 5) }}" required><div class="hint">08:30 means 08:30:59 is on time and 08:31 is late.</div></div>
                            <div><label for="full_day_hours">Standard day (hours)</label><input id="full_day_hours" type="number" step="0.25" min="1" max="24" name="full_day_hours" class="form-control" value="{{ $v('full_day_hours') }}" required><div class="hint">A day without a departure is credited as half of this.</div></div>
                            <div><label for="repeat_capture_minutes">Repeat capture window (minutes)</label><input id="repeat_capture_minutes" type="number" min="0" max="240" name="repeat_capture_minutes" class="form-control" value="{{ $v('repeat_capture_minutes') }}" required><div class="hint">A capture this soon after arriving is not a departure.</div></div>
                            <div><label for="start_date">Attendance tracked from</label><input id="start_date" type="date" name="start_date" class="form-control" value="{{ $v('start_date') }}" required><div class="hint">No absences are recorded before this date.</div></div>
                        </div>
                    </div>
                </div>
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Leave rules</h3></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-form">
                            <div><label for="leave_year_start_month">Leave year starts in</label>
                                <select id="leave_year_start_month" name="leave_year_start_month" class="form-control">
                                    @foreach (range(1, 12) as $m)<option value="{{ $m }}" {{ (int) $v('leave_year_start_month') === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>@endforeach
                                </select><div class="hint">July = the financial year.</div></div>
                            <div><label for="annual_leave_days">Default annual leave (days)</label><input id="annual_leave_days" type="number" min="0" max="120" name="annual_leave_days" class="form-control" value="{{ $v('annual_leave_days') }}" required><div class="hint">Suggested in Leave planning.</div></div>
                        </div>
                    </div>
                    <div class="ehr-panel-foot ehr-actions" style="justify-content:space-between">
                        <span>Changing an attendance rule recalculates this month.</span>
                        @unless ($readOnly)<button class="btn btn-primary">Save settings</button>@endunless
                    </div>
                </div>
            </div>
        </div>
    </fieldset>
    </form>
</div>
