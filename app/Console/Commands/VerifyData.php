<?php

namespace App\Console\Commands;

use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\PublicHoliday;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\AttendanceStats;
use App\Services\LeaveRules;
use App\Services\Reports;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Checks that every figure the system shows adds up.
 *
 * The attendance check does not trust the attendance engine: it works each
 * day out again with a separate, plain implementation of the rules, straight
 * from the raw clock-ins, and compares. The other checks reconcile the
 * dashboards, the reports, leave balances and the approval trail with each
 * other and with the records underneath.
 *
 *   php artisan ehrms:verify                 # the demo staff, last 90 days
 *   php artisan ehrms:verify --everyone      # every active person
 *   php artisan ehrms:verify --from=2026-09-01 --to=2026-09-30
 */
class VerifyData extends Command
{
    protected $signature = 'ehrms:verify {--from=} {--to=} {--everyone : Check every active person, not just the demo sandbox} {--show=5 : Examples to print per failed check}';

    protected $description = 'Recompute attendance and leave independently and check that every figure adds up';

    private array $results = [];

    public function handle()
    {
        $to = Carbon::parse($this->option('to') ?: today())->startOfDay()->min(today());
        $from = Carbon::parse($this->option('from') ?: $to->copy()->subDays(89))->startOfDay();
        $demo = User::where('is_demo', true)->pluck('id')->all();
        $userIds = $this->option('everyone') || !$demo
            ? User::where('status', 'Active')->pluck('id')->all()
            : $demo;
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $this->info(sprintf('Checking %d people, %s – %s', count($userIds), $from->format('d M Y'), $to->format('d M Y')));

        $this->checkAttendanceIndependently($users, $from, $to);
        $this->checkRecordsAreWellFormed($userIds, $from, $to);
        $this->checkTotalsAgree($userIds, $from, $to);
        $this->checkLeaveMatchesAttendance($userIds, $from, $to);
        $this->checkLeaveArithmetic($users);
        $this->checkWorkflowIntegrity($userIds);

        $this->newLine();
        $this->table(['Check', 'Result', 'Detail'], array_map(fn ($r) => [$r[0], $r[1] ? '<info>pass</info>' : '<error>FAIL</error>', $r[2]], $this->results));
        $failed = count(array_filter($this->results, fn ($r) => !$r[1]));
        $failed ? $this->error("{$failed} check(s) failed.") : $this->info('Everything adds up.');

        return $failed ? 1 : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Attendance, recomputed from the raw clock-ins
    |--------------------------------------------------------------------------
    */

    private function checkAttendanceIndependently($users, Carbon $from, Carbon $to): void
    {
        $config = SystemConfiguration::current();
        $holidays = PublicHoliday::whereBetween('date', [$from->toDateString(), $to->toDateString()])->pluck('date')
            ->map(fn ($d) => substr((string) $d, 0, 10))->flip();
        $trackFrom = $config->start_date ? Carbon::parse($config->start_date)->toDateString() : null;
        $window = $config->repeat_capture_minutes * 60;
        $halfDay = round($config->full_day_hours / 2, 2);
        [$dh, $dm] = array_map('intval', explode(':', $config->defaultLateTime()));

        // Raw clock-ins: codes absent (bridge) or a successful card / fingerprint / face.
        $punches = [];
        DB::table('event_logs')->whereIn('user_id', $users->keys())
            ->whereBetween('event_time', [$from->toDateString() . ' 00:00:00', $to->toDateString() . ' 23:59:59'])
            ->where(fn ($q) => $q->whereNull('major')->orWhere(fn ($r) => $r->where('major', 5)->whereIn('minor', [1, 38, 75])))
            ->orderBy('event_time')->get(['user_id', 'event_time'])
            ->each(function ($e) use (&$punches) {
                $punches[$e->user_id][substr($e->event_time, 0, 10)][] = $e->event_time;
            });

        $leaveDays = [];
        Leave::whereIn('user_id', $users->keys())->whereIn('status', [Leave::APPROVED, Leave::RECALLED])
            ->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString())
            ->get()->each(function (Leave $l) use (&$leaveDays) {
                $last = $l->status === Leave::RECALLED ? $l->recall_date->copy()->subDay() : $l->end_date;
                foreach (CarbonPeriod::create($l->start_date, $last) as $d) {
                    $leaveDays[$l->user_id][$d->toDateString()] = true;
                }
            });

        $records = DB::table('attendance_records')->whereIn('user_id', $users->keys())
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])->get()
            ->keyBy(fn ($r) => $r->user_id . '|' . $r->attendance_date);

        $names = ['monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6, 'sunday' => 7];
        $mismatches = [];
        $checked = 0;
        foreach ($users as $user) {
            $own = array_values(array_filter(array_map(fn ($d) => $names[strtolower(trim($d))] ?? null, $user->work_days)));
            $weekdays = $own ?: $config->workingWeekdays();
            $start = $user->start_working_date ? Carbon::parse($user->start_working_date)->max($from) : $from;
            [$lh, $lm] = $user->custom_late_time ? array_map('intval', explode(':', $user->custom_late_time)) : [$dh, $dm];

            foreach (CarbonPeriod::create($start, $to) as $day) {
                $key = $day->toDateString();
                $record = $records[$user->id . '|' . $key] ?? null;
                if ($record && ($record->is_manual || $record->source === 'import' || $record->is_imported === 'Yes')) {
                    continue; // corrected or imported by people: not the engine's to compute
                }
                $working = in_array($day->isoWeekday(), $weekdays, true) && !isset($holidays[$key]);
                $times = $punches[$user->id][$key] ?? [];

                $expect = null;
                if ($times) {
                    $first = Carbon::parse($times[0]);
                    $last = Carbon::parse(end($times));
                    $left = $last->diffInSeconds($first) >= $window;
                    $late = $working ? max(0, ($first->hour * 60 + $first->minute) - ($lh * 60 + $lm)) : 0;
                    $expect = [
                        'status' => 'Present', 'in' => $first->format('H:i:s'), 'out' => $left ? $last->format('H:i:s') : null,
                        'late' => $late, 'hours' => $left ? round($last->diffInSeconds($first) / 3600, 2) : ($day->isToday() ? 0 : $halfDay),
                    ];
                } elseif ($working && (!$trackFrom || $key >= $trackFrom)) {
                    $expect = ['status' => isset($leaveDays[$user->id][$key]) ? 'On Leave' : 'Absent', 'in' => null, 'out' => null, 'late' => 0, 'hours' => 0];
                }

                $checked++;
                $actual = $record ? [
                    'status' => $record->status, 'in' => $record->check_in_time, 'out' => $record->check_out_time,
                    'late' => (int) $record->late_minutes, 'hours' => round((float) $record->hours, 2),
                ] : null;
                if ($expect != $actual) {
                    $mismatches[] = "{$user->name} {$key}: expected " . json_encode($expect) . ' got ' . json_encode($actual);
                }
            }
        }

        $this->record('Attendance equals an independent recomputation from the clock-ins', !$mismatches,
            number_format($checked) . ' person-days checked' . ($mismatches ? ', ' . count($mismatches) . ' differ' : ''), $mismatches);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Records are well formed
    |--------------------------------------------------------------------------
    */

    private function checkRecordsAreWellFormed(array $userIds, Carbon $from, Carbon $to): void
    {
        $q = fn () => DB::table('attendance_records')->whereIn('user_id', $userIds)->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);

        $future = DB::table('attendance_records')->whereIn('user_id', $userIds)->where('attendance_date', '>', today()->toDateString())->count();
        $this->record('No attendance recorded for days that have not happened', $future === 0, "{$future} future record(s)");

        $dupes = DB::table('attendance_records')->whereIn('user_id', $userIds)->select('user_id', 'attendance_date')
            ->groupBy('user_id', 'attendance_date')->havingRaw('COUNT(*) > 1')->get()->count();
        $this->record('One record per person per day', $dupes === 0, "{$dupes} duplicate(s)");

        $badPresent = $q()->where('status', 'Present')->whereNull('check_in_time')->count();
        $badAbsent = $q()->whereIn('status', ['Absent', 'On Leave'])->where(fn ($w) => $w->whereNotNull('check_in_time')->orWhere('hours', '>', 0)->orWhere('is_late', 'Yes'))->count();
        $badLate = $q()->where(fn ($w) => $w->where(fn ($a) => $a->where('is_late', 'Yes')->where('late_minutes', 0))->orWhere(fn ($b) => $b->where('is_late', 'No')->where('late_minutes', '>', 0)))->count();
        $lateOff = $q()->where('is_working_day', 0)->where('is_late', 'Yes')->count();
        $badOut = $q()->whereNotNull('check_out_time')->whereColumn('check_out_time', '<=', 'check_in_time')->count();
        $bad = $badPresent + $badAbsent + $badLate + $lateOff + $badOut;
        $this->record('Every record is internally consistent', $bad === 0,
            "present without arrival {$badPresent}, absent with times {$badAbsent}, late flag vs minutes {$badLate}, late on a day off {$lateOff}, left before arriving {$badOut}");
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Dashboard, staff pages and reports agree
    |--------------------------------------------------------------------------
    */

    private function checkTotalsAgree(array $userIds, Carbon $from, Carbon $to): void
    {
        $summary = AttendanceStats::summary($userIds, $from, $to);
        $people = AttendanceStats::perPerson($userIds, $from, $to);
        $daily = AttendanceStats::daily($userIds, $from, $to);
        $report = Reports::summary('check', $userIds, $from, $to);

        $parts = $summary['on_time'] + $summary['late'] + $summary['absent'] + $summary['on_leave'];
        $this->record('Working days = on time + late + absent + on leave', $parts === $summary['working'], "{$summary['working']} = {$parts}");

        $diffs = [];
        foreach (['working', 'present', 'on_time', 'late', 'absent', 'on_leave', 'half_days', 'late_minutes'] as $k) {
            if ($people->sum($k) !== $summary[$k]) {
                $diffs[] = "{$k}: people {$people->sum($k)} vs total {$summary[$k]}";
            }
        }
        $this->record('Dashboard totals equal the sum over every person', !$diffs, $diffs ? implode('; ', $diffs) : 'all eight figures agree', $diffs);

        $dailyDiffs = [];
        foreach (['on_time', 'late', 'absent', 'on_leave'] as $k) {
            $sum = array_sum(array_column($daily, $k));
            if ($sum !== $summary[$k]) {
                $dailyDiffs[] = "{$k}: by day {$sum} vs total {$summary[$k]}";
            }
        }
        $this->record('Month chart (by day) adds up to the same totals', !$dailyDiffs, $dailyDiffs ? implode('; ', $dailyDiffs) : 'four series agree', $dailyDiffs);

        $reportRows = $report['departments']->flatMap->rows;
        $reportOk = $report['summary'] == $summary && $reportRows->sum('present') === $summary['present'] && $reportRows->count() === $people->count();
        $this->record('PDF summary report shows the dashboard figures', $reportOk,
            "{$reportRows->count()} people in {$report['departments']->count()} departments, {$reportRows->sum('present')} staff-days present");

        $rateOk = $people->every(function ($p) {
            $expected = $p->working - $p->on_leave;

            return $p->rate === ($expected > 0 ? round(100 * $p->present / $expected, 1) : null);
        });
        $this->record('Each person\'s rate = present ÷ (working days − leave)', $rateOk, 'checked for ' . $people->count() . ' people');
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Leave and attendance tell the same story
    |--------------------------------------------------------------------------
    */

    private function checkLeaveMatchesAttendance(array $userIds, Carbon $from, Carbon $to): void
    {
        $onLeave = DB::table('attendance_records')->whereIn('user_id', $userIds)->where('status', 'On Leave')
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])->get(['user_id', 'attendance_date', 'is_manual']);
        $orphans = $onLeave->reject(fn ($r) => $r->is_manual)->filter(function ($r) {
            return !Leave::inForceOn($r->attendance_date)->where('user_id', $r->user_id)->exists();
        });
        $this->record('Every "on leave" day is covered by approved leave', $orphans->isEmpty(),
            $onLeave->count() . ' on-leave day(s), ' . $orphans->count() . ' without approved leave',
            $orphans->map(fn ($r) => "user {$r->user_id} on {$r->attendance_date}")->all());

        $pendingCounted = DB::table('attendance_records as a')
            ->join('leaves as l', function ($j) {
                $j->on('l.user_id', '=', 'a.user_id')->whereColumn('a.attendance_date', '>=', 'l.start_date')->whereColumn('a.attendance_date', '<=', 'l.end_date');
            })
            ->whereIn('a.user_id', $userIds)->where('a.status', 'On Leave')->where('a.is_manual', 0)
            ->whereIn('l.status', [Leave::PENDING, Leave::REJECTED, Leave::WITHDRAWN, Leave::CANCELLED])
            ->whereNotExists(function ($q) {
                $q->from('leaves as ok')->whereColumn('ok.user_id', 'a.user_id')->whereColumn('ok.start_date', '<=', 'a.attendance_date')
                    ->whereColumn('ok.end_date', '>=', 'a.attendance_date')->whereIn('ok.status', [Leave::APPROVED, Leave::RECALLED]);
            })->count();
        $this->record('Pending, declined, withdrawn or cancelled leave never excuses absence', $pendingCounted === 0, "{$pendingCounted} day(s)");
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Leave arithmetic
    |--------------------------------------------------------------------------
    */

    private function checkLeaveArithmetic($users): void
    {
        $leaves = Leave::whereIn('user_id', $users->keys())->get();
        $wrongDays = $leaves->filter(fn (Leave $l) => $l->days !== LeaveRules::workingDays($l->start_date, $l->end_date));
        $this->record('Each request counts its working days correctly', $wrongDays->isEmpty(),
            $leaves->count() . ' request(s), ' . $wrongDays->count() . ' miscounted', $wrongDays->map(fn ($l) => "{$l->reference}: {$l->days} vs " . LeaveRules::workingDays($l->start_date, $l->end_date))->all());

        $wrongRecall = $leaves->where('status', Leave::RECALLED)->filter(fn (Leave $l) => $l->days_restored !== LeaveRules::workingDays($l->recall_date, $l->end_date));
        $this->record('Recalls restore exactly the unused working days', $wrongRecall->isEmpty(), $leaves->where('status', Leave::RECALLED)->count() . ' recall(s)');

        $problems = [];
        foreach ($users as $user) {
            foreach (DB::table('leave_entitlements')->where('user_id', $user->id)->pluck('leave_year') as $year) {
                $b = LeaveRules::annualBalance($user, (int) $year);
                $taken = $leaves->where('user_id', $user->id)->where('leave_type', Leave::ANNUAL)->where('leave_year', (int) $year)
                    ->whereIn('status', [Leave::APPROVED, Leave::RECALLED])->sum(fn ($l) => $l->daysTaken());
                if ($b->taken !== (int) $taken || $b->balance() !== $b->daysDue + $b->carriedForward - $b->taken) {
                    $problems[] = "{$user->name} {$year}: taken {$b->taken} vs {$taken}";
                }
                if ($b->balance() < 0) {
                    $problems[] = "{$user->name} {$year}: overdrawn ({$b->balance()})";
                }
            }
        }
        $this->record('Balances: due + carried forward − taken, never overdrawn', !$problems, count($problems) . ' problem(s)', $problems);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. The approval trail is complete
    |--------------------------------------------------------------------------
    */

    private function checkWorkflowIntegrity(array $userIds): void
    {
        $problems = [];
        foreach (Leave::whereIn('user_id', $userIds)->with('actions')->get() as $l) {
            $acts = $l->actions->pluck('action');
            if (!$acts->contains(LeaveAction::SUBMITTED) && !$acts->contains(LeaveAction::RECORDED)) {
                $problems[] = "{$l->reference}: no submission in the trail";
            }
            if ($l->status === Leave::PENDING && (!$l->stage || !in_array($l->stage, $l->routeList(), true))) {
                $problems[] = "{$l->reference}: pending but not at a stage of its route";
            }
            if ($l->status !== Leave::PENDING && $l->stage) {
                $problems[] = "{$l->reference}: decided but still at a stage";
            }
            if ($l->source === Leave::SOURCE_APPLICATION && in_array($l->status, [Leave::APPROVED, Leave::RECALLED, Leave::CANCELLED], true)
                && !$l->actions->contains(fn ($a) => $a->action === LeaveAction::APPROVED && $a->stage === Leave::STAGE_US)) {
                $problems[] = "{$l->reference}: approved without the University Secretary's decision";
            }
            if ($l->status === Leave::REJECTED && !$l->actions->contains(fn ($a) => $a->action === LeaveAction::REJECTED && trim((string) $a->comment) !== '')) {
                $problems[] = "{$l->reference}: declined without a reason";
            }
            if ($l->actions->contains(fn ($a) => $a->actor_id && (int) $a->actor_id === (int) $l->user_id && !in_array($a->action, [LeaveAction::SUBMITTED, LeaveAction::WITHDRAWN], true))) {
                $problems[] = "{$l->reference}: the applicant acted on their own request";
            }
            if ($l->hr_days_due !== null && !$l->actions->contains(fn ($a) => $a->action === LeaveAction::VERIFIED)) {
                $problems[] = "{$l->reference}: Section II frozen without HR verification";
            }
        }
        $this->record('Approval trails are complete and nobody approved their own leave', !$problems, count($problems) . ' problem(s)', $problems);
    }

    private function record(string $check, bool $ok, string $detail, array $examples = []): void
    {
        $this->results[] = [$check, $ok, $detail];
        if (!$ok && $examples) {
            $this->warn("✗ {$check}");
            foreach (array_slice($examples, 0, (int) $this->option('show')) as $example) {
                $this->line('    ' . $example);
            }
        }
    }
}
