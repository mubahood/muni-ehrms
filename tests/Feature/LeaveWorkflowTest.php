<?php

namespace Tests\Feature;

use App\Exceptions\LeaveActionRefused;
use App\Models\Department;
use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\LeaveEntitlement;
use App\Models\User;
use App\Services\LeaveRules;
use App\Services\LeaveWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The leave process end to end. Today is Wednesday 7 October 2026; the leave
 * year runs July–June, so this is leave year 2026 ("2026/27").
 */
class LeaveWorkflowTest extends TestCase
{
    private User $hodCs;
    private User $dean;
    private User $hodFinance;
    private User $hr;
    private User $us;
    private User $lecturer;
    private User $accountant;
    private Department $cs;
    private Department $finance;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $science = $this->makeFaculty(['name' => 'Faculty of Science']);
        $this->cs = $this->makeDepartment(['name' => 'Computer Science', 'type' => Department::ACADEMIC, 'faculty_id' => $science->id]);
        $this->finance = $this->makeDepartment(['name' => 'Finance']);
        $hrDept = $this->makeDepartment(['name' => 'Human Resource']);

        $this->dean = $this->makeUser(['name' => 'Dean Science', 'department_id' => $this->cs->id], ['dean']);
        $this->hodCs = $this->makeUser(['name' => 'Head CS', 'department_id' => $this->cs->id], ['hod']);
        $this->hodFinance = $this->makeUser(['name' => 'Head Finance', 'department_id' => $this->finance->id], ['hod']);
        $this->hr = $this->makeUser(['name' => 'HR Officer', 'department_id' => $hrDept->id], ['hr']);
        $this->us = $this->makeUser(['name' => 'University Secretary', 'department_id' => $hrDept->id], ['us']);
        $this->lecturer = $this->makeUser(['name' => 'Lecturer One', 'department_id' => $this->cs->id]);
        $this->accountant = $this->makeUser(['name' => 'Accountant One', 'department_id' => $this->finance->id]);

        $science->update(['dean_id' => $this->dean->id]);
        $this->cs->update(['hod_id' => $this->hodCs->id]);
        $this->finance->update(['hod_id' => $this->hodFinance->id]);

