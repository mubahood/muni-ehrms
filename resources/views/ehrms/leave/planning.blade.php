<div class="ehr">
    <form method="get" action="{{ admin_url('leave/planning') }}" class="ehr-toolbar">
        <div class="ehr-toolbar" style="margin:0">
            <div class="ehr-field"><label for="year">Leave year</label>
                <select id="year" name="year" class="form-control" onchange="this.form.submit()">
                    @foreach ($years as $y)<option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ \App\Services\LeaveRules::yearLabel($y) }}</option>@endforeach
                </select></div>
            <div class="ehr-field"><label for="department">Department</label>
                <select id="department" name="department" class="form-control" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" {{ (string) $departmentId === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>@endforeach
                </select></div>
        </div>
        @if ($unallocated)
            <span class="st st-pending" style="align-self:center">{{ $unallocated }} without an allocation</span>
        @endif
    </form>

    <div class="ehr-grid">
        <div class="span-8">
            <form method="post" action="{{ admin_url('leave/planning') }}">
                @csrf
                <input type="hidden" name="mode" value="rows">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="department" value="{{ $departmentId }}">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Allocations · {{ \App\Services\LeaveRules::yearLabel($year) }}</h3>
                        <button class="btn btn-primary btn-sm">Save changes</button></div>
                    <div class="ehr-panel-body flush ehr-table-wrap">
                        <table class="ehr-table">
                            <thead><tr><th>Employee</th><th class="r" style="width:96px">Days due</th><th class="r" style="width:110px">Carried fwd</th><th class="r">Total</th><th class="r">Taken</th><th class="r">Pending</th><th class="r">Available</th></tr></thead>
                            <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>{{ $r->user->name }}<span class="cell-sub">{{ optional($r->user->department)->name ?: 'No department' }}</span></td>
                                    <td class="r"><input type="number" min="0" max="365" name="due[{{ $r->user->id }}]" value="{{ optional($r->entitlement)->days_due }}" placeholder="—" class="form-control" style="width:80px;margin-left:auto;text-align:right"></td>
                                    <td class="r"><input type="number" min="0" max="365" name="carried[{{ $r->user->id }}]" value="{{ optional($r->entitlement)->carried_forward ?? 0 }}" class="form-control" style="width:80px;margin-left:auto;text-align:right"></td>
                                    <td class="r">{{ $r->entitlement ? $r->balance->total() : '—' }}</td>
                                    <td class="r">{{ $r->balance->taken }}</td>
                                    <td class="r">{{ $r->balance->pending }}</td>
                                    <td class="r"><b style="{{ $r->entitlement && $r->balance->available() <= 0 ? 'color:var(--absent)' : '' }}">{{ $r->entitlement ? $r->balance->available() : '—' }}</b></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ehr-panel-foot ehr-actions" style="justify-content:space-between">
                        <span>Total = days due + carried forward. Requests awaiting approval hold their days.</span>
                        <button class="btn btn-primary btn-sm">Save changes</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="span-4">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Set days due for a group</h3></div>
                <div class="ehr-panel-body">
                    <form method="post" action="{{ admin_url('leave/planning') }}">
                        @csrf
                        <input type="hidden" name="mode" value="bulk">
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="department" value="{{ $departmentId }}">
                        <div class="ehr-form" style="grid-template-columns:1fr">
                            <div><label for="bulk_department">Who</label>
                                <select id="bulk_department" name="bulk_department" class="form-control">
                                    <option value="">Everyone</option>
                                    @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                                </select></div>
                            <div><label for="bulk_days">Days due in {{ \App\Services\LeaveRules::yearLabel($year) }}</label>
                                <input type="number" id="bulk_days" name="bulk_days" min="0" max="120" value="{{ $defaultDays }}" class="form-control" required></div>
                            <label style="font-weight:500;display:flex;gap:8px;align-items:center"><input type="checkbox" name="bulk_only_missing" value="1" checked style="accent-color:var(--maroon)"> Only people without an allocation</label>
                            <button class="btn btn-default">Apply to the group</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Carry forward from {{ \App\Services\LeaveRules::yearLabel($year - 1) }}</h3></div>
                <div class="ehr-panel-body">
                    <p class="ehr-small ehr-muted" style="margin-top:0">Each person's unused balance from last year is carried forward, up to the limit. Days due are kept.</p>
                    <form method="post" action="{{ admin_url('leave/planning') }}" onsubmit="return confirm('Carry forward last year\'s unused balances for everyone?');">
                        @csrf
                        <input type="hidden" name="mode" value="carry">
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="department" value="{{ $departmentId }}">
                        <label class="ehr-small" style="font-weight:600" for="carry_cap">At most (days)</label>
                        <input type="number" id="carry_cap" name="carry_cap" min="0" max="120" value="15" class="form-control" required>
                        <button class="btn btn-default btn-block" style="margin-top:10px">Carry forward balances</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
