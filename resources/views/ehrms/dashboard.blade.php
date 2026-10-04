@php
    use Carbon\Carbon;

    $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
    $hm = fn ($h) => \App\Admin\Controllers\AttendanceRecordController::hm($h);
    $C = ['on_time' => '#17703f', 'late' => '#b97b10', 'absent' => '#b42318', 'on_leave' => '#1a5aa0'];
    $names = ['on_time' => 'On time', 'late' => 'Late', 'absent' => 'Absent', 'on_leave' => 'On leave'];

    // The day the "today" strip describes: today, or the last working day on a weekend or holiday.
    $isToday = $day->isToday();
    $records = fn (array $q) => admin_url('attendance-records') . '?' . http_build_query(array_merge([
        'attendance_date' => ['start' => $day->toDateString(), 'end' => $day->toDateString()],
    ], $q));
    $t = $today;
    $onTime = $t['in']->count() - $t['late']->count();
    $base = max(1, $t['expected'] - $t['on_leave']->count());
    $inSoFar = $t['expected'] ? round(100 * $t['in']->count() / $base, 1) : null;

    $s = $monthSummary;
    $lateTime = substr(\App\Models\SystemConfiguration::current()->defaultLateTime(), 0, 5);
    $keep = fn ($q) => array_filter(array_merge($filters, $q), fn ($v) => $v !== null && $v !== '');

    // Daily columns: working days only (a weekend with nobody expected is not a bar).
    $columns = function (array $daily) use ($C, $names) {
        // Normal working days only: a Saturday where two people on a six-day pattern were expected is not a bar.
        $usual = max(array_map('array_sum', $daily) ?: [0]);
        $daily = array_filter($daily, fn ($d) => array_sum($d) > 0 && array_sum($d) >= $usual / 2);
        $series = [];
        foreach (['on_time', 'late', 'absent', 'on_leave'] as $k) {
            $series[] = ['name' => $names[$k], 'color' => $C[$k], 'data' => array_values(array_map(fn ($d) => $d[$k], $daily))];
        }

        return [
            'kind' => 'columns', 'stacked' => true, 'height' => 372, 'width' => count($daily) > 24 ? '72%' : '55%',
            // Long runs label Mondays only; the tooltip always gives the full date.
            'categories' => array_map(fn ($d) => count($daily) <= 12 || Carbon::parse($d)->isMonday() ? Carbon::parse($d)->format('D j M') : '', array_keys($daily)),
            'titles' => array_map(fn ($d) => Carbon::parse($d)->format('l j F'), array_keys($daily)),
            'series' => $series, 'count' => count($daily),
        ];
    };
    $monthChart = $columns($monthDaily);
    $trendChart = $columns($trendDaily);
    $defaultPane = $monthChart['count'] >= 10 || !$month->isSameMonth(today()) ? 'month' : 'trend';

    $donut = [
        'kind' => 'donut', 'height' => 230, 'legend' => false, 'centerLabel' => 'Attendance', 'centerValue' => $pct($s['rate']),
        'series' => array_map(fn ($k) => ['name' => $names[$k], 'color' => $C[$k], 'data' => $s[$k]], ['on_time', 'late', 'absent', 'on_leave']),
    ];
    $hasMonth = ($s['on_time'] + $s['late'] + $s['absent'] + $s['on_leave']) > 0;

    // Weekly rate and punctuality: drop the weeks before records begin.
    $weeks = array_values(array_filter($weekly, fn ($w) => $w['working'] > 0));
    $low = collect($weeks)->flatMap(fn ($w) => [$w['rate'], $w['punctuality']])->filter(fn ($v) => $v !== null)->min();
    $weekChart = [
        'kind' => 'lines', 'height' => 240, 'suffix' => '%', 'yMax' => 100, 'yMin' => $yMin = ($low === null ? 0 : max(0, (int) floor(($low - 5) / 10) * 10)), 'ticks' => max(2, (100 - $yMin) / 10),
        'categories' => array_column($weeks, 'label'),
        'titles' => array_map(fn ($w) => 'Week of ' . Carbon::parse($w['week'])->format('j F'), $weeks),
        'series' => [
            ['name' => 'Attendance rate', 'color' => '#800000', 'data' => array_column($weeks, 'rate')],
            ['name' => 'Punctuality', 'color' => '#2a78d6', 'data' => array_column($weeks, 'punctuality')],
        ],
    ];

    $hasSpread = collect($spread)->sum(fn ($r) => $r['on_time'] + $r['late']) > 0;
    $spreadChart = [
        'kind' => 'columns', 'stacked' => true, 'height' => 240, 'width' => '82%',
        // Axis labels on the hour; every bar's own quarter hour shows in its tooltip.
        'categories' => array_map(fn ($r) => preg_match('/^\d\d:00$/', $r['label']) || $r['label'] === $lateTime ? $r['label'] : '', $spread),
        'titles' => array_map(fn ($r) => preg_match('/^\d\d:\d\d$/', $r['label']) ? 'Arrived ' . $r['label'] . '–' . date('H:i', strtotime($r['label']) + 899) : 'Arrived ' . $r['label'], $spread),
        'markX' => $lateTime, 'markXLabel' => 'late after ' . $lateTime,
        'series' => [
            ['name' => 'On time', 'color' => $C['on_time'], 'data' => array_column($spread, 'on_time')],
            ['name' => 'Late', 'color' => $C['late'], 'data' => array_column($spread, 'late')],
        ],
    ];
    $peak = collect($spread)->sortByDesc(fn ($r) => $r['on_time'] + $r['late'])->first();
