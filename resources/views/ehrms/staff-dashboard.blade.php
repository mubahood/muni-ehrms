@php
    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
    $hm = fn ($h) => \App\Admin\Controllers\AttendanceRecordController::hm($h);
    $s = $summary;
    $base = $isSelf ? admin_url('me') : admin_url('staff/' . $user->id);
    $prev = $month->copy()->subMonth()->format('Y-m');
    $next = $month->copy()->addMonth();
@endphp
<div class="ehr">

    <div class="ehr-toolbar">
        <div class="ehr-actions">
            <a class="btn btn-default" href="{{ $base }}?month={{ $prev }}" aria-label="Previous month"><i class="fa fa-chevron-left"></i></a>
            <strong style="font-size:15px;min-width:130px;text-align:center">{{ $month->format('F Y') }}</strong>
            @if ($next->lte(today()))
                <a class="btn btn-default" href="{{ $base }}?month={{ $next->format('Y-m') }}" aria-label="Next month"><i class="fa fa-chevron-right"></i></a>
            @else
                <span class="btn btn-default disabled"><i class="fa fa-chevron-right"></i></span>
            @endif
        </div>
        <div class="ehr-actions">
            @if ($isSelf)
                <a class="btn btn-default" href="{{ admin_url('my-leave/apply') }}"><i class="fa fa-plane"></i>&nbsp; Apply for leave</a>
            @endif
            <a class="btn btn-primary" href="{{ admin_url('reports/individual.pdf') }}?{{ http_build_query(['user' => $user->id, 'from' => $month->toDateString(), 'to' => $month->copy()->endOfMonth()->min(today())->toDateString()]) }}" target="_blank">
                <i class="fa fa-file-pdf-o"></i>&nbsp; Month report (PDF)
            </a>
        </div>
    </div>

    @if ($onLeaveNow)
        <div class="ehr-note" style="margin-bottom:18px;border-left-color:var(--leave)">
            <b>On {{ strtolower($onLeaveNow->typeLabel()) }}</b> until {{ $onLeaveNow->end_date->format('l j F') }} ·
            back on {{ optional($onLeaveNow->return_date)->format('l j F') }}.
            <a href="{{ admin_url('leave/' . $onLeaveNow->id) }}">View request</a>
        </div>
    @endif

    <div class="ehr-kpis">
        <div class="ehr-kpi k-brand"><div class="v">{{ $pct($s['rate']) }}</div><div class="l">Attendance rate</div><div class="s">{{ $s['present'] }} of {{ max(0, $s['working'] - $s['on_leave']) }} days</div></div>
        <div class="ehr-kpi k-present"><div class="v">{{ $pct($s['punctuality']) }}</div><div class="l">Punctuality</div><div class="s">{{ $s['on_time'] }} on time</div></div>
        <div class="ehr-kpi k-late"><div class="v">{{ $s['late'] }}</div><div class="l">Late arrivals</div><div class="s">{{ $s['late_minutes'] ? $hm($s['late_minutes'] / 60) . ' in total' : 'none' }}</div></div>
        <div class="ehr-kpi k-absent"><div class="v">{{ $s['absent'] }}</div><div class="l">Absent</div><div class="s">{{ $s['absent'] ? 'working days without a clock-in' : 'none' }}</div></div>
        <div class="ehr-kpi k-leave"><div class="v">{{ $s['on_leave'] }}</div><div class="l">On leave</div></div>
        <div class="ehr-kpi"><div class="v">{{ $avgArrival ?: '—' }}</div><div class="l">Average arrival</div><div class="s">late after {{ $lateTime }}</div></div>
    </div>

    <div class="ehr-grid">
        <div class="span-8">
            <div class="ehr-panel">
                <div class="ehr-panel-head">
                    <h3>Calendar</h3>
                    <span class="ehr-legend" style="margin:0">
                        <span><i style="background:var(--present)"></i>On time</span>
                        <span><i style="background:var(--late)"></i>Late</span>
                        <span><i style="background:var(--absent)"></i>Absent</span>
                        <span><i style="background:var(--leave)"></i>Leave</span>
                    </span>
                </div>
                <div class="ehr-cal">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow)
                        <div class="dow">{{ $dow }}</div>
                    @endforeach
                    @foreach ($days as $d)
                        @php
                            $r = $d['record'];
                            $cls = !$d['in_month'] ? 'out' : (!$d['working'] && !$r ? 'off' : '');
                            if ($r && $d['in_month']) { $cls .= ' d-' . $r->statusKey(); }
                            if ($d['date']->isToday()) { $cls .= ' today'; }
                            $tip = $d['date']->format('l j F');
                            if ($d['holiday']) { $tip .= ' · ' . $d['holiday']; }
                            if ($r) { $tip .= ' · ' . $r->statusLabel(); }
                        @endphp
                        <div class="day {{ $cls }}" title="{{ $tip }}">
                            <span class="n">{{ $d['date']->day }}</span>
                            @if ($r && $d['in_month'])<span class="tag"></span>@endif
                            @if ($d['in_month'])
                                @if ($r && $r->status === 'Present')
                                    <span class="t">{{ substr($r->check_in_time, 0, 5) }}@if ($r->check_out_time)–{{ substr($r->check_out_time, 0, 5) }}@endif</span>
                                @elseif ($r && $r->status === 'On Leave')
                                    <span class="t">Leave</span>
                                @elseif ($r && $r->status === 'Absent')
                                    <span class="t">{{ $d['date']->isToday() ? 'Not in yet' : 'Absent' }}</span>
                                @elseif ($d['holiday'])
                                    <span class="t" style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($d['holiday'], 16) }}</span>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="span-4">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Annual leave {{ $balance->label() }}</h3>
                    @if ($isSelf)<a class="ehr-small" href="{{ admin_url('my-leave') }}">My leave</a>@endif
                </div>
                <div class="ehr-panel-body">
                    @if (!$balance->hasAllocation)
                        <div class="ehr-note warn">No annual leave has been allocated for {{ $balance->label() }} yet. Human Resource sets it under Leave planning.</div>
                    @else
                        <div style="display:flex;align-items:baseline;gap:8px">
                            <span style="font-size:30px;font-weight:700;letter-spacing:-.02em">{{ $balance->available() }}</span>
                            <span class="ehr-muted">of {{ $balance->total() }} days available</span>
                        </div>
                        <div class="ehr-meter">
                            <span class="taken" style="width: {{ $balance->total() ? 100 * $balance->taken / $balance->total() : 0 }}%"></span>
                            <span class="pending" style="width: {{ $balance->total() ? 100 * $balance->pending / $balance->total() : 0 }}%"></span>
                        </div>
                        <dl class="ehr-dl" style="grid-template-columns:1fr auto">
                            <dt>Days due</dt><dd>{{ $balance->daysDue }}</dd>
                            <dt>Carried forward</dt><dd>{{ $balance->carriedForward }}</dd>
                            <dt>Taken</dt><dd>{{ $balance->taken }}</dd>
                            <dt>Awaiting approval</dt><dd>{{ $balance->pending }}</dd>
                        </dl>
                    @endif
                </div>
            </div>
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Recent leave</h3></div>
                <div class="ehr-panel-body flush">
                    @forelse ($leaves as $l)
                        @if ($loop->first)<ul class="ehr-list">@endif
                        <li>
                            <span class="who"><b><a href="{{ admin_url('leave/' . $l->id) }}" style="color:inherit">{{ $l->typeLabel() }}</a></b>
                                <span>{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M Y') }} · {{ $l->days }} day{{ $l->days === 1 ? '' : 's' }}</span></span>
                            <span class="st st-{{ $l->status }}">{{ $l->statusLabel() }}</span>
                        </li>
                        @if ($loop->last)</ul>@endif
                    @empty
                        <div class="ehr-empty">No leave recorded.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="span-7">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Arrival times, last 30 days</h3><span class="hint">bars start at {{ $lateTime }}: below is early, above is late</span></div>
                <div class="ehr-panel-body ehr-chart-body">
                    @if (count($arrivals) < 2)
                        <div class="ehr-empty"><i class="fa fa-clock-o"></i>Not enough clock-ins in the last 30 days to draw a picture.</div>
                    @else
                        @php
                            $lateMin = (int) substr($lateTime, 0, 2) * 60 + (int) substr($lateTime, 3, 2);
                            $offsets = array_map(fn ($a) => $a['minutes'] - $lateMin, $arrivals);
                            $lo = (int) floor((min(array_merge($offsets, [0])) - 5) / 30) * 30;
                            $hi = (int) ceil((max(array_merge($offsets, [0])) + 5) / 30) * 30;
                            $arrivalChart = [
                                'kind' => 'columns', 'height' => 260, 'legend' => false, 'width' => '58%',
                                'clockBase' => $lateMin, 'yMin' => $lo, 'yMax' => max($hi, 30), 'ticks' => (max($hi, 30) - $lo) / 30,
                                'categories' => array_map(fn ($a) => count($arrivals) <= 12 || \Carbon\Carbon::parse($a['date'])->isMonday() ? \Carbon\Carbon::parse($a['date'])->format('D j M') : '', $arrivals),
                                'titles' => array_map(fn ($a) => \Carbon\Carbon::parse($a['date'])->format('l j F'), $arrivals),
                                'ranges' => [['from' => -1440, 'to' => 0, 'color' => '#17703f'], ['from' => 0.5, 'to' => 1440, 'color' => '#b97b10']],
                                'markY' => 0, 'markYLabel' => 'late after ' . $lateTime,
                                'series' => [['name' => 'Arrived', 'color' => '#17703f', 'data' => $offsets]],
                            ];
                        @endphp
                        <div class="ehr-chart" data-chart="{{ json_encode($arrivalChart) }}"></div>
                    @endif
                </div>
            </div>
            @if (count($arrivals) >= 2)
                @php
                    $std = (float) (\App\Models\SystemConfiguration::current()->full_day_hours ?? 8) ?: 8;
                    $hoursChart = [
                        'kind' => 'columns', 'height' => 200, 'legend' => false, 'width' => '58%', 'suffixUnit' => ' h', 'yMin' => 0,
                        'yMax' => $yMaxH = (int) (ceil(max(array_merge(array_column($arrivals, 'hours'), [$std])) / 2) * 2 + 2), 'ticks' => $yMaxH / 2,
                        'categories' => $arrivalChart['categories'], 'titles' => $arrivalChart['titles'],
                        'ranges' => [['from' => 0, 'to' => $std / 2 + 0.01, 'color' => '#b42318'], ['from' => $std / 2 + 0.01, 'to' => 24, 'color' => '#800000']],
                        'markY' => $std, 'markYLabel' => $std . ' h day',
                        'series' => [['name' => 'Hours on site', 'color' => '#800000', 'data' => array_map(fn ($a) => $a['hours'], $arrivals)]],
                    ];
                @endphp
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>Hours on site</h3><span class="hint">first to last clock-in · red: half a day or less, including days not seen leaving</span></div>
                    <div class="ehr-panel-body ehr-chart-body"><div class="ehr-chart" data-chart="{{ json_encode($hoursChart) }}"></div></div>
                </div>
            @endif
        </div>

        <div class="span-5">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Recent days</h3>
                    @if (\App\Services\AccessPolicy::allows(Admin::user(), 'attendance.view'))
                        <a class="ehr-small" href="{{ admin_url('attendance-records') }}?user_id={{ $user->id }}">All records</a>
                    @endif
                </div>
                <div class="ehr-panel-body flush ehr-table-wrap">
                    <table class="ehr-table">
                        <thead><tr><th>Day</th><th>Status</th><th class="r">In</th><th class="r">Out</th><th class="r">Hours</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $r)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($r->attendance_date)->format('D d M') }}</td>
                                <td><span class="st st-{{ $r->statusKey() }}">{{ $r->statusLabel() }}</span></td>
                                <td class="r">{{ $r->check_in_time ? substr($r->check_in_time, 0, 5) : '—' }}</td>
                                <td class="r">{{ $r->check_out_time ? substr($r->check_out_time, 0, 5) : '—' }}</td>
                                <td class="r">{{ $r->status === 'Present' ? $hm($r->hours) . ($r->is_half_day ? '*' : '') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="ehr-empty">No attendance recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="ehr-panel-foot">* Half day: not seen leaving, credited with half the standard day.</div>
            </div>
        </div>
    </div>
</div>


