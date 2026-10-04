<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Services\AccessPolicy;
use App\Services\AttendanceEngine;
use App\Services\Scope;
use Tests\TestCase;

/**
 * Who may open what, and whose records they then see.
 */
class AccessControlTest extends TestCase
{
    /** role => areas that role must be allowed into (every other area is refused). */
    private const MATRIX = [
        'employee' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
        ],
        'hod' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
            'employees.view', 'organisation.view', 'reports', 'reports.legacy', 'leave.approve', 'leave.all',
        ],
        'dean' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
            'employees.view', 'organisation.view', 'reports', 'reports.legacy', 'leave.approve', 'leave.all',
        ],
        'hr' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
            'employees.view', 'organisation.view', 'reports', 'reports.legacy', 'leave.approve', 'leave.all',
            'attendance.correct', 'employees.manage', 'organisation.manage', 'holidays.manage', 'leave.manage',
            'leave.recall', 'audit',
        ],
        'us' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
            'employees.view', 'organisation.view', 'reports', 'reports.legacy', 'leave.approve', 'leave.all', 'leave.recall',
        ],
        'admin' => [
            'dashboard', 'my.dashboard', 'my.leave', 'my.profile', 'notifications', 'attendance.view', 'holidays.view',
            'employees.view', 'organisation.view', 'reports', 'reports.legacy', 'leave.all',
            'attendance.correct', 'employees.manage', 'organisation.manage', 'holidays.manage', 'leave.manage',
            'configuration', 'configuration.write', 'system.users', 'audit', 'device.events', 'imports', 'demo.manage',
        ],
    ];

    public function test_every_role_reaches_exactly_its_areas()
    {
        foreach (self::MATRIX as $role => $allowed) {
            $user = $this->makeUser([], [$role]);
            foreach (array_keys(AccessPolicy::AREAS) as $area) {
                $this->assertSame(
                    in_array($area, $allowed, true),
                    AccessPolicy::allows($user, $area),
                    "{$role} → {$area}"
                );
            }
        }
    }

    public function test_unmapped_pages_are_for_the_system_administrator_only()
    {
        $this->assertFalse(AccessPolicy::allows($this->makeUser([], ['hr']), 'unmapped'));
        $this->assertTrue(AccessPolicy::allows($this->makeUser([], ['admin']), 'unmapped'));
    }

    public function test_pages_are_refused_over_http()
    {
        $hod = $this->makeUser([], ['hod']);
        $employee = $this->makeUser();

        $this->actingAs($hod, 'admin')->get('/system-configurations')->assertStatus(403);
        $this->actingAs($hod, 'admin')->get('/vehicles')->assertStatus(403);
        $this->actingAs($employee, 'admin')->get('/users')->assertStatus(403);
        $this->actingAs($employee, 'admin')->get('/auth/users')->assertStatus(403);
    }

    public function test_viewing_and_changing_are_separate()
    {
        $hod = $this->makeUser([], ['hod']);
        $hr = $this->makeUser([], ['hr']);
        $staff = $this->makeUser();
        app(AttendanceEngine::class)->processDay('2026-10-05');
        $record = $this->record($staff, '2026-10-05');

        $this->actingAs($hod, 'admin')->get("/attendance-records/{$record->id}/edit")->assertStatus(403);
        $this->actingAs($hod, 'admin')->get('/users/create')->assertStatus(403);
        $this->actingAs($hr, 'admin')->get("/attendance-records/{$record->id}/edit")->assertOk();
        $this->actingAs($hr, 'admin')->get('/users/create')->assertOk();
    }

    public function test_head_of_department_sees_only_their_department()
    {
        [$cs, $finance] = [$this->makeDepartment(['name' => 'Computer Science']), $this->makeDepartment(['name' => 'Finance'])];
        $hod = $this->makeUser(['department_id' => $cs->id], ['hod']);
        $cs->update(['hod_id' => $hod->id]);
        $mine = $this->makeUser(['department_id' => $cs->id, 'name' => 'Alice Mine']);
        $theirs = $this->makeUser(['department_id' => $finance->id, 'name' => 'Bob Theirs']);

        $this->assertSame(Scope::DEPARTMENT, Scope::level($hod));
        $this->assertTrue(Scope::canSee($hod, $mine->id));
        $this->assertFalse(Scope::canSee($hod, $theirs->id));

        $page = $this->actingAs($hod, 'admin')->get('/users')->assertOk()->getContent();
        $this->assertStringContainsString('Alice Mine', $page);
        $this->assertStringNotContainsString('Bob Theirs', $page);
    }

    public function test_dean_sees_every_department_of_their_faculty()
    {
        $faculty = $this->makeFaculty();
        $dean = $this->makeUser([], ['dean']);
        $faculty->update(['dean_id' => $dean->id]);
        $a = $this->makeDepartment(['type' => Department::ACADEMIC, 'faculty_id' => $faculty->id]);
        $b = $this->makeDepartment(['type' => Department::ACADEMIC, 'faculty_id' => $faculty->id]);
        $outside = $this->makeDepartment();
        $inA = $this->makeUser(['department_id' => $a->id]);
        $inB = $this->makeUser(['department_id' => $b->id]);
        $out = $this->makeUser(['department_id' => $outside->id]);

        $this->assertSame(Scope::FACULTY, Scope::level($dean));
        $this->assertTrue(Scope::canSee($dean, $inA->id));
        $this->assertTrue(Scope::canSee($dean, $inB->id));
        $this->assertFalse(Scope::canSee($dean, $out->id));
    }

    public function test_employee_sees_only_their_own_attendance()
    {
        $me = $this->makeUser(['name' => 'Carol Self']);
        $other = $this->makeUser(['name' => 'Dan Other']);
        app(AttendanceEngine::class)->processDay('2026-10-05');

        $page = $this->actingAs($me, 'admin')->get('/attendance-records')->assertOk()->getContent();
        $this->assertStringContainsString('Carol Self', $page);
        $this->assertStringNotContainsString('Dan Other', $page);

        $theirs = $this->record($other, '2026-10-05');
        $this->actingAs($me, 'admin')->get("/attendance-records/{$theirs->id}")->assertStatus(403);
    }

    public function test_university_wide_roles_see_everyone()
    {
        foreach (['hr', 'us', 'admin'] as $role) {
            $this->assertSame(Scope::UNIVERSITY, Scope::level($this->makeUser([], [$role])), $role);
        }
    }

    public function test_only_the_system_administrator_can_assign_roles()
    {
        $hr = $this->makeUser([], ['hr']);
        $admin = $this->makeUser([], ['admin']);

        $this->assertStringNotContainsString('name="roles[]"', $this->actingAs($hr, 'admin')->get('/users/create')->getContent());
        $this->assertStringContainsString('name="roles[]"', $this->actingAs($admin, 'admin')->get('/users/create')->getContent());
    }

    public function test_hr_correction_requires_a_reason_and_is_kept()
    {
        $hr = $this->makeUser([], ['hr']);
        $staff = $this->makeUser();
        app(AttendanceEngine::class)->processDay('2026-10-05');
        $record = $this->record($staff, '2026-10-05');

        $this->actingAs($hr, 'admin')->put("/attendance-records/{$record->id}", [
            'status' => 'Present', 'check_in_time' => '08:10:00', 'check_out_time' => '17:00:00', 'correction_reason' => '',
        ]);
        $this->assertSame('Absent', $this->record($staff, '2026-10-05')->status, 'no reason, no change');

        $this->actingAs($hr, 'admin')->put("/attendance-records/{$record->id}", [
            'status' => 'Present', 'check_in_time' => '08:40:00', 'check_out_time' => '17:00:00',
            'correction_reason' => 'Terminal offline; confirmed by HoD',
        ]);
        $fixed = $this->record($staff, '2026-10-05');
        $this->assertSame('Present', $fixed->status);
        $this->assertSame(1, (int) $fixed->is_manual);
        $this->assertSame(10, (int) $fixed->late_minutes);
        $this->assertEquals(8.33, (float) $fixed->hours);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.corrected', 'user_id' => $hr->id]);

        app(AttendanceEngine::class)->processDay('2026-10-05');
        $this->assertSame('Present', $this->record($staff, '2026-10-05')->status, 'correction survives a rebuild');
    }
}