@endphp
<div class="ehr">

    {{-- Filters --}}
    <form method="get" action="{{ admin_url('/') }}" class="ehr-toolbar ehr-dash-filters">
        @if ($faculties->isNotEmpty() || $departments->count() > 1)
            @if ($faculties->isNotEmpty())
                <div class="ehr-field"><label for="f-faculty">Faculty</label>
                    <select id="f-faculty" name="faculty" class="form-control" onchange="this.form.department.value='';this.form.submit()">
                        <option value="">All faculties and units</option>
                        @foreach ($faculties as $f)<option value="{{ $f->id }}" {{ (string) ($filters['faculty'] ?? '') === (string) $f->id ? 'selected' : '' }}>{{ $f->name }}</option>@endforeach
                        <option value="admin" {{ ($filters['faculty'] ?? '') === 'admin' ? 'selected' : '' }}>Administrative units</option>
                    </select></div>
            @endif
            <div class="ehr-field"><label for="f-dept">Department</label>
                <select id="f-dept" name="department" class="form-control" onchange="this.form.submit()">
                    <option value="">All departments in view</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" {{ (string) ($filters['department'] ?? '') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>@endforeach
                </select></div>
        @endif
        <div class="ehr-field"><label for="f-month">Month</label>
            <input id="f-month" type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}" max="{{ today()->format('Y-m') }}" onchange="this.form.submit()"></div>
        @if (array_filter($filters))<a href="{{ admin_url('/') }}" class="btn btn-default" title="Clear filters"><i class="fa fa-times"></i> Clear</a>@endif
        <a href="{{ admin_url('reports') }}" class="btn btn-primary"><i class="fa fa-file-pdf-o"></i> Reports</a>
    </form>

    @if (!empty($offerDemo))
        <div class="ehr-explore">
            <span><b>No clock-ins from real staff in the last two weeks</b>, so these figures are empty until the terminals are in use.
                To see the system with three months of data, explore the demo university (you can come back at any time).</span>
            <form method="post" action="{{ admin_url('demo-data/enter') }}">@csrf
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-flask"></i>&nbsp; Explore the demo</button></form>
        </div>
    @endif

    {{-- Today (or the last working day) --}}
    <div class="ehr-daybar">
        <span class="ehr-daybar-title">
            @if ($isToday)<span class="live-dot" aria-hidden="true"></span> Today, {{ $day->format('l j F') }}
            @else {{ $day->format('l j F') }}
            @endif
        </span>
        <span class="ehr-daybar-note">
            @if ($isToday) live as at <span data-clock-short>{{ now()->format('H:i') }}</span>
            @else {{ today()->format('l') }} is not a working day — showing the last working day
            @endif
        </span>
    </div>
    <div class="ehr-kpis ehr-kpis-6">
        <div class="ehr-kpi k-brand"><div class="v" data-countup="{{ $t['expected'] }}">{{ $t['expected'] }}</div><div class="l">Expected</div><div class="s">{{ $t['on_leave']->count() }} of them on leave</div></div>
        <a class="ehr-kpi k-present" href="{{ $records(['status_key' => 'present']) }}"><div class="v" data-countup="{{ $onTime }}">{{ $onTime }}</div><div class="l">In on time</div><div class="s">by {{ $lateTime }}</div></a>
        <a class="ehr-kpi k-late" href="{{ $records(['status_key' => 'late']) }}"><div class="v" data-countup="{{ $t['late']->count() }}">{{ $t['late']->count() }}</div><div class="l">Late</div>
            <div class="s">{{ $t['late']->sum('late_minutes') ? $hm($t['late']->sum('late_minutes') / 60) . ' lost' : 'none' }}</div></a>
        <a class="ehr-kpi k-absent" href="{{ $records(['status_key' => 'absent']) }}"><div class="v" data-countup="{{ $t['not_in']->count() }}">{{ $t['not_in']->count() }}</div><div class="l">{{ $isToday ? 'Not in yet' : 'Absent' }}</div><div class="s">{{ $isToday ? 'as at ' . now()->format('H:i') : 'did not clock in' }}</div></a>
        <a class="ehr-kpi k-leave" href="{{ $records(['status_key' => 'leave']) }}"><div class="v" data-countup="{{ $t['on_leave']->count() }}">{{ $t['on_leave']->count() }}</div><div class="l">On leave</div><div class="s">approved leave</div></a>
        <div class="ehr-kpi k-brand ehr-kpi-meter"><div class="v" @if ($inSoFar !== null) data-countup="{{ $inSoFar }}" data-suffix="%" @endif>{{ $pct($inSoFar) }}</div>
            <div class="l">{{ $isToday ? 'In so far' : 'Attendance' }}</div>
            <div class="meter" aria-hidden="true"><span style="width: {{ min(100, (float) $inSoFar) }}%"></span></div></div>
    </div>

    <div class="ehr-grid eq">

        @if (!empty($setup))
            <div class="span-12">
                <div class="ehr-panel" style="border-top:3px solid #b97b10">
                    <div class="ehr-panel-head"><h3><i class="fa fa-wrench" style="color:#b97b10;margin-right:4px"></i> Needs attention <span class="ehr-pill" style="background:#b97b10">{{ count($setup) }}</span></h3>
                        <span class="hint">set-up gaps that affect the figures; this list clears itself as they are fixed</span></div>
                    <ul class="ehr-checklist">
                        @foreach ($setup as [$icon, $title, $detail, $url, $action])
                            <li><i class="fa {{ $icon }}"></i><div><b>{{ $title }}</b><span>{{ $detail }}</span></div>
                                @if ($url)<a class="go" href="{{ $url }}">{{ $action }} <i class="fa fa-angle-right"></i></a>@endif</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($waitingCount)
            <div class="span-12">
                <div class="ehr-panel accent">
                    <div class="ehr-panel-head"><h3><i class="fa fa-inbox ehr-ic"></i> Waiting for your decision <span class="ehr-pill">{{ $waitingCount }}</span></h3>
                        <a href="{{ admin_url('leave/approvals') }}" class="ehr-small">Open the approvals queue <i class="fa fa-angle-right"></i></a></div>
                    <div class="ehr-panel-body flush ehr-table-wrap">
                        <table class="ehr-table ehr-table-hover">
                            <tbody>
                            @foreach ($waiting as $l)
                                <tr data-href="{{ admin_url('leave/' . $l->id) }}">
                                    <td style="width:130px;white-space:nowrap"><a class="row-link" href="{{ admin_url('leave/' . $l->id) }}">{{ $l->reference }}</a></td>
                                    <td><span class="ehr-person"><span class="ehr-av">{{ $l->user->initials() }}</span><span>{{ $l->user->name }}<span class="cell-sub">{{ optional($l->user->department)->name }}</span></span></span></td>
                                    <td class="hide-xs">{{ $l->typeLabel() }}</td>
                                    <td class="hide-xs" style="white-space:nowrap">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M') }}</td>
                                    <td class="r"><b>{{ $l->days }}</b> {{ \Illuminate\Support\Str::plural('day', $l->days) }}</td>
                                    <td class="ehr-muted r hide-xs">waiting {{ optional($l->submitted_at)->diffForHumans(null, true) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Daily attendance --}}
        <div class="span-8">
            <div class="ehr-panel">
                <div class="ehr-panel-head">
                    <h3>Daily attendance</h3>
                    <div class="ehr-seg" data-switch="daily" role="group" aria-label="Period">
                        <button type="button" data-show="month" class="{{ $defaultPane === 'month' ? 'on' : '' }}">{{ $month->format('F') }}</button>
                        <button type="button" data-show="trend" class="{{ $defaultPane === 'trend' ? 'on' : '' }}">Last 6 weeks</button>
                    </div>
                </div>
                <div class="ehr-panel-body ehr-chart-body">
                    <div data-pane-of="daily" data-pane-id="month" @if ($defaultPane !== 'month') hidden @endif>
                        @if ($monthChart['count'])
                            <div class="ehr-chart" data-chart="{{ json_encode($monthChart) }}"></div>
                        @else
                            <div class="ehr-empty"><i class="fa fa-bar-chart"></i>No working days recorded in {{ $month->format('F Y') }} yet.</div>
                        @endif
                    </div>
                    <div data-pane-of="daily" data-pane-id="trend" @if ($defaultPane !== 'trend') hidden @endif>
                        @if ($trendChart['count'])
                            <div class="ehr-chart" data-chart="{{ json_encode($trendChart) }}"></div>
                        @else
                            <div class="ehr-empty"><i class="fa fa-bar-chart"></i>No working days recorded in the last six weeks.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Month composition --}}
        <div class="span-4">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>{{ $month->format('F Y') }}</h3><span class="hint">{{ number_format($s['people']) }} staff · {{ $month->isSameMonth(today()) ? 'to date' : 'whole month' }}</span></div>
                <div class="ehr-panel-body">
                    @if ($hasMonth)
                        <div class="ehr-donut-wrap">
                            <div class="ehr-chart" data-chart="{{ json_encode($donut) }}"></div>
                        </div>
                        <ul class="ehr-keys">
                            @foreach (['on_time', 'late', 'absent', 'on_leave'] as $k)
                                <li><i style="background: {{ $C[$k] }}"></i>{{ $names[$k] }}<b>{{ number_format($s[$k]) }}</b></li>
                            @endforeach
                        </ul>
                        <dl class="ehr-dl ehr-dl-tight">
                            <dt>Punctuality</dt><dd>{{ $pct($s['punctuality']) }}</dd>
                            <dt>Time lost to lateness</dt><dd>{{ $hm($s['late_minutes'] / 60) }}</dd>
                            <dt>Half days (not seen leaving)</dt><dd>{{ number_format($s['half_days']) }}</dd>
                            <dt>Hours on site</dt><dd>{{ number_format($s['hours'], 0) }} h</dd>
                        </dl>
                    @else
                        <div class="ehr-empty"><i class="fa fa-pie-chart"></i>No attendance recorded for this month yet.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Trend and arrival pattern --}}
        <div class="span-6">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>Attendance and punctuality</h3><span class="hint">weekly, last 12 weeks</span></div>
                <div class="ehr-panel-body ehr-chart-body">
                    @if (count($weeks) > 0)
                        <div class="ehr-chart" data-chart="{{ json_encode($weekChart) }}"></div>
                    @else
                        <div class="ehr-empty"><i class="fa fa-line-chart"></i>Weekly figures appear once attendance is recorded.</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="span-6">
            <div class="ehr-panel">
                <div class="ehr-panel-head"><h3>When people arrive</h3>
                    <span class="hint">{{ $month->format('F') }}{{ $peak && ($peak['on_time'] + $peak['late']) ? ' · busiest ' . $peak['label'] : '' }}</span></div>
                <div class="ehr-panel-body ehr-chart-body">
                    @if ($hasSpread)
                        <div class="ehr-chart" data-chart="{{ json_encode($spreadChart) }}"></div>
                    @else
                        <div class="ehr-empty"><i class="fa fa-clock-o"></i>No clock-ins recorded for {{ $month->format('F Y') }} yet.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Departments and the day's lists --}}
        <div class="span-5">
            <div class="ehr-panel natural">
                <div class="ehr-panel-head"><h3>Departments</h3><span class="hint">attendance rate · {{ $month->format('F') }} · lowest first</span></div>
                <div class="ehr-panel-body flush">
                    @if ($league->isEmpty())
                        <div class="ehr-empty"><i class="fa fa-sitemap"></i>No department figures for this month yet.</div>
                    @else
                        <ul class="ehr-league">
                            @foreach ($league->take(10) as $d)
                                @php $tone = $d->rate < 85 ? 'low' : ($d->rate < 95 ? 'mid' : 'ok'); @endphp
                                <li>
                                    <a href="{{ $d->id ? admin_url('/') . '?' . http_build_query($keep(['department' => $d->id, 'faculty' => null])) : '#' }}"
                                       title="{{ $d->name }}: {{ number_format($d->present) }} of {{ number_format($d->expected) }} staff-days present · {{ $d->late }} late arrival(s)">
                                        <span class="name">{{ $d->name }}<span>{{ $d->staff }} staff · {{ $d->late }} late</span></span>
                                        <span class="track"><span class="{{ $tone }}" style="width: {{ $d->rate }}%"></span><i class="target" style="left:95%" title="95% target"></i></span>
                                        <span class="pct {{ $tone }}">{{ $pct($d->rate) }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($league->count() > 10)<div class="ehr-panel-foot">{{ $league->count() - 10 }} more department(s), all at {{ $pct($league->slice(10)->min('rate')) }} or above. The tick marks the 95% target.</div>
                @elseif ($league->isNotEmpty())<div class="ehr-panel-foot">Select a department to see only its staff. The tick marks the 95% target.</div>@endif
            </div>
        </div>
        <div class="span-7">
            <div class="ehr-panel natural">
                <div class="ehr-panel-head ehr-panel-head-tabs">
                    <nav class="ehr-tabs js-tabs">
                        <a href="#" data-tab="late" class="on">Late{{ $isToday ? ' today' : '' }}<span class="count">{{ $t['late']->count() }}</span></a>
                        <a href="#" data-tab="notin">{{ $isToday ? 'Not in yet' : 'Absent' }}<span class="count">{{ $t['not_in']->count() }}</span></a>
                        <a href="#" data-tab="away">Away, next 14 days<span class="count">{{ $upcomingLeave->count() }}</span></a>
                    </nav>
                </div>
                <div class="ehr-panel-body flush">
                    <div data-pane="late">
                        @forelse ($t['late']->sortByDesc('late_minutes')->take(8) as $r)
                            @if ($loop->first)<ul class="ehr-list">@endif
                            <li><a class="ehr-person" href="{{ admin_url('staff/' . $r->user->id) }}"><span class="ehr-av">{{ $r->user->initials() }}</span><span class="who"><b>{{ $r->user->name }}</b><span>{{ optional($r->user->department)->name }}</span></span></a>
                                <span class="num">{{ substr($r->check_in_time, 0, 5) }} <span class="st st-late">+{{ $hm($r->late_minutes / 60) }}</span></span></li>
                            @if ($loop->last)</ul>@endif
                        @empty
                            <div class="ehr-empty"><i class="fa fa-check-circle-o"></i>Nobody arrived late{{ $isToday ? ' today' : '' }}.</div>
                        @endforelse
                    </div>
                    <div data-pane="notin" hidden>
                        @forelse ($t['not_in']->take(8) as $r)
                            @if ($loop->first)<ul class="ehr-list">@endif
                            <li><a class="ehr-person" href="{{ admin_url('staff/' . $r->user->id) }}"><span class="ehr-av">{{ $r->user->initials() }}</span><span class="who"><b>{{ $r->user->name }}</b><span>{{ optional($r->user->department)->name }}</span></span></a>
                                <span class="ehr-muted ehr-small">{{ $r->user->position }}</span></li>
                            @if ($loop->last)</ul>@endif
                        @empty
                            <div class="ehr-empty"><i class="fa fa-check-circle-o"></i>Everyone expected has clocked in.</div>
                        @endforelse
                    </div>
                    <div data-pane="away" hidden>
                        @forelse ($upcomingLeave as $l)
                            @if ($loop->first)<ul class="ehr-list">@endif
                            <li><a class="ehr-person" href="{{ admin_url('leave/' . $l->id) }}"><span class="ehr-av">{{ $l->user->initials() }}</span><span class="who"><b>{{ $l->user->name }}</b><span>{{ $l->typeLabel() }} · {{ optional($l->user->department)->name }}</span></span></a>
                                <span class="ehr-small" style="white-space:nowrap">{{ $l->start_date->format('d M') }} – {{ $l->end_date->format('d M') }}
                                    @if ($l->start_date->lte(today()))<span class="st st-leave">Away</span>@endif</span></li>
                            @if ($loop->last)</ul>@endif
                        @empty
                            <div class="ehr-empty"><i class="fa fa-plane"></i>No approved leave in the next 14 days.</div>
                        @endforelse
                    </div>
                </div>
                <div class="ehr-panel-foot js-tab-foot">
                    <a href="{{ $records(['status_key' => 'late']) }}" data-for="late">All late arrivals <i class="fa fa-angle-right"></i></a>
                    <a href="{{ $records(['status_key' => 'absent']) }}" data-for="notin" hidden>Everyone not clocked in <i class="fa fa-angle-right"></i></a>
                    <a href="{{ admin_url('leave/all') }}?when=now" data-for="away" hidden>Everyone away <i class="fa fa-angle-right"></i></a>
                </div>
            </div>
        </div>

        {{-- The last 30 days --}}
        @foreach ([['Most absences', $topAbsent, 'day', 'absent'], ['Most late arrivals', $topLate, 'time', 'late']] as [$title, $rows, $unit, $kind])
            @php $max = max(1, $rows->max('count')); @endphp
            <div class="span-6">
                <div class="ehr-panel">
                    <div class="ehr-panel-head"><h3>{{ $title }}</h3><span class="hint">last 30 days</span></div>
                    <div class="ehr-panel-body flush">
                        @forelse ($rows as $r)
                            @if ($loop->first)<ul class="ehr-list ehr-rank">@endif
                            <li><a class="ehr-person" href="{{ admin_url('staff/' . $r->user->id) }}"><span class="ehr-av">{{ $r->user->initials() }}</span><span class="who"><b>{{ $r->user->name }}</b><span>{{ optional($r->user->department)->name ?: 'No department' }}</span></span></a>
                                <span class="rank-bar" aria-hidden="true"><span class="{{ $kind }}" style="width: {{ 100 * $r->count / $max }}%"></span></span>
                                <span class="num">{{ $r->count }} {{ \Illuminate\Support\Str::plural($unit, $r->count) }}@if ($kind === 'late')<span class="cell-sub">{{ $hm($r->minutes / 60) }} lost</span>@endif</span></li>
                            @if ($loop->last)</ul>@endif
                        @empty
                            <div class="ehr-empty"><i class="fa fa-smile-o"></i>{{ $kind === 'late' ? 'No late arrivals' : 'No absences' }} in the last 30 days.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
$(function () {
    $('.js-tabs').off('click.tabs').on('click.tabs', 'a[data-tab]', function (e) {
        e.preventDefault();
        var tab = this.getAttribute('data-tab');
        var $panel = $(this).closest('.ehr-panel');
        $(this).addClass('on').siblings().removeClass('on');
        $panel.find('[data-pane]').each(function () { this.hidden = this.getAttribute('data-pane') !== tab; });
        $panel.find('.js-tab-foot [data-for]').each(function () { this.hidden = this.getAttribute('data-for') !== tab; });
    });
});
</script>