        foreach (User::all() as $user) {
            LeaveEntitlement::create(['user_id' => $user->id, 'leave_year' => 2026, 'days_due' => 30, 'carried_forward' => 0]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    public function test_routes_follow_the_university_form()
    {
        $this->assertSame(['hod', 'dean', 'hr', 'us'], LeaveWorkflow::buildRoute($this->lecturer), 'academic');
        $this->assertSame(['hod', 'hr', 'us'], LeaveWorkflow::buildRoute($this->accountant), 'administrative');
        $this->assertSame(['dean', 'hr', 'us'], LeaveWorkflow::buildRoute($this->hodCs), 'a Head skips their own stage');
        $this->assertSame(['hr', 'us'], LeaveWorkflow::buildRoute($this->dean), 'a Dean skips Head and Dean');
        $this->assertSame(['hr', 'us'], LeaveWorkflow::buildRoute($this->hodFinance), 'administrative Head');
    }

    public function test_full_approval_of_an_academic_request()
    {
        $leave = $this->apply($this->lecturer, 'annual', '2026-10-12', '2026-10-16');
        $this->assertSame('hod', $leave->stage);
        $this->assertMatchesRegularExpression('/^LV-2026-\d{5}$/', $leave->reference);
        $this->assertSame(5, $leave->days);
        $this->assertSame('2026-10-19', $leave->return_date->toDateString(), 'Friday leave → back on Monday');

        $leave = LeaveWorkflow::approve($leave, $this->hodCs, 'Lectures covered');
        $this->assertSame('dean', $leave->stage);
        $leave = LeaveWorkflow::approve($leave, $this->dean);
        $this->assertSame('hr', $leave->stage);
        $leave = LeaveWorkflow::approve($leave, $this->hr);
        $this->assertSame('us', $leave->stage);
        $this->assertSame(30, (int) $leave->hr_days_due, 'Section II frozen by HR');
        $this->assertSame(30, (int) $leave->hr_balance);
        $leave = LeaveWorkflow::approve($leave, $this->us, 'Enjoy');

        $this->assertSame(Leave::APPROVED, $leave->status);
        $this->assertNull($leave->stage);
        $this->assertSame(
            ['submitted', 'recommended', 'recommended', 'verified', 'approved'],
            $leave->actions()->pluck('action')->all()
        );
        $balance = LeaveRules::annualBalance($this->lecturer, 2026);
        $this->assertSame(5, $balance->taken);
        $this->assertSame(25, $balance->balance());
    }

    public function test_each_step_notifies_the_right_people()
    {
        $leave = $this->apply($this->accountant, 'sick', '2026-10-12', '2026-10-13');
        $this->assertNotified($this->hodFinance, 'awaiting your action');
        $this->assertNotified($this->accountant, 'submitted');
        $this->assertNotNotified($this->hr);

        LeaveWorkflow::approve($leave, $this->hodFinance);
        $this->assertNotified($this->hr, 'awaiting your action');
        $this->assertNotified($this->accountant, 'moved to the Human Resource Office');

        LeaveWorkflow::approve($leave->fresh(), $this->hr);
        $this->assertNotified($this->us, 'awaiting your action');

        LeaveWorkflow::approve($leave->fresh(), $this->us);
        $this->assertNotified($this->accountant, 'Leave approved');
        $this->assertNotified($this->hodFinance, 'Leave approved – Accountant One');
        Mail::assertNothingSent();
    }

    public function test_vacant_head_and_dean_are_passed_with_a_note_in_the_trail()
    {
        $this->cs->update(['hod_id' => null]);
        $this->hodCs->roles()->detach();
        $faculty = $this->cs->faculty;
        $faculty->update(['dean_id' => null]);

        $leave = $this->apply($this->lecturer, 'study', '2026-10-12', '2026-10-13');

        $this->assertSame('hr', $leave->stage);
        $this->assertSame(2, $leave->actions()->where('action', LeaveAction::SKIPPED)->count());
        $states = array_column(LeaveWorkflow::timeline($leave), 'state');
        $this->assertSame(['done', 'skipped', 'skipped', 'current', 'waiting'], $states);
    }

    public function test_human_resource_and_secretary_stages_wait_and_alert_the_administrator()
    {
        $admin = $this->makeUser([], ['admin']);
        $this->hr->roles()->detach();

        $leave = $this->apply($this->accountant, 'sick', '2026-10-12', '2026-10-12');
        LeaveWorkflow::approve($leave, $this->hodFinance);

        $this->assertSame('hr', $leave->fresh()->stage, 'HR is never skipped');
        $this->assertNotified($admin, 'No Human Resource Office account');
    }

    /*
    |--------------------------------------------------------------------------
    | Who may act
    |--------------------------------------------------------------------------
    */

    public function test_only_the_officer_at_the_current_stage_can_act()
    {
        $leave = $this->apply($this->lecturer, 'sick', '2026-10-12', '2026-10-12');

        foreach ([$this->hodFinance, $this->dean, $this->hr, $this->us, $this->accountant] as $wrong) {
            try {
                LeaveWorkflow::approve($leave, $wrong);
                $this->fail("{$wrong->name} acted at the Head of Department stage");
            } catch (LeaveActionRefused $e) {
                $this->assertTrue($e->forbidden);
            }
        }
        $this->assertSame('hod', $leave->fresh()->stage);
    }

    public function test_nobody_acts_on_their_own_request()
    {
        $otherHr = $this->makeUser(['name' => 'HR Two'], ['hr']);
        $leave = $this->apply($this->hr, 'sick', '2026-10-12', '2026-10-12'); // HR's own Head is vacant → HR stage
        $this->assertSame('hr', $leave->stage);

        $this->assertFalse(LeaveWorkflow::canAct($this->hr, $leave));
        $this->assertTrue(LeaveWorkflow::canAct($otherHr, $leave));
        $this->assertFalse(LeaveWorkflow::pendingFor($this->hr)->where('leaves.id', $leave->id)->exists());
        $this->assertTrue(LeaveWorkflow::pendingFor($otherHr)->where('leaves.id', $leave->id)->exists());
    }

    public function test_approval_queues_hold_only_what_waits_for_each_person()
    {
        $academic = $this->apply($this->lecturer, 'sick', '2026-10-12', '2026-10-12');
        $admin = $this->apply($this->accountant, 'sick', '2026-10-12', '2026-10-12');

        $this->assertSame([$academic->id], LeaveWorkflow::pendingFor($this->hodCs)->pluck('leaves.id')->all());
        $this->assertSame([$admin->id], LeaveWorkflow::pendingFor($this->hodFinance)->pluck('leaves.id')->all());
        $this->assertSame([], LeaveWorkflow::pendingFor($this->dean)->pluck('leaves.id')->all());
        $this->assertSame([], LeaveWorkflow::pendingFor($this->lecturer)->pluck('leaves.id')->all());

        LeaveWorkflow::approve($academic, $this->hodCs);
        $this->assertSame([$academic->id], LeaveWorkflow::pendingFor($this->dean)->pluck('leaves.id')->all());
    }

    /*
    |--------------------------------------------------------------------------
    | Declining, withdrawing, cancelling, recalling
    |--------------------------------------------------------------------------
    */

    public function test_declining_needs_a_reason_and_ends_the_request_at_any_stage()
    {
        foreach (['hod' => 0, 'dean' => 1, 'hr' => 2, 'us' => 3] as $stage => $approvalsFirst) {
            $leave = $this->apply($this->lecturer, 'sick', '2026-10-12', '2026-10-12');
            $chain = [$this->hodCs, $this->dean, $this->hr, $this->us];
            for ($i = 0; $i < $approvalsFirst; $i++) {
                $leave = LeaveWorkflow::approve($leave, $chain[$i]);
            }
            $this->assertSame($stage, $leave->stage);

            try {
                LeaveWorkflow::reject($leave, $chain[$approvalsFirst], '   ');
                $this->fail('declined without a reason');
            } catch (LeaveActionRefused $e) {
                $this->assertFalse($e->forbidden);
            }

            $leave = LeaveWorkflow::reject($leave, $chain[$approvalsFirst], 'Exams week');
            $this->assertSame(Leave::REJECTED, $leave->status, "declined at {$stage}");
            $this->assertNotified($this->lecturer, 'not approved');
            $leave->delete();
        }
    }

    public function test_only_the_applicant_withdraws_and_only_while_pending()
    {
        $leave = $this->apply($this->accountant, 'sick', '2026-10-12', '2026-10-12');

        $this->expectRefusal(fn () => LeaveWorkflow::withdraw($leave, $this->hodFinance));
        $leave = LeaveWorkflow::withdraw($leave, $this->accountant, 'Plans changed');
        $this->assertSame(Leave::WITHDRAWN, $leave->status);
        $this->assertNotified($this->hodFinance, 'withdrawn');
        $this->expectRefusal(fn () => LeaveWorkflow::withdraw($leave, $this->accountant));
    }

    public function test_cancelling_approved_leave_before_it_starts_restores_every_day()
    {
        $leave = $this->approveFully($this->apply($this->accountant, 'annual', '2026-10-12', '2026-10-16'));
        $this->assertSame(5, LeaveRules::annualBalance($this->accountant, 2026)->taken);

        $this->expectRefusal(fn () => LeaveWorkflow::cancel($leave, $this->hodFinance, 'No'));
        $this->expectRefusal(fn () => LeaveWorkflow::cancel($leave, $this->hr, ''));
        $leave = LeaveWorkflow::cancel($leave, $this->hr, 'Department audit moved forward');

        $this->assertSame(Leave::CANCELLED, $leave->status);
        $this->assertSame(0, LeaveRules::annualBalance($this->accountant, 2026)->taken);
    }

    public function test_started_leave_cannot_be_cancelled_only_recalled()
    {
        $leave = $this->recordPast($this->accountant, 'annual', '2026-10-05', '2026-10-09');

        $this->expectRefusal(fn () => LeaveWorkflow::cancel($leave, $this->hr, 'Too late'));
    }

    public function test_recall_restores_unused_days_and_rebuilds_attendance()
    {
        $this->holiday('2026-10-09', 'Independence Day');
        $leave = $this->recordPast($this->accountant, 'annual', '2026-09-28', '2026-10-09');
        $this->assertSame(9, $leave->days, '28 Sep – 9 Oct: ten weekdays less Independence Day');
        $this->assertSame('On Leave', $this->record($this->accountant, '2026-10-05')->status);

        $this->expectRefusal(fn () => LeaveWorkflow::recall($leave, $this->hodFinance, '2026-10-05', 'Audit'));
        $this->expectRefusal(fn () => LeaveWorkflow::recall($leave, $this->hr, '2026-09-28', 'Audit'));
        $this->expectRefusal(fn () => LeaveWorkflow::recall($leave, $this->hr, '2026-10-12', 'Audit'));

        $leave = LeaveWorkflow::recall($leave, $this->us, '2026-10-05', 'External audit');

        $this->assertSame(Leave::RECALLED, $leave->status);
        $this->assertSame(4, $leave->days_restored, '5–8 Oct (9th is a holiday)');
        $this->assertSame(5, $leave->daysTaken());
        $this->assertSame('On Leave', $this->record($this->accountant, '2026-10-02')->status, 'before the recall');
        $this->assertSame('Absent', $this->record($this->accountant, '2026-10-05')->status, 'expected back from the resume date');
        $this->assertSame(5, LeaveRules::annualBalance($this->accountant, 2026)->taken);
        $this->assertNotified($this->accountant, 'resume duty');
        $this->assertNotified($this->hodFinance, 'recalled from leave');
    }

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    */

    public function test_annual_leave_is_limited_by_the_allocation_and_pending_requests_reserve_days()
    {
        LeaveEntitlement::where('user_id', $this->accountant->id)->update(['days_due' => 8, 'carried_forward' => 2]);

        [$exact, , $errors] = LeaveRules::check($this->accountant, 'annual', '2026-10-12', '2026-10-23');
        $this->assertSame(10, $exact);
        $this->assertSame([], $errors, 'exactly the allocation is allowed');

        [, , $errors] = LeaveRules::check($this->accountant, 'annual', '2026-10-12', '2026-10-26');
        $this->assertStringContainsString('11 working days were requested but only 10 annual leave days remain', $errors[0] ?? '');

        $this->apply($this->accountant, 'annual', '2026-10-12', '2026-10-17'); // 5 days, pending
        [, , $errors] = LeaveRules::check($this->accountant, 'annual', '2026-11-02', '2026-11-09'); // 6 days
        $this->assertStringContainsString('only 5 annual leave days remain', $errors[0] ?? '');

        [, , $errors] = LeaveRules::check($this->accountant, 'sick', '2026-11-02', '2026-11-30');
        $this->assertSame([], $errors, 'other types are not limited by the annual allocation');
    }

    public function test_no_allocation_means_no_annual_leave()
    {
        LeaveEntitlement::where('user_id', $this->accountant->id)->delete();
        [, , $errors] = LeaveRules::check($this->accountant, 'annual', '2026-10-12', '2026-10-12');

        $this->assertStringContainsString('No annual leave has been allocated', $errors[0]);
    }

    public function test_balance_is_checked_again_when_hr_and_the_secretary_act()
    {
        $leave = $this->apply($this->accountant, 'annual', '2026-10-12', '2026-10-16');
        LeaveWorkflow::approve($leave, $this->hodFinance);
        LeaveEntitlement::where('user_id', $this->accountant->id)->update(['days_due' => 3]);

        $this->expectRefusal(fn () => LeaveWorkflow::approve($leave->fresh(), $this->hr), false);
        $this->assertSame('hr', $leave->fresh()->stage);
    }

    public function test_overlapping_requests_are_refused()
    {
        $this->apply($this->accountant, 'sick', '2026-10-12', '2026-10-14');

        [, , $errors] = LeaveRules::check($this->accountant, 'study', '2026-10-14', '2026-10-20');
        $this->assertStringContainsString('overlap', $errors[0]);

        [, , $errors] = LeaveRules::check($this->accountant, 'study', '2026-10-15', '2026-10-20');
        $this->assertSame([], $errors, 'the day after is free');
    }

    public function test_applicants_cannot_start_in_the_past_but_hr_can_record_it()
    {
        [, , $applicant] = LeaveRules::check($this->accountant, 'sick', '2026-10-05', '2026-10-06');
        [, , $hr] = LeaveRules::check($this->accountant, 'sick', '2026-10-05', '2026-10-06', null, true, true);

        $this->assertStringContainsString('cannot start in the past', $applicant[0]);
        $this->assertSame([], $hr);
    }

    public function test_annual_leave_cannot_cross_the_leave_year()
    {
        LeaveEntitlement::create(['user_id' => $this->accountant->id, 'leave_year' => 2027, 'days_due' => 30, 'carried_forward' => 0]);
        [, , $errors] = LeaveRules::check($this->accountant, 'annual', '2027-06-28', '2027-07-02');

        $this->assertStringContainsString('within one leave year', implode(' ', $errors));
    }

    public function test_days_and_return_date_skip_weekends_and_holidays()
    {
        $this->holiday('2026-10-09', 'Independence Day');

        $this->assertSame(2, LeaveRules::workingDays('2026-10-07', '2026-10-11'), 'Wed, Thu; Fri is a holiday; then the weekend');
        $this->assertSame('2026-10-12', LeaveRules::returnDateAfter('2026-10-08')->toDateString());
        [$days, , $errors] = LeaveRules::check($this->accountant, 'sick', '2026-10-10', '2026-10-11');
        $this->assertSame(0, $days);
        $this->assertStringContainsString('no working days', $errors[0]);
    }

    public function test_employees_cannot_apply_for_types_only_hr_records()
    {
        [, , $applicant] = LeaveRules::check($this->accountant, 'official', '2026-10-12', '2026-10-12');
        [, , $hr] = LeaveRules::check($this->accountant, 'official', '2026-10-12', '2026-10-12', null, false, true);

        $this->assertNotEmpty($applicant);
        $this->assertSame([], $hr);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function apply(User $user, string $type, string $from, string $to): Leave
    {
        [$days, $year, $errors] = LeaveRules::check($user, $type, $from, $to);
        $this->assertSame([], $errors, "application {$type} {$from}–{$to} for {$user->name}");

        $leave = new Leave([
            'user_id' => $user->id, 'leave_type' => $type, 'start_date' => $from, 'end_date' => $to,
            'days' => $days, 'leave_year' => $year, 'return_date' => LeaveRules::returnDateAfter($to),
            'reason' => 'Test', 'department_id' => $user->department_id,
        ]);
        $leave->setRelation('user', $user);

        return LeaveWorkflow::submit($leave, $user)->fresh();
    }

    private function recordPast(User $user, string $type, string $from, string $to): Leave
    {
        [$days, $year, $errors] = LeaveRules::check($user, $type, $from, $to, null, true, true);
        $this->assertSame([], $errors);
        $leave = new Leave([
            'user_id' => $user->id, 'leave_type' => $type, 'start_date' => $from, 'end_date' => $to,
            'days' => $days, 'leave_year' => $year, 'return_date' => LeaveRules::returnDateAfter($to), 'reason' => 'On paper',
        ]);

        return LeaveWorkflow::recordByHr($leave, $this->hr)->fresh();
    }

    private function approveFully(Leave $leave): Leave
    {
        while ($leave->status === Leave::PENDING) {
            $approver = LeaveWorkflow::stageApprovers($leave, $leave->stage)->first();
            $leave = LeaveWorkflow::approve($leave, $approver);
        }

        return $leave;
    }

    private function expectRefusal(callable $action, ?bool $forbidden = null): void
    {
        try {
            $action();
            $this->fail('the action was allowed');
        } catch (LeaveActionRefused $e) {
            if ($forbidden !== null) {
                $this->assertSame($forbidden, $e->forbidden, $e->getMessage());
            }
            $this->addToAssertionCount(1);
        }
    }

    private function notificationsFor(User $user): array
    {
        return DB::table('notifications')->where('notifiable_id', $user->id)->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['title'] . ' | ' . json_decode($d, true)['body'])->all();
    }

    private function assertNotified(User $user, string $text): void
    {
        $all = implode("\n", $this->notificationsFor($user));
        $this->assertStringContainsStringIgnoringCase($text, $all, "{$user->name} should have been told \"{$text}\"");
    }

    private function assertNotNotified(User $user): void
    {
        $this->assertSame([], $this->notificationsFor($user), "{$user->name} should not have been notified");
    }
}
