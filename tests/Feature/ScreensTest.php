<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\User;
use App\Services\AttendanceEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The screens, driven over HTTP as people use them.
 */
class ScreensTest extends TestCase
{
    private Department $cs;
    private Department $finance;
    private User $hod;
    private User $dean;
    private User $hr;
    private User $us;
    private User $admin;
    private User $lecturer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $faculty = $this->makeFaculty(['name' => 'Faculty of Science']);
        $this->cs = $this->makeDepartment(['name' => 'Computer Science', 'type' => Department::ACADEMIC, 'faculty_id' => $faculty->id]);
        $this->finance = $this->makeDepartment(['name' => 'Finance']);
        $this->hod = $this->makeUser(['name' => 'Head CS', 'department_id' => $this->cs->id], ['hod']);
        $this->dean = $this->makeUser(['name' => 'Dean Science', 'department_id' => $this->cs->id], ['dean']);
        $this->hr = $this->makeUser(['name' => 'HR Officer'], ['hr']);
        $this->us = $this->makeUser(['name' => 'The Secretary'], ['us']);
        $this->admin = $this->makeUser(['name' => 'Sys Admin'], ['admin']);
        $this->lecturer = $this->makeUser(['name' => 'Lecturer Lee', 'department_id' => $this->cs->id]);
        $this->outsider = $this->makeUser(['name' => 'Finance Fiona', 'department_id' => $this->finance->id]);
        $this->cs->update(['hod_id' => $this->hod->id]);
        $faculty->update(['dean_id' => $this->dean->id]);
        foreach (User::all() as $u) {
            LeaveEntitlement::create(['user_id' => $u->id, 'leave_year' => 2026, 'days_due' => 30, 'carried_forward' => 0]);
        }
    }

    public function test_every_role_gets_a_working_dashboard()
    {
        app(AttendanceEngine::class)->processRange('2026-10-01', '2026-10-07');
        foreach ([$this->admin, $this->hr, $this->us, $this->dean, $this->hod, $this->lecturer] as $user) {
            $this->actingAs($user, 'admin')->get('/')->assertOk();
            $this->actingAs($user, 'admin')->get('/me')->assertOk()->assertSee('Annual leave');
        }
        $this->actingAs($this->lecturer, 'admin')->get('/')->assertSee('My attendance');
        $this->actingAs($this->hr, 'admin')->get('/')->assertSee('Whole university');
    }

    public function test_a_request_travels_the_whole_route_over_http()
    {
        $this->actingAs($this->lecturer, 'admin')->post('/my-leave', [
            'leave_type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16',
            'reason' => 'Rest with family in Koboko', 'contact_phone' => '0772 000 000',
        ])->assertRedirect();
        $leave = Leave::where('user_id', $this->lecturer->id)->firstOrFail();
        $this->assertSame('hod', $leave->stage);

        $this->actingAs($this->dean, 'admin')->post("/leave/{$leave->id}/approve")->assertRedirect();
        $this->assertSame('hod', $leave->fresh()->stage, 'the Dean cannot act at the Head stage');

        foreach ([[$this->hod, 'dean'], [$this->dean, 'hr'], [$this->hr, 'us'], [$this->us, null]] as [$who, $next]) {
            $this->actingAs($who, 'admin')->get("/leave/{$leave->id}")->assertOk()->assertSee('This request is waiting for you');
            $this->actingAs($who, 'admin')->post("/leave/{$leave->id}/approve", ['comment' => 'OK'])->assertRedirect("/leave/{$leave->id}");
            $this->assertSame($next, $leave->fresh()->stage);
        }
        $this->assertSame(Leave::APPROVED, $leave->fresh()->status);

        $pdf = $this->actingAs($this->lecturer, 'admin')->get("/leave/{$leave->id}/form.pdf");
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_application_errors_are_explained_on_the_form()
    {
        $this->actingAs($this->lecturer, 'admin')
            ->from('/my-leave/apply')
            ->post('/my-leave', ['leave_type' => 'annual', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'reason' => 'Late'])
            ->assertRedirect('/my-leave/apply')
            ->assertSessionHasErrors('dates');
        $this->assertSame(0, Leave::count());
    }

    public function test_the_live_check_counts_days_and_balance()
    {
        $this->actingAs($this->lecturer, 'admin')
            ->getJson('/my-leave/check?leave_type=annual&start_date=2026-10-12&end_date=2026-10-23')
            ->assertOk()->assertJson(['days' => 10, 'available' => 30, 'after' => 20, 'errors' => []]);
    }

    public function test_requests_are_private_to_the_people_concerned()
    {
        $this->actingAs($this->lecturer, 'admin')->post('/my-leave', [
            'leave_type' => 'sick', 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Clinic',
        ]);
        $leave = Leave::firstOrFail();

        $this->actingAs($this->outsider, 'admin')->get("/leave/{$leave->id}")->assertStatus(403);
        $this->actingAs($this->outsider, 'admin')->get("/leave/{$leave->id}/form.pdf")->assertStatus(403);
        $this->actingAs($this->hod, 'admin')->get("/leave/{$leave->id}")->assertOk();
        $this->actingAs($this->hr, 'admin')->get("/leave/{$leave->id}")->assertOk();
    }

    public function test_reports_are_limited_to_the_viewers_scope()
    {
        app(AttendanceEngine::class)->processRange('2026-10-01', '2026-10-07');

        $own = $this->actingAs($this->hod, 'admin')->get("/reports/summary.pdf?scope=department:{$this->cs->id}&period=this_month");
        $own->assertOk();
        $this->assertStringStartsWith('%PDF', $own->getContent());
        $this->actingAs($this->hod, 'admin')->get("/reports/summary.pdf?scope=department:{$this->finance->id}")->assertStatus(403);
        $this->actingAs($this->hod, 'admin')->get('/reports/summary.pdf?scope=university')->assertStatus(403);
        $this->actingAs($this->hr, 'admin')->get('/reports/summary.pdf?scope=university')->assertOk();

        $this->actingAs($this->lecturer, 'admin')->get('/reports/summary.pdf?scope=university')->assertStatus(403);
        $this->actingAs($this->lecturer, 'admin')->get("/reports/individual.pdf?user={$this->lecturer->id}")->assertOk();
        $this->actingAs($this->lecturer, 'admin')->get("/reports/individual.pdf?user={$this->outsider->id}")->assertStatus(403);

        foreach (['daily', 'leave'] as $type) {
            $this->assertStringStartsWith('%PDF', $this->actingAs($this->hr, 'admin')->get("/reports/{$type}.pdf?scope=university")->getContent());
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.downloaded', 'user_id' => $this->hr->id]);
    }

    public function test_hr_plans_leave_for_a_year()
    {
        LeaveEntitlement::query()->delete();

        $this->actingAs($this->hr, 'admin')->post('/leave/planning', [
            'mode' => 'bulk', 'year' => 2026, 'bulk_days' => 24, 'bulk_department' => $this->cs->id,
        ])->assertRedirect();
        $this->assertSame(24, LeaveEntitlement::where('user_id', $this->lecturer->id)->value('days_due'));
        $this->assertNull(LeaveEntitlement::where('user_id', $this->outsider->id)->first(), 'only Computer Science');

        LeaveEntitlement::create(['user_id' => $this->outsider->id, 'leave_year' => 2025, 'days_due' => 30, 'carried_forward' => 0]);
        $this->actingAs($this->hr, 'admin')->post('/leave/planning', ['mode' => 'carry', 'year' => 2026, 'carry_cap' => 10])->assertRedirect();
        $this->assertSame(10, LeaveEntitlement::where('user_id', $this->outsider->id)->where('leave_year', 2026)->value('carried_forward'), '30 unused, capped at 10');

        $this->actingAs($this->hod, 'admin')->get('/leave/planning')->assertStatus(403);
    }

    public function test_hr_records_past_leave_and_attendance_follows()
    {
        app(AttendanceEngine::class)->processRange('2026-10-05', '2026-10-06');
        $this->assertSame('Absent', $this->record($this->outsider, '2026-10-05')->status);

        $this->actingAs($this->hr, 'admin')->post('/leave/record', [
            'user_id' => $this->outsider->id, 'leave_type' => 'sick', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'reason' => 'Medical form 114',
        ])->assertRedirect();

        $this->assertSame('On Leave', $this->record($this->outsider, '2026-10-05')->status);
    }

    public function test_changing_the_late_time_recalculates_this_month()
    {
        $this->clockIn($this->lecturer, '2026-10-05 08:20:00', '2026-10-05 17:00:00');
        app(AttendanceEngine::class)->processDay('2026-10-05');
        $this->assertSame('No', $this->record($this->lecturer, '2026-10-05')->is_late);

        $this->actingAs($this->admin, 'admin')->put('/settings', [
            'company_name' => 'Muni University', 'system_name' => 'EHRMS', 'company_address' => 'P.O. Box 725 Arua, Uganda',
            'late_time' => '08:00', 'working_days' => [1, 2, 3, 4, 5], 'full_day_hours' => 8, 'repeat_capture_minutes' => 30,
            'start_date' => '2026-01-01', 'leave_year_start_month' => 7, 'annual_leave_days' => 30,
        ])->assertRedirect('/settings');

        $this->assertSame('Yes', $this->record($this->lecturer, '2026-10-05')->is_late);
        $this->assertSame(20, (int) $this->record($this->lecturer, '2026-10-05')->late_minutes);
        $this->actingAs($this->hr, 'admin')->get('/settings')->assertStatus(403);
    }

    public function test_notifications_open_and_clear()
    {
        $this->actingAs($this->lecturer, 'admin')->post('/my-leave', [
            'leave_type' => 'sick', 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Clinic',
        ]);
        $this->actingAs($this->hod, 'admin')->getJson('/notifications/summary')->assertJson(['unread' => 1]);
        $note = $this->hod->notifications()->first();

        $this->actingAs($this->hod, 'admin')->get("/notifications/{$note->id}")->assertRedirect(url('leave/' . Leave::first()->id));
        $this->actingAs($this->hod, 'admin')->getJson('/notifications/summary')->assertJson(['unread' => 0]);
        $this->actingAs($this->outsider, 'admin')->get("/notifications/{$note->id}")->assertNotFound();
    }

    public function test_sign_ins_are_audited()
    {
        $this->post('/auth/login', ['username' => $this->lecturer->username, 'password' => 'wrong-password'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.failed']);
        $this->post('/auth/login', ['username' => $this->lecturer->username, 'password' => 'Secret@2026'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $this->lecturer->id]);

        $this->actingAs($this->hr, 'admin')->get('/audit-log')->assertOk()->assertSee('Failed sign-in');
    }

    public function test_the_verification_command_finds_nothing_wrong()
    {
        $this->clockIn($this->lecturer, '2026-10-05 08:40:00', '2026-10-05 17:00:00');
        app(AttendanceEngine::class)->processRange('2026-09-01', '2026-10-07');

        $this->artisan('ehrms:verify --everyone --from=2026-09-01')->expectsOutput('Everything adds up.')->assertExitCode(0);

        DB::table('attendance_records')->where('user_id', $this->lecturer->id)->where('attendance_date', '2026-10-05')->update(['late_minutes' => 3]);
        $this->artisan('ehrms:verify --everyone --from=2026-09-01')->assertExitCode(1);
    }

    public function test_people_search_stays_within_scope()
    {
        $ids = fn ($response) => collect($response->json('results'))->pluck('id')->all();

        $hod = $this->actingAs($this->hod, 'admin')->getJson('/lookup/people?q=')->assertOk();
        $this->assertContains($this->lecturer->id, $ids($hod));
        $this->assertNotContains($this->outsider->id, $ids($hod), 'a Head never finds staff outside their department');

        $this->assertContains($this->outsider->id, $ids($this->actingAs($this->hr, 'admin')->getJson('/lookup/people?q=Fiona')));
        $this->assertSame([$this->lecturer->id], $ids($this->actingAs($this->lecturer, 'admin')->getJson('/lookup/people')), 'an employee finds only themselves');

        $colleagues = $ids($this->actingAs($this->lecturer, 'admin')->getJson('/lookup/people?colleagues=1'));
        $this->assertContains($this->hod->id, $colleagues);
        $this->assertNotContains($this->lecturer->id, $colleagues);
        $this->assertNotContains($this->outsider->id, $colleagues);
    }

    public function test_leave_actions_answer_in_json_for_the_modals()
    {
        $this->actingAs($this->lecturer, 'admin')->postJson('/my-leave', [
            'leave_type' => 'sick', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'reason' => 'Past',
        ])->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['start_date']]);

        $this->actingAs($this->lecturer, 'admin')->postJson('/my-leave', [
            'leave_type' => 'sick', 'start_date' => '2026-10-12', 'end_date' => '2026-10-12', 'reason' => 'Clinic',
        ])->assertOk()->assertJsonStructure(['message', 'redirect']);
        $leave = Leave::firstOrFail();

        $this->actingAs($this->hod, 'admin')->postJson("/leave/{$leave->id}/reject", ['comment' => ''])->assertStatus(422);
        $this->actingAs($this->dean, 'admin')->postJson("/leave/{$leave->id}/approve")->assertStatus(403);
        $this->actingAs($this->hod, 'admin')->postJson("/leave/{$leave->id}/approve", ['stay' => 1])
            ->assertOk()->assertJson(['stage' => 'dean', 'redirect' => null, 'waiting' => 0]);
    }
}
