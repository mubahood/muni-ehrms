@php
    $roleNames = ['admin' => 'System Administrator', 'us' => 'University Secretary', 'hr' => 'Human Resource', 'dean' => 'Faculty Dean', 'hod' => 'Head of Department', 'employee' => 'Employee'];
    $topRole = function ($user) use ($roleNames) {
        $slugs = $user->roles->pluck('slug')->all();
        foreach (array_keys($roleNames) as $slug) {
            if (in_array($slug, $slugs, true)) {
                return [$slug, $roleNames[$slug]];
            }
        }

        return ['employee', 'Employee'];
    };
@endphp
<div class="ehr">

    <div class="ehr-note" style="margin-bottom:12px">
        <b>How the demo works.</b> The demo accounts live in their own small university: three faculties, fourteen departments and about seventy staff (fifteen of them with sign-ins) with
        three months of clock-ins and leave at every stage. They can see only each other, and they cannot change anything that affects the real
        university (settings, public holidays, system users, the terminals' data). Real staff never see them in dashboards, reports, approvals or
        notifications.@if ($weekly) The demo is rebuilt with fresh dates every Sunday night.@endif
    </div>

    <div class="ehr-kpis ehr-kpis-6">
        <div class="ehr-kpi k-brand"><div class="v">{{ $stats['accounts'] }}</div><div class="l">Demo staff</div><div class="s">{{ $stats['logins'] ?? 0 }} sign-ins · {{ $stats['faculties'] }} faculties · {{ $stats['departments'] }} departments</div></div>
        <div class="ehr-kpi k-present"><div class="v">{{ number_format($stats['clock_ins']) }}</div><div class="l">Simulated clock-ins</div><div class="s">topped up through the day</div></div>
        <div class="ehr-kpi k-present"><div class="v">{{ number_format($stats['attendance']) }}</div><div class="l">Attendance days</div></div>
        <div class="ehr-kpi k-leave"><div class="v">{{ $stats['leave'] }}</div><div class="l">Leave requests</div><div class="s">{{ $stats['pending'] }} awaiting a decision</div></div>
        <div class="ehr-kpi {{ $loginsOn ? 'k-present' : 'k-absent' }}"><div class="v" style="font-size:16px;padding-top:4px">{{ $loginsOn ? 'Shown' : 'Hidden' }}</div><div class="l">On the login page</div></div>
        <div class="ehr-kpi k-brand"><div class="v" style="font-size:16px;padding-top:4px">{{ $stats['built_at'] ? \Carbon\Carbon::parse($stats['built_at'])->format('d M H:i') : '—' }}</div><div class="l">Built</div></div>
    </div>

    <div class="ehr-grid">
        <div class="span-8">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Demo sign-in accounts</h3><span class="hint">password for all: <code>{{ $password }}</code></span></div>
                <div class="ehr-panel-body flush ehr-table-wrap">
                    @if ($accounts->isEmpty())
                        <div class="ehr-empty"><i class="fa fa-flask"></i>There is no demo data. Build it with the button on the right.</div>
                    @else
                        <table class="ehr-table" id="demo-accounts">
                            <thead><tr><th>Person</th><th>Username</th><th class="hide-xs">Role</th><th class="hide-xs">Department</th><th class="hide-xs">Last sign-in</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($accounts as $a)
                                @php [$slug, $label] = $topRole($a); $seen = $lastSeen[$a->id] ?? null; @endphp
                                <tr id="demo-row-{{ $a->id }}">
                                    <td><span class="ehr-person"><span class="ehr-av">{{ $a->initials() }}</span><span>{{ $a->name }}<span class="cell-sub">{{ $a->position }}</span></span></span></td>
                                    <td><code>{{ $a->username }}</code></td>
                                    <td class="hide-xs"><span class="ehr-role r-{{ $slug }}">{{ $label }}</span></td>
                                    <td class="hide-xs">{{ optional($a->department)->name }}</td>
                                    <td class="hide-xs ehr-muted">{{ $seen ? \Carbon\Carbon::parse($seen->at)->diffForHumans() . ' · ' . $seen->n . '×' : 'never' }}</td>
                                    <td class="r">
                                        <form method="post" action="{{ admin_url('demo-data/accounts/' . $a->id) }}" data-ajax data-remove="#demo-row-{{ $a->id }}"
                                              data-confirm="Delete {{ $a->name }} ({{ $a->username }}) and all of their demo attendance and leave?" data-danger data-confirm-ok="Delete account">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-default btn-sm" title="Delete this demo account"><i class="fa fa-trash-o"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="span-4">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Explore it yourself</h3></div>
                <div class="ehr-panel-body">
                    <p class="ehr-small" style="margin:0 0 10px">Open the demo as its System Administrator without signing out; <b>Back to my account</b> in the header returns you here.</p>
                    <form method="post" action="{{ admin_url('demo-data/enter') }}">@csrf
                        <button type="submit" class="btn btn-primary btn-block" @if ($accounts->isEmpty()) disabled @endif><i class="fa fa-flask"></i>&nbsp; Explore the demo</button></form>
                </div>
            </div>
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Login page</h3></div>
                <div class="ehr-panel-body">
                    <p class="ehr-small" style="margin:0 0 10px">When shown, the login page offers <b>Try a demo account</b>: a window listing every demo account by role; one click signs in.</p>
                    <form method="post" action="{{ admin_url('demo-data/logins') }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $loginsOn ? 0 : 1 }}">
                        <button type="submit" class="btn {{ $loginsOn ? 'btn-default' : 'btn-primary' }} btn-block" @if ($accounts->isEmpty() && !$loginsOn) disabled @endif>
                            <i class="fa {{ $loginsOn ? 'fa-eye-slash' : 'fa-eye' }}"></i>&nbsp; {{ $loginsOn ? 'Hide demo sign-ins' : 'Show demo sign-ins on the login page' }}
                        </button>
                    </form>
                </div>
            </div>
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Rebuild or delete</h3></div>
                <div class="ehr-panel-body">
                    <form method="post" action="{{ admin_url('demo-data/rebuild') }}" style="margin-bottom:10px"
                          data-confirm="Replace the demo data with a fresh build (three months up to today)? Anything visitors did in the demo is discarded." data-confirm-ok="Rebuild">
                        @csrf
                        <button type="submit" class="btn btn-default btn-block"><i class="fa fa-refresh"></i>&nbsp; {{ $accounts->isEmpty() ? 'Build the demo data' : 'Rebuild with fresh dates' }}</button>
                    </form>
                    <form method="post" action="{{ admin_url('demo-data/purge') }}"
                          data-confirm="Delete every demo account and all demo attendance, leave, departments and faculties? Real staff and records are not touched." data-danger data-confirm-ok="Delete all demo data">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-block" @if ($accounts->isEmpty()) disabled @endif><i class="fa fa-trash"></i>&nbsp; Delete all demo data</button>
                    </form>
                    <p class="ehr-muted ehr-small" style="margin:10px 0 0">Deleting also hides the demo sign-ins. Every change here is recorded in the audit log.</p>
                </div>
            </div>
        </div>
    </div>
</div>
