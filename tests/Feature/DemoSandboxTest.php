<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Leave;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\DemoSandbox;
use App\Services\LeaveWorkflow;
use App\Services\Scope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * The demo sandbox on a live system: real staff never see it, demo accounts
 * never see or change anything real, and the admin can remove it cleanly.
 */
class DemoSandboxTest extends TestCase
{
    private User $realHr;
    private User $realAdmin;
    private User $realStaff;
    private Department $realDept;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->realDept = $this->makeDepartment(['name' => 'Registry (real)']);
        $this->realHr = $this->makeUser(['name' => 'Real HR', 'username' => 'real.hr'], ['hr']);
        $this->realAdmin = $this->makeUser(['name' => 'Real Admin', 'username' => 'real.admin'], ['admin']);
        $this->realStaff = $this->makeUser(['name' => 'Real Person', 'department_id' => $this->realDept->id, 'employee_no' => 'MU-7001']);
        (new DemoSandbox())->build(30);
    }

    private function demo(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_builds_fifteen_accounts_covering_every_role_with_history()
    {
        $demo = User::where('is_demo', true)->get();
        $this->assertGreaterThan(60, $demo->count(), 'a university of realistic size');
        $this->assertSame(15, $demo->whereIn('username', collect(config('demo.accounts'))->pluck('username'))->count(), 'fifteen sign-in accounts');
        $this->assertTrue($demo->every(fn ($u) => strpos($u->username, 'demo.') === 0));
        foreach (['admin', 'us', 'hr', 'dean', 'hod', 'employee'] as $role) {
            $this->assertTrue($demo->contains(fn ($u) => $u->hasAnyRole($role)), "a demo {$role}");
        }
        $stats = DemoSandbox::stats();
        $this->assertGreaterThan(1000, $stats['clock_ins']);
        $this->assertSame(['hod', 'hod', 'dean', 'hr', 'hr', 'us'], Leave::whereIn('user_id', $demo->pluck('id'))->where('status', Leave::PENDING)
            ->orderByRaw("FIELD(stage, 'hod', 'dean', 'hr', 'us')")->pluck('stage')->all(), 'leave waits at every stage');

        // Mostly present, as in a real university.
        $s = \App\Services\AttendanceStats::summary($demo->pluck('id')->all(), today()->subDays(29), today());
        $this->assertGreaterThan(85, $s['rate']);
        $this->assertGreaterThan(0, $s['late']);
        $this->assertGreaterThan(0, $s['absent']);
        $this->assertGreaterThan($s['absent'] * 5, $s['present']);
    }

    public function test_real_staff_never_see_the_sandbox()
    {
        $ids = Scope::userIds($this->realHr);
        $this->assertContains($this->realStaff->id, $ids);
        $this->assertEmpty(array_intersect($ids, User::where('is_demo', true)->pluck('id')->all()));
        $this->assertFalse(Scope::departments($this->realHr)->contains('is_demo', true));
        $this->assertSame(0, LeaveWorkflow::pendingFor($this->realHr)->count(), 'no demo leave in the real HR queue');

        $page = $this->actingAs($this->realHr, 'admin')->get('/users')->assertOk()->getContent();
        $this->assertStringNotContainsString('Harriet Letaru', $page);
        $people = $this->actingAs($this->realHr, 'admin')->getJson('/lookup/people?q=a')->json('results');
        $this->assertEmpty(array_filter($people, fn ($p) => User::find($p['id'])->is_demo));
        $csv = $this->actingAs($this->realHr, 'admin')->get('/reports/summary.csv?scope=university&period=last_30')->streamedContent();
        $this->assertStringNotContainsString('demo', strtolower(preg_replace('/^.*\n/', '', $csv)));
    }

    public function test_demo_accounts_see_only_the_sandbox()
    {
        $hr = $this->demo('demo.hr');
        $ids = Scope::userIds($hr);
        $this->assertNotContains($this->realStaff->id, $ids);
        $this->assertCount(User::where('is_demo', true)->count(), $ids);
        $this->assertTrue(LeaveWorkflow::pendingFor($hr)->get()->every(fn ($l) => $l->user->is_demo));
        $this->assertGreaterThan(0, LeaveWorkflow::pendingFor($hr)->count());

        $this->actingAs($hr, 'admin')->get('/')->assertOk()->assertDontSee('Real Person');
        $this->actingAs($hr, 'admin')->get("/staff/{$this->realStaff->id}")->assertStatus(403);
        $this->actingAs($hr, 'admin')->get("/users/{$this->realStaff->id}/edit")->assertStatus(404);
        $this->actingAs($hr, 'admin')->delete("/users/{$this->realStaff->id}")->assertStatus(404);
        $this->actingAs($hr, 'admin')->get("/departments/{$this->realDept->id}/edit")->assertStatus(404);
        $this->assertNotNull($this->realStaff->fresh());
    }

    public function test_demo_accounts_cannot_touch_anything_university_wide()
    {
        $admin = $this->demo('demo.admin');
        $lateBefore = SystemConfiguration::current()->late_time;

        $this->actingAs($admin, 'admin')->get('/settings')->assertOk()->assertSee('Read-only in the demo');
        $this->actingAs($admin, 'admin')->put('/settings', ['late_time' => '11:00'])->assertStatus(403);
        $this->assertSame($lateBefore, SystemConfiguration::current()->fresh()->late_time);

        foreach (['/public-holidays/create', '/auth/users', '/event-logs', '/import-attendance-records', '/demo-data', '/vehicles', '/general-reports', '/companies'] as $url) {
            $this->actingAs($admin, 'admin')->get($url)->assertStatus(403);
        }
        $this->actingAs($admin, 'admin')->post('/demo-data/purge')->assertStatus(403);
        $this->assertTrue(DemoSandbox::exists());
    }

    public function test_leave_never_crosses_between_the_two_worlds()
    {
        $realLeave = Leave::create([
            'user_id' => $this->realStaff->id, 'leave_type' => 'annual', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21',
            'days' => 2, 'leave_year' => 2026, 'status' => Leave::PENDING, 'stage' => Leave::STAGE_HR, 'reason' => 'Rest',
        ]);
        $this->assertFalse(LeaveWorkflow::canAct($this->demo('demo.hr'), $realLeave), 'a demo HR never decides real leave');
        $this->assertTrue(LeaveWorkflow::canAct($this->realHr, $realLeave));
        $this->actingAs($this->demo('demo.hr'), 'admin')->get("/leave/{$realLeave->id}")->assertStatus(403);

        $this->actingAs($this->demo('demo.hr'), 'admin')->post('/leave/record', [
            'user_id' => $this->realStaff->id, 'leave_type' => 'annual', 'start_date' => '2026-10-26', 'end_date' => '2026-10-27', 'reason' => 'x x x',
        ])->assertStatus(403);
        $this->actingAs($this->demo('demo.employee'), 'admin')->post('/my-leave', [
            'leave_type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'reason' => 'Visit', 'acting_user_id' => $this->realStaff->id,
        ])->assertSessionHasErrors('acting_user_id');
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->realHr->id)->count(), 'no demo notice reaches real staff');
    }

    public function test_new_records_made_in_the_demo_stay_in_the_demo()
    {
        $hr = $this->demo('demo.hr');
        $this->actingAs($hr, 'admin')->post('/departments', ['name' => 'Registry (real)', 'type' => Department::ADMINISTRATIVE, 'is_active' => 'on'])->assertRedirect();
        $made = Department::where('name', 'Registry (real)')->where('is_demo', true)->first();
        $this->assertNotNull($made, 'a name used in the real university is free in the sandbox');
    }

    public function test_admin_deletes_one_account_or_everything_and_nothing_real_is_lost()
    {
        $realEvents = DB::table('event_logs')->where('user_id', $this->realStaff->id)->count();
        $one = $this->demo('demo.nurse');
        $this->actingAs($this->realAdmin, 'admin')->deleteJson("/demo-data/accounts/{$one->id}")->assertOk();
        $this->assertNull(User::find($one->id));
        $this->assertSame(0, DB::table('attendance_records')->where('user_id', $one->id)->count());

        SystemConfiguration::current()->update(['demo_logins' => true]);
        $this->actingAs($this->realAdmin, 'admin')->post('/demo-data/purge')->assertRedirect();
        $this->assertFalse(DemoSandbox::exists());
        $this->assertSame(0, Department::where('is_demo', true)->count());
        $this->assertSame(0, \App\Models\Faculty::where('is_demo', true)->count());
        $this->assertSame(0, DB::table('event_logs')->where('source', 'demo')->count());
        $this->assertFalse((bool) SystemConfiguration::current()->fresh()->demo_logins, 'deleting hides the demo sign-ins');
        $this->assertNotNull($this->realStaff->fresh());
        $this->assertSame($realEvents, DB::table('event_logs')->where('user_id', $this->realStaff->id)->count());
    }

    public function test_login_page_offers_demo_accounts_only_when_switched_on()
    {
        $this->get('/auth/login')->assertOk()->assertDontSee('Try a demo account');
        SystemConfiguration::current()->update(['demo_logins' => true]);
        $this->get('/auth/login')->assertOk()->assertSee('Try a demo account')->assertSee('demo.hod.maths')->assertDontSee('real.hr');

        $this->post('/auth/login', ['username' => 'demo.hr', 'password' => config('demo.password')])->assertRedirect();
        $this->assertSame('demo.hr', optional(auth('admin')->user())->username);
    }

    public function test_real_admin_explores_the_demo_and_comes_back()
    {
        $this->actingAs($this->realAdmin, 'admin')->post('/demo-data/enter')->assertRedirect(admin_url('/'));
        $this->assertSame('demo.admin', auth('admin')->user()->username);
        $this->get('/')->assertOk()->assertSee('Back to my account')->assertDontSee('Real Person');

        $this->post('/demo/leave')->assertRedirect(admin_url('/'));
        $this->assertSame('real.admin', auth('admin')->user()->username);

        // A demo account cannot use the switch to reach a real account.
        $this->actingAs($this->demo('demo.admin'), 'admin')->withSession(['ehr_demo_return' => [$this->realAdmin->id, 'forged']])
            ->post('/demo/leave')->assertStatus(403);
        $this->actingAs($this->demo('demo.admin'), 'admin')->post('/demo-data/enter')->assertStatus(403);
        $this->actingAs($this->realHr, 'admin')->post('/demo-data/enter')->assertStatus(403);
    }

    public function test_demo_password_cannot_be_changed()
    {
        $hr = $this->demo('demo.hr');
        $hash = $hr->password;
        $this->actingAs($hr, 'admin')->put('/auth/setting', [
            'first_name' => 'Harriet', 'last_name' => 'Letaru', 'password' => 'NewSecret2026', 'password_confirmation' => 'NewSecret2026',
        ]);
        $this->assertSame($hash, $hr->fresh()->password);
    }

    public function test_rebuilding_attendance_over_a_long_range_never_invents_demo_absences()
    {
        $ids = User::where('is_demo', true)->pluck('id')->all();
        $before = DB::table('attendance_records')->whereIn('user_id', $ids)->count();
        app(\App\Services\AttendanceEngine::class)->processRange(today()->subDays(120), today(), $ids);
        $this->assertSame($before, DB::table('attendance_records')->whereIn('user_id', $ids)->count());
    }

    public function test_top_up_adds_the_day_once()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 12:00:00')); // Thursday
        $first = (new DemoSandbox())->topUp();
        $second = (new DemoSandbox())->topUp();
        $this->assertGreaterThan(10, $first);
        $this->assertSame(0, $second);
        $this->assertGreaterThan(8, DB::table('attendance_records')->where('attendance_date', '2026-10-08')
            ->whereIn('user_id', User::where('is_demo', true)->pluck('id'))->where('status', 'Present')->count());
    }
}
