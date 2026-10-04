<div class="ehr">
    <form method="post" action="{{ admin_url('leave/record') }}" data-ajax novalidate>
        @csrf
        <div class="ehr-grid">
            <div class="span-8">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Leave to record</h3><span class="hint">saved as approved</span></div>
                    <div class="ehr-panel-body">
                        <div class="ehr-form">
                            <div class="full"><label for="user_id">Employee</label>
                                <select id="user_id" name="user_id" class="form-control" required data-people data-placeholder="Search by name or staff number"></select></div>
                            <div class="full"><label for="leave_type">Type of leave</label>
                                <select id="leave_type" name="leave_type" class="form-control" required>
                                    @foreach (\App\Models\Leave::TYPES as $k => $v)<option value="{{ $k }}" {{ $k === 'annual' ? 'selected' : '' }}>{{ $v }}</option>@endforeach
                                </select></div>
                            <div><label for="start_date">First day</label><input type="text" id="start_date" name="start_date" class="form-control" required data-date placeholder="Choose a date"></div>
                            <div><label for="end_date">Last day</label><input type="text" id="end_date" name="end_date" class="form-control" required data-date placeholder="Choose a date"></div>
                            <div class="full"><label for="reason">Reason / reference</label>
                                <textarea id="reason" name="reason" class="form-control" rows="2" required placeholder="For example: approved on paper, ref. MU/HR/2026/114"></textarea></div>
                        </div>
                    </div>
                    <div class="ehr-panel-foot ehr-actions" style="justify-content:flex-end">
                        <a href="{{ admin_url('leave/all') }}" class="btn btn-default">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-check"></i> Record as approved</button>
                    </div>
                </div>
            </div>
            <div class="span-4">
                <div class="ehr-note info">
                    <b>When to use this.</b> Leave approved on paper before the online process, and official duty or travel.
                    It counts against annual leave, and attendance for days already past is updated at once. Past dates are allowed.
                </div>
            </div>
        </div>
    </form>
</div>
