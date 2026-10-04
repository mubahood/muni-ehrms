<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\LeaveEntitlement;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Support\DemoManifest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The demonstration university: fifteen people in seven departments (see
 * config/demo.php), three months of clock-ins and leave in every state, all
 * marked is_demo and sealed off from the real university.
 *
 *  build()   creates it (refuses if it exists)
 *  topUp()   adds the day's simulated clock-ins up to now, so the sandbox
 *            stays current between rebuilds (run by the scheduler)
 *  purge()   removes every trace of it
 *
 * Clock-ins are simulated per person per day from a seed made of the two, so
 * the same day always comes out the same however often it is generated.
 */
class DemoSandbox
{
    private WorkCalendar $calendar;

    /** @var array<int, string> user id => persona */
    private array $personas = [];

    public static function exists(): bool
    {
        return User::where('is_demo', true)->exists();
    }

    /** @return array<string, int|string|null> */
    public static function stats(): array
    {
        $ids = User::where('is_demo', true)->pluck('id');

        return [
            'accounts' => $ids->count(),
            'logins' => User::where('is_demo', true)->whereIn('username', collect(config('demo.accounts'))->pluck('username'))->count(),
            'departments' => Department::where('is_demo', true)->count(),
            'faculties' => Faculty::where('is_demo', true)->count(),
            'clock_ins' => DB::table('event_logs')->where('source', DemoManifest::EVENT_SOURCE)->count(),
            'attendance' => DB::table('attendance_records')->whereIn('user_id', $ids)->count(),
            'leave' => Leave::whereIn('user_id', $ids)->count(),
            'pending' => Leave::whereIn('user_id', $ids)->where('status', Leave::PENDING)->count(),
            'built_at' => optional(User::where('is_demo', true)->min('created_at'), fn ($d) => Carbon::parse($d)->toDateTimeString()),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build
    |--------------------------------------------------------------------------
    */

    public function build(?int $days = null): array
    {
        if (self::exists()) {
            throw new \RuntimeException('The demo sandbox already exists. Delete it first, or rebuild.');
        }
        $days = max(14, $days ?? (int) config('demo.days', 90));
        $from = today()->subDays($days - 1);
        $this->calendar = new WorkCalendar($from->copy()->subDays(10), today()->addDays(120));
        $engine = app(AttendanceEngine::class);

        DB::transaction(function () use ($from, $engine) {
            // The sandbox's history may start before the University's records do.
            $config = SystemConfiguration::current();
            if (!$config->start_date || Carbon::parse($config->start_date)->gt($from)) {
                $config->update(['start_date' => $from->toDateString()]);
            }

            [$faculties, $departments] = $this->organisation();
            $people = $this->people($faculties, $departments, $from);
            $this->entitlements($people, $from);
            $this->leave($people);
            foreach (CarbonPeriod::create($from, today()) as $day) {
                $this->simulate($people, $day);
            }
            $engine->processRange($from, today(), $people->pluck('id')->all());
            $this->corrections($people);
            $this->backdate($people->pluck('id')->all());
        });

        return self::stats();
    }

    private function organisation(): array
    {
        $faculties = [];
        foreach (config('demo.faculties') as $code => $name) {
            $faculties[$code] = Faculty::create(['name' => $name, 'code' => $code, 'is_active' => true, 'is_demo' => true]);
        }
        $departments = [];
        foreach (config('demo.departments') as $code => $d) {
            [$name, $facultyCode] = $d;
            $department = new Department();
            $department->forceFill([
                'name' => $name, 'code' => $code, 'is_active' => true, 'is_demo' => true,
                'type' => $facultyCode ? Department::ACADEMIC : Department::ADMINISTRATIVE,
                'faculty_id' => $facultyCode ? $faculties[$facultyCode]->id : null,
            ])->save();
            $departments[$code] = $department;
        }

        return [$faculties, $departments];
    }

    private function people(array $faculties, array $departments, Carbon $from): Collection
    {
        $roles = Role::pluck('id', 'slug');
        $people = collect();
        $serial = 0;

        // The fifteen sign-in accounts.
        foreach (config('demo.accounts') as $a) {
            $user = $this->person($a['username'], $a['title'], $a['first'], $a['last'], $a['sex'], $a['position'],
                $departments[$a['department']], $from, ++$serial, array_merge($a['roles'], ['employee']), $roles);
            if (!empty($a['heads'])) {
                $departments[$a['heads']]->update(['hod_id' => $user->id]);
            }
            if (!empty($a['dean_of'])) {
                $faculties[$a['dean_of']]->update(['dean_id' => $user->id]);
            }
            $people->put($a['username'], $user);
        }

        // The rest of the staff, so the university has a realistic size.
        $first = config('demo.first_names');
        $last = config('demo.surnames');
        $used = $people->mapWithKeys(fn ($u) => [strtolower($u->first_name . ' ' . $u->last_name) => true])->all();
        $k = 0;
        $next = function () use (&$k, &$used, $first, $last) {
            do {
                $f = $first[($k * 7 + intdiv($k, count($first))) % count($first)];
                $l = $last[($k * 11 + 3) % count($last)];
                $k++;
            } while (isset($used[strtolower("{$f} {$l}")]));
            $used[strtolower("{$f} {$l}")] = true;

            return [$f, $l, in_array($f, array_slice($first, 20), true) ? 'Female' : 'Male'];
        };
        $staffNo = 0;
        foreach (config('demo.departments') as $code => [$name, $facultyCode, $count, $titles, $head]) {
            for ($n = 0; $n < $count; $n++) {
                [$f, $l, $sex] = $next();
                $title = $titles[$n % count($titles)];
                $isHead = $head && $n === 0;
                $position = $isHead ? ($facultyCode ? "Head of Department, {$name}" : "Head, {$name}") : $title;
                $honorific = $title === 'Senior Lecturer' || $isHead && $facultyCode ? 'Dr' : ($sex === 'Female' ? 'Ms' : 'Mr');
                $user = $this->person(sprintf('demo.staff%03d', ++$staffNo), $honorific, $f, $l, $sex, $position,
                    $departments[$code], $from, ++$serial, $isHead ? ['hod', 'employee'] : ['employee'], $roles);
                if ($isHead) {
                    $departments[$code]->update(['hod_id' => $user->id]);
                }
                $people->put($user->username, $user);
            }
        }
        foreach (config('demo.extra_deans', []) as $facultyCode => $deptCode) {
            [$f, $l, $sex] = $next();
            $user = $this->person(sprintf('demo.staff%03d', ++$staffNo), 'Prof', $f, $l, $sex, 'Dean, ' . config("demo.faculties.{$facultyCode}"),
                $departments[$deptCode], $from, ++$serial, ['dean', 'employee'], $roles);
            $faculties[$facultyCode]->update(['dean_id' => $user->id]);
            $people->put($user->username, $user);
        }

        foreach ($people as $user) {
            $this->personas[$user->id] = self::personaFor($user->username);
        }

        return $people;
    }

    private function person(string $username, string $honorific, string $first, string $last, string $sex, string $position,
        Department $department, Carbon $from, int $serial, array $roleSlugs, $roles): User
    {
        $user = new User();
        $user->forceFill([
            'username' => $username,
            'password' => Hash::make(config('demo.password')),
            'name' => "{$honorific} {$first} {$last}",
            'first_name' => $first,
            'last_name' => $last,
            'email' => $username . '@demo.invalid',
            'employee_no' => sprintf('DEMO-%03d', $serial),
            'position' => $position,
            'sex' => $sex,
            'department_id' => $department->id,
            'campus_id' => DB::table('companies')->min('id') ?: 1,
            'status' => 'Active',
            // The sandbox's history starts at $from, so that is when they start work:
            // rebuilding attendance over a longer range never marks them absent before it.
            'start_working_date' => $from->toDateString(),
            'change_password' => 'No',
            'has_changed_password' => 'Yes',
            'is_mail_verified' => 'Yes',
            'notify_account_created_by_email' => 'No',
            'is_demo' => true,
        ]);
        $user->work_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $user->save();
        $user->roles()->sync(collect($roleSlugs)->unique()->map(fn ($r) => $roles[$r])->all());

        return $user;
    }

    /** How a demo person behaves: as configured for a sign-in account, otherwise drawn from the mix by their username. */
    public static function personaFor(string $username): string
    {
        $account = collect(config('demo.accounts'))->firstWhere('username', $username);
        if ($account) {
            return $account['persona'];
        }
        $roll = crc32($username) % 100;
        foreach (config('demo.persona_mix') as $persona => $share) {
            if ($roll < $share) {
                return $persona;
            }
            $roll -= $share;
        }

        return 'punctual';
    }

    private function entitlements(Collection $people, Carbon $from): void
    {
        $years = array_unique([LeaveRules::leaveYearOf($from), LeaveRules::currentLeaveYear(), LeaveRules::leaveYearOf(today()->addDays(120))]);
        foreach ($people->values() as $i => $person) {
            foreach ($years as $year) {
                LeaveEntitlement::create([
                    'user_id' => $person->id, 'leave_year' => $year,
                    'days_due' => strpos($person->position, 'Dean') !== false || strpos($person->name, 'Prof') === 0 ? 36 : 30,
                    'carried_forward' => [0, 3, 5, 2, 0, 8, 1, 4][$i % 8],
                ]);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Leave, through the real workflow
    |--------------------------------------------------------------------------
    */

    private function leave(Collection $p): void
    {
        $hr = $p['demo.hr'];

        // Taken before, recorded by Human Resource (approved on paper).
        $this->record($p['demo.ict'], 'annual', ...$this->span(-55, 4), ...[$hr, 'Annual leave, approved on paper before the online process.']);
        $this->record($p['demo.maths'], 'sick', ...$this->span(-41, 3), ...[$hr, 'Malaria, sick note from Arua Regional Referral Hospital.']);
        $this->record($p['demo.employee'], 'annual', ...$this->span(-35, 4), ...[$hr, 'Annual leave.']);
        $this->record($p['demo.nurse'], 'annual', ...$this->span(-25, 5), ...[$hr, 'Annual leave.']);
        $this->record($p['demo.hod.maths'], 'study', ...$this->span(-15, 2), ...[$hr, 'Examiners\' meeting at Makerere University.']);

        // Away right now.
        $this->record($p['demo.accountant'], 'annual', ...$this->span(-2, 6), ...[$hr, 'Annual leave.']);

        // Recalled part-way through by the University Secretary.
        [$start, $end] = $this->span(-12, 8);
        $recalled = $this->record($p['demo.hrofficer'], 'annual', $start, $end, $hr, 'Annual leave.');
        LeaveWorkflow::recall($recalled, $p['demo.us'], $this->workingDayFrom($start, 4), 'Needed for the staff audit ahead of Council.');

        // Applied online and fully approved, starting soon.
        $this->approveAll($this->apply($p['demo.hod.nursing'], 'annual', ...$this->span(9, 5), ...['Annual leave with family in Nebbi.']));
        $this->approveAll($this->apply($p['demo.dean.health'], 'study', ...$this->span(14, 3), ...['Presenting at the East African Health Research conference.']));
        $this->approveAll($this->apply($p['demo.admin'], 'annual', ...$this->span(22, 4), ...['Annual leave.']));
        $this->approveAll($this->apply($p['demo.employee'], 'study', ...$this->span(30, 3), ...['Conference paper presentation in Kampala.']));

        // Waiting at every stage, with a demo account as the person who must act.
        $this->apply($p['demo.lecturer'], 'annual', ...$this->span(6, 3), ...['Family visit in Koboko.']);                        // with demo.hod
        $this->apply($p['demo.employee'], 'annual', ...$this->span(18, 5), ...['Graduation ceremony of my sister.']);            // with demo.hod
        LeaveWorkflow::approve($this->apply($p['demo.maths'], 'sick', ...$this->span(8, 2), ...['Minor surgery, appointment confirmed.']), $p['demo.hod.maths'], 'Classes covered by a colleague.'); // with demo.dean
        $this->apply($p['demo.ict'], 'compassionate', ...$this->span(7, 3), ...['Burial of my uncle in Yumbe.']);                 // with demo.hr (no Head in ICT)
        $this->approveUntil($this->apply($p['demo.nurse'], 'compassionate', ...$this->span(12, 2), ...['Burial of a relative.']), Leave::STAGE_HR); // with demo.hr
        $this->approveUntil($this->apply($p['demo.hod'], 'annual', ...$this->span(20, 6), ...['Annual leave.']), Leave::STAGE_US);   // with demo.us

        // Leave across the wider staff: taken earlier, away now, booked ahead.
        $staff = $p->filter(fn ($u, $username) => strpos($username, 'demo.staff') === 0 && !$u->hasAnyRole('hod', 'dean'))->values();
        $plan = [
            [-80, 5, 'annual'], [-70, 3, 'sick'], [-63, 4, 'annual'], [-50, 2, 'study'], [-44, 5, 'annual'], [-38, 3, 'sick'],
            [-30, 4, 'annual'], [-22, 2, 'compassionate'], [-17, 5, 'annual'], [-9, 3, 'sick'], [-6, 4, 'annual'],
            [-3, 7, 'annual'], [-1, 5, 'maternity'], [-4, 6, 'study'],
        ];
        foreach ($plan as $i => [$offset, $length, $type]) {
            $who = $staff[($i * 5 + 2) % $staff->count()];
            [$start, $end] = $this->span($offset, $length);
            $reason = ['annual' => 'Annual leave.', 'sick' => 'Sick leave, medical note attached.', 'study' => 'Workshop at Makerere University.',
                'compassionate' => 'Burial of a relative.', 'maternity' => 'Maternity leave.'][$type];
            if ($type === 'maternity' && $who->sex !== 'Female') {
                $type = 'annual';
                $reason = 'Annual leave.';
            }
            try {
                $this->record($who, $type, $start, $end, $hr, $reason);
            } catch (\Throwable $e) {
                // overlapping or over the balance: skip that one
            }
        }
        foreach ([[4, 3], [11, 5], [16, 2], [27, 6]] as $i => [$in, $length]) {
            $who = $staff[($i * 7 + 4) % $staff->count()];
            try {
                $this->approveAll($this->apply($who, 'annual', ...$this->span($in, $length), ...['Annual leave.']));
            } catch (\Throwable $e) {
                // overlapping or over the balance: skip that one
            }
        }

        // Decided in other ways.
        LeaveWorkflow::reject($this->apply($p['demo.lecturer'], 'annual', ...$this->span(10, 3), ...['Travel.']), $p['demo.hod'],
            'Examinations are running that week; please choose dates after the exams.');
        LeaveWorkflow::withdraw($this->apply($p['demo.hod.maths'], 'annual', ...$this->span(15, 2), ...['Personal.']), $p['demo.hod.maths']);
        LeaveWorkflow::cancel($this->approveAll($this->apply($p['demo.ict'], 'annual', ...$this->span(25, 4), ...['Annual leave.'])), $hr,
            'The ICT systems audit was moved into these dates.');
    }

    private function apply(User $user, string $type, Carbon $start, Carbon $end, string $reason): Leave
    {
        [$days, $year, $errors] = LeaveRules::check($user, $type, $start, $end);
        if ($errors) {
            throw new \RuntimeException("Demo leave for {$user->name} refused: " . implode(' ', $errors));
        }
        $leave = new Leave([
            'user_id' => $user->id, 'leave_type' => $type, 'start_date' => $start, 'end_date' => $end,
            'days' => $days, 'leave_year' => $year, 'return_date' => LeaveRules::returnDateAfter($end),
            'reason' => $reason, 'department_id' => $user->department_id,
            'contact_address' => 'Arua City', 'contact_phone' => '07' . (70 + $user->id % 20) . ' ' . (100 + $user->id * 7 % 900) . ' ' . (100 + $user->id * 13 % 900),
        ]);

        return LeaveWorkflow::submit($leave, $user)->fresh();
    }

    private function record(User $user, string $type, Carbon $start, Carbon $end, User $hr, string $reason): Leave
    {
        [$days, $year, $errors] = LeaveRules::check($user, $type, $start, $end, null, true, true);
        if ($errors) {
            throw new \RuntimeException("Demo HR leave for {$user->name} refused: " . implode(' ', $errors));
        }

        return LeaveWorkflow::recordByHr(new Leave([
            'user_id' => $user->id, 'leave_type' => $type, 'start_date' => $start, 'end_date' => $end, 'days' => $days,
            'leave_year' => $year, 'return_date' => LeaveRules::returnDateAfter($end), 'reason' => $reason,
            'department_id' => $user->department_id,
        ]), $hr)->fresh();
    }

    private function approveAll(Leave $leave): Leave
    {
        return $this->approveUntil($leave, null);
    }

    private function approveUntil(Leave $leave, ?string $stopAt): Leave
    {
        $comments = ['', 'Recommended.', '', 'Verified against the leave plan.', ''];
        $n = 0;
        while ($leave->status === Leave::PENDING && $leave->stage !== $stopAt) {
            $approver = LeaveWorkflow::stageApprovers($leave, $leave->stage)->first();
            if (!$approver) {
                break;
            }
            $leave = LeaveWorkflow::approve($leave, $approver, $comments[$n++ % count($comments)]);
        }

        return $leave;
    }

    /** [first day, last day]: $length working days starting $offset working days from today. */
    private function span(int $offset, int $length): array
    {
        $start = $this->workingDayFrom(today(), $offset);

        return [$start, $this->workingDayFrom($start, $length - 1)];
    }

    private function workingDayFrom(Carbon $day, int $offset): Carbon
    {
        $day = $day->copy();
        $step = $offset < 0 ? -1 : 1;
        while (!$this->calendar->isWorkingDay($day)) {
            $day->addDays($step);
        }
        for ($left = abs($offset); $left > 0;) {
            $day->addDays($step);
            if ($this->calendar->isWorkingDay($day)) {
                $left--;
            }
        }

        return $day;
    }

    /*
    |--------------------------------------------------------------------------
    | Clock-ins
    |--------------------------------------------------------------------------
    */

    /**
     * Add the simulated clock-ins of demo staff for today (up to now) and for
     * any earlier day since the last generated one, then rebuild those days.
     * Safe to run as often as you like.
     */
    public function topUp(): int
    {
        $people = User::where('is_demo', true)->where('status', 'Active')->get();
        if ($people->isEmpty()) {
            return 0;
        }
        $last = DB::table('event_logs')->where('source', DemoManifest::EVENT_SOURCE)->max('event_time');
        $from = $last ? Carbon::parse($last)->startOfDay() : today();
        $from = $from->max(today()->subDays(7));
        $this->calendar = new WorkCalendar($from->copy()->subDay(), today()->addDay());
        foreach ($people as $u) {
            $this->personas[$u->id] = self::personaFor($u->username);
        }

        $added = 0;
        foreach (CarbonPeriod::create($from, today()) as $day) {
            $added += $this->simulate($people, $day);
            app(AttendanceEngine::class)->processDay($day, $people->pluck('id')->all());
        }

        return $added;
    }

    /** Insert one day's clock-ins (those not already there, none in the future). */
    private function simulate(Collection $people, Carbon $day): int
    {
        $date = $day->toDateString();
        $now = now();
        $existing = DB::table('event_logs')->where('source', DemoManifest::EVENT_SOURCE)
            ->whereBetween('event_time', [$date . ' 00:00:00', $date . ' 23:59:59'])->pluck('event_serial')->flip();
        $away = Leave::whereIn('user_id', $people->pluck('id'))
            ->whereIn('status', [Leave::APPROVED, Leave::RECALLED])
            ->where('start_date', '<=', $date)->where('end_date', '>=', $date)->get()
            ->filter(fn (Leave $l) => $l->status !== Leave::RECALLED || !$l->recall_date || Carbon::parse($l->recall_date)->gt($day))
            ->pluck('user_id')->flip();
        [$lh, $lm] = array_map('intval', explode(':', SystemConfiguration::current()->defaultLateTime()));
        $late = $lh * 60 + $lm;

        $rows = [];
        foreach ($people->values() as $person) {
            mt_srand(crc32($person->username . '|' . $date));
            $times = [];
            if (!$this->calendar->isWorkingDayFor($person, $day)) {
                if ($day->isSaturday() && $person->username === 'demo.ict' && mt_rand(1, 100) <= 35) {
                    $times = [$this->at($day, mt_rand(9 * 60 + 5, 9 * 60 + 50)), $this->at($day, mt_rand(13 * 60, 13 * 60 + 50))];
                }
            } elseif (!isset($away[$person->id])) {
                $times = $this->dayFor($this->personas[$person->id] ?? 'punctual', $day, $late);
            }
            foreach ($times as $n => $time) {
                $serial = 'demo-' . $person->id . '-' . $day->format('Ymd') . '-' . $n;
                if ($time->gt($now) || isset($existing[$serial])) {
                    continue;
                }
                $rows[] = [
                    'event_serial' => $serial,
                    'event_time' => $time->format('Y-m-d H:i:s'),
                    'employee_no' => $person->employee_no,
                    'employee_name' => $person->name,
                    'user_id' => $person->id,
                    'device_name' => 'Main Gate Terminal (demo)',
                    'event_type' => 'AccessControllerEvent',
                    'verify_mode' => 'face',
                    'source' => DemoManifest::EVENT_SOURCE,
                    'process_status' => 'processed',
                    'processed_at' => $now,
                    'created_at' => $time,
                    'updated_at' => $now,
                ];
            }
        }
        mt_srand();
        if ($rows) {
            DB::table('event_logs')->insert($rows);
        }

        return count($rows);
    }

    /** @return Carbon[] the day's captures for a persona: arrival (sometimes scanned twice) and departure */
    private function dayFor(string $persona, Carbon $day, int $late): array
    {
        $chance = fn (int $pct) => mt_rand(1, 100) <= $pct;
        $absent = ['punctual' => 2, 'sometimes_late' => 4, 'chronic_late' => 6, 'absentee' => 18, 'no_signout' => 4][$persona] ?? 3;
        if ($chance($absent)) {
            return [];
        }
        $lateChance = ['punctual' => 4, 'sometimes_late' => 28, 'chronic_late' => 65, 'absentee' => 15, 'no_signout' => 10][$persona] ?? 10;
        $arrive = $chance($lateChance)
            ? mt_rand($late + 1, $late + ($persona === 'chronic_late' ? 95 : 45))
            : mt_rand($late - 62, $late - 2);
        $times = [$this->at($day, $arrive)];
        if ($chance(10)) {
            $times[] = $times[0]->copy()->addSeconds(mt_rand(5, 140)); // scanned twice at the door
        }
        if (!$chance($persona === 'no_signout' ? 35 : 2)) {
            $times[] = $this->at($day, mt_rand(16 * 60 + 40, 18 * 60 + 15));
        }

        return $times;
    }

    private function at(Carbon $day, int $minutes): Carbon
    {
        return $day->copy()->setTime(intdiv($minutes, 60), $minutes % 60, mt_rand(0, 59));
    }

    /** Two days corrected by Human Resource, as when a terminal was offline. */
    private function corrections(Collection $people): void
    {
        $hr = $people['demo.hr'];
        $records = DB::table('attendance_records')
            ->whereIn('user_id', $people->pluck('id'))
            ->where('status', 'Absent')->where('is_working_day', 1)
            ->where('attendance_date', '<', today()->subDays(5)->toDateString())
            ->orderBy('attendance_date')->limit(2)->get();
        foreach ($records as $record) {
            DB::table('attendance_records')->where('id', $record->id)->update([
                'status' => 'Present', 'check_in_time' => '07:55:00', 'check_out_time' => '17:05:00', 'hours' => 9.17,
                'is_late' => 'No', 'late_minutes' => 0, 'is_manual' => true, 'corrected_by' => $hr->id, 'corrected_at' => now(),
                'source' => 'manual', 'correction_reason' => 'Main Gate terminal was offline that morning; attendance confirmed by the Head of Department.',
            ]);
        }
    }

    /**
     * The workflow ran in a second; spread it out so the trails read like a
     * real office: submitted days before the leave, a decision a day or two
     * after the one before, nothing in the future.
     */
    private function backdate(array $userIds): void
    {
        $now = now();
        foreach (Leave::whereIn('user_id', $userIds)->with('actions')->orderBy('id')->get() as $k => $leave) {
            mt_srand(7919 * ($k + 1));
            $start = $leave->start_date->copy()->setTime(9, 0);
            $submitted = $leave->status === Leave::PENDING
                ? $now->copy()->subDays(mt_rand(1, 5))->setTime(mt_rand(8, 16), mt_rand(0, 59))
                : $start->copy()->subDays(mt_rand(6, 14))->min($now->copy()->subDays(mt_rand(4, 9)))->setTime(mt_rand(8, 16), mt_rand(0, 59));
            $submitted = $submitted->min($now->copy()->subHours(2));
            $at = $submitted->copy();
            foreach ($leave->actions as $i => $action) {
                if ($i > 0 && $action->action !== LeaveAction::SKIPPED) {
                    $at = $at->copy()->addDays(mt_rand(0, 2))->addHours(mt_rand(1, 6))->min($now->copy()->subMinutes(mt_rand(20, 90)));
                }
                $action->timestamps = false;
                $action->created_at = $at;
                $action->save();
            }
            $leave->timestamps = false;
            $leave->submitted_at = $submitted;
            if ($leave->decided_at) {
                $leave->decided_at = $at;
            }
            if ($leave->recalled_at) {
                $leave->recalled_at = $at;
            }
            $leave->save();
        }
        mt_srand();

        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $userIds)->orderBy('created_at')->get(['id'])
            ->each(function ($note, $i) use ($now) {
                $when = $now->copy()->subMinutes(15 + ($i * 997) % (5 * 24 * 60));
                DB::table('notifications')->where('id', $note->id)->update(['created_at' => $when, 'updated_at' => $when]);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Purge
    |--------------------------------------------------------------------------
    */

    /**
     * Remove the sandbox completely: its people and everything about them,
     * its departments and faculties. Nothing of the real university is touched.
     *
     * @param  int[]|null  $onlyUserIds  remove just these demo accounts
     */
    public static function purge(?array $onlyUserIds = null): int
    {
        $ids = User::where('is_demo', true)
            ->when($onlyUserIds !== null, fn ($q) => $q->whereIn('id', $onlyUserIds ?: [0]))
            ->pluck('id')->all();

        DB::transaction(function () use ($ids, $onlyUserIds) {
            $leaveIds = DB::table('leaves')->whereIn('user_id', $ids)->pluck('id')->all();
            DB::table('leave_actions')->whereIn('leave_id', $leaveIds)->delete();
            DB::table('leaves')->whereIn('id', $leaveIds)->delete();
            DB::table('leaves')->whereIn('acting_user_id', $ids)->update(['acting_user_id' => null]);
            DB::table('leave_entitlements')->whereIn('user_id', $ids)->delete();
            DB::table('attendance_records')->whereIn('user_id', $ids)->delete();
            DB::table('event_logs')->whereIn('user_id', $ids)->delete();
            if ($onlyUserIds === null) {
                DB::table('event_logs')->where('source', DemoManifest::EVENT_SOURCE)->delete();
            }
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $ids)->delete();
            DB::table('audit_logs')->whereIn('user_id', $ids)->delete();
            DB::table('audit_logs')->where('subject_type', Leave::class)->whereIn('subject_id', $leaveIds)->delete();
            if (\Illuminate\Support\Facades\Schema::hasTable('admin_operation_log')) {
                DB::table('admin_operation_log')->whereIn('user_id', $ids)->delete();
            }
            DB::table('departments')->whereIn('hod_id', $ids)->update(['hod_id' => null]);
            DB::table('faculties')->whereIn('dean_id', $ids)->update(['dean_id' => null]);
            DB::table('admin_role_users')->whereIn('user_id', $ids)->delete();
            DB::table('users')->whereIn('id', $ids)->delete();

            if ($onlyUserIds === null) {
                DB::table('departments')->where('is_demo', true)->delete();
                DB::table('faculties')->where('is_demo', true)->delete();
            }
        });

        return count($ids);
    }
}
