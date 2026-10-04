<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Leave;
use App\Services\AttendanceEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The attendance rules, one case per rule. "Today" is Wednesday 7 Oct 2026;
 * Monday 5 Oct is the usual past working day used.
 */
class AttendanceEngineTest extends TestCase
{
    private AttendanceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(AttendanceEngine::class);
    }

    public function test_on_time_arrival_and_departure_gives_time_on_site()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-05 08:12:00', '2026-10-05 17:27:00');
        $this->engine->processDay('2026-10-05');

        $r = $this->record($u, '2026-10-05');
        $this->assertSame('Present', $r->status);
        $this->assertSame('No', $r->is_late);
        $this->assertSame('08:12:00', $r->check_in_time);
        $this->assertSame('17:27:00', $r->check_out_time);
        $this->assertEquals(9.25, (float) $r->hours, 'no lunch deduction: 08:12 → 17:27 is 9 h 15 m');
        $this->assertSame(0, (int) $r->is_half_day);
    }

    public function test_late_is_judged_to_the_minute()
    {
        $onTime = $this->makeUser();
        $late = $this->makeUser();
        $this->clockIn($onTime, '2026-10-05 08:30:59', '2026-10-05 17:00:00');
        $this->clockIn($late, '2026-10-05 08:31:00', '2026-10-05 17:00:00');
        $this->engine->processDay('2026-10-05');

        $this->assertSame('No', $this->record($onTime, '2026-10-05')->is_late, '08:30:59 is on time');
        $r = $this->record($late, '2026-10-05');
        $this->assertSame('Yes', $r->is_late, '08:31 is late');
        $this->assertSame(1, (int) $r->late_minutes);
    }

    public function test_personal_late_time_overrides_the_default()
    {
        $u = $this->makeUser(['custom_late_time' => '09:00:00']);
        $this->clockIn($u, '2026-10-05 08:45:00', '2026-10-05 17:00:00');
        $this->engine->processDay('2026-10-05');

        $this->assertSame('No', $this->record($u, '2026-10-05')->is_late);
    }

    public function test_absent_on_a_working_day_without_clock_in()
    {
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-05');

        $r = $this->record($u, '2026-10-05');
        $this->assertSame('Absent', $r->status);
        $this->assertEquals(0, (float) $r->hours);
    }

    public function test_weekend_without_clock_in_has_no_record()
    {
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-04'); // Sunday

        $this->assertNull($this->record($u, '2026-10-04'));
    }

    public function test_clock_in_on_a_weekend_is_present_but_never_late()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-03 10:15:00', '2026-10-03 13:15:00'); // Saturday
        $this->engine->processDay('2026-10-03');

        $r = $this->record($u, '2026-10-03');
        $this->assertSame('Present', $r->status);
        $this->assertSame('No', $r->is_late);
        $this->assertSame(0, (int) $r->is_working_day);
        $this->assertEquals(3, (float) $r->hours);
    }

    public function test_public_holiday_is_not_a_working_day()
    {
        $u = $this->makeUser();
        $this->holiday('2026-10-05', 'Test Day');
        $this->engine->processDay('2026-10-05');

        $this->assertNull($this->record($u, '2026-10-05'), 'nobody is absent on a public holiday');
    }

    public function test_adding_a_holiday_corrects_days_already_processed()
    {
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-05');
        $this->assertSame('Absent', $this->record($u, '2026-10-05')->status);

        $this->holiday('2026-10-05');

        $this->assertNull($this->record($u, '2026-10-05'));
    }

    public function test_personal_work_days_are_respected()
    {
        $saturdayWorker = $this->makeUser(['work_days' => ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']]);
        $this->engine->processRange('2026-10-03', '2026-10-05'); // Sat, Sun, Mon

        $this->assertSame('Absent', $this->record($saturdayWorker, '2026-10-03')->status, 'expected on Saturday');
        $this->assertNull($this->record($saturdayWorker, '2026-10-05'), 'Monday is their day off');
    }

    public function test_single_capture_on_a_past_day_is_a_half_day()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-05 08:00:00');
        $this->engine->processDay('2026-10-05');

        $r = $this->record($u, '2026-10-05');
        $this->assertSame('Present', $r->status);
        $this->assertNull($r->check_out_time);
        $this->assertSame(1, (int) $r->is_half_day);
        $this->assertEquals(4, (float) $r->hours, 'half of the 8-hour standard day');
    }

    public function test_repeat_capture_at_the_door_is_not_a_departure()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-05 08:00:00', '2026-10-05 08:00:09', '2026-10-05 08:29:00');
        $this->engine->processDay('2026-10-05');

        $r = $this->record($u, '2026-10-05');
        $this->assertNull($r->check_out_time, 'all captures are within 30 minutes of arrival');
        $this->assertSame(1, (int) $r->is_half_day);
        $this->assertSame(3, (int) $r->punch_count);
    }

    public function test_capture_after_the_window_is_a_departure()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-05 08:00:00', '2026-10-05 08:30:00');
        $this->engine->processDay('2026-10-05');

        $this->assertSame('08:30:00', $this->record($u, '2026-10-05')->check_out_time);
    }

    public function test_today_without_departure_is_in_progress_not_a_half_day()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-07 08:05:00');
        $this->engine->processDay('2026-10-07');

        $r = $this->record($u, '2026-10-07');
        $this->assertSame('Present', $r->status);
        $this->assertSame(0, (int) $r->is_half_day);
        $this->assertEquals(0, (float) $r->hours);
    }

    public function test_future_days_are_not_processed()
    {
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-08');

        $this->assertNull($this->record($u, '2026-10-08'));
    }

    public function test_pending_leave_does_not_excuse_absence()
    {
        $u = $this->makeUser();
        $this->leave($u, '2026-10-05', '2026-10-06', Leave::PENDING);
        $this->engine->processDay('2026-10-05');

        $this->assertSame('Absent', $this->record($u, '2026-10-05')->status);
    }

    public function test_approved_leave_marks_on_leave()
    {
        $u = $this->makeUser();
        $this->leave($u, '2026-10-05', '2026-10-06', Leave::APPROVED);
        $this->engine->processDay('2026-10-05');

        $this->assertSame('On Leave', $this->record($u, '2026-10-05')->status);
    }

    public function test_declined_and_withdrawn_leave_do_not_count()
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->leave($a, '2026-10-05', '2026-10-05', Leave::REJECTED);
        $this->leave($b, '2026-10-05', '2026-10-05', Leave::WITHDRAWN);
        $this->engine->processDay('2026-10-05');

        $this->assertSame('Absent', $this->record($a, '2026-10-05')->status);
        $this->assertSame('Absent', $this->record($b, '2026-10-05')->status);
    }

    public function test_clock_in_during_approved_leave_counts_as_present()
    {
        $u = $this->makeUser();
        $this->leave($u, '2026-10-05', '2026-10-06', Leave::APPROVED);
        $this->clockIn($u, '2026-10-05 09:00:00', '2026-10-05 12:00:00');
        $this->engine->processDay('2026-10-05');

        $this->assertSame('Present', $this->record($u, '2026-10-05')->status);
    }

    public function test_recalled_leave_ends_on_the_resume_date()
    {
        $u = $this->makeUser();
        $this->leave($u, '2026-10-01', '2026-10-09', Leave::RECALLED, ['recall_date' => '2026-10-05']);
        $this->engine->processRange('2026-10-02', '2026-10-06');

        $this->assertSame('On Leave', $this->record($u, '2026-10-02')->status, 'before resuming');
        $this->assertSame('Absent', $this->record($u, '2026-10-05')->status, 'expected back from the resume date');
    }

    public function test_hr_correction_survives_a_rebuild()
    {
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-05');
        DB::table('attendance_records')->where('user_id', $u->id)->update([
            'status' => 'Present', 'check_in_time' => '08:00:00', 'is_manual' => true,
            'correction_reason' => 'Terminal offline',
        ]);

        $this->engine->processDay('2026-10-05');

        $this->assertSame('Present', $this->record($u, '2026-10-05')->status);
    }

    public function test_imported_record_survives_a_rebuild()
    {
        $u = $this->makeUser();
        DB::table('attendance_records')->insert([
            'user_id' => $u->id, 'attendance_date' => '2026-10-05', 'status' => 'Present',
            'check_in_time' => '07:55:00', 'source' => 'import', 'is_imported' => 'Yes',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->engine->processDay('2026-10-05');

        $this->assertSame('07:55:00', $this->record($u, '2026-10-05')->check_in_time);
    }

    public function test_rebuilding_twice_gives_identical_records()
    {
        $u = $this->makeUser();
        $this->clockIn($u, '2026-10-05 08:40:00', '2026-10-05 16:10:00');
        $this->engine->processRange('2026-10-01', '2026-10-07');
        $first = AttendanceRecord::where('user_id', $u->id)->orderBy('attendance_date')->get()
            ->map->only(['attendance_date', 'status', 'check_in_time', 'check_out_time', 'hours', 'is_late', 'late_minutes']);

        $this->engine->processRange('2026-10-01', '2026-10-07');
        $second = AttendanceRecord::where('user_id', $u->id)->orderBy('attendance_date')->get()
            ->map->only(['attendance_date', 'status', 'check_in_time', 'check_out_time', 'hours', 'is_late', 'late_minutes']);

        $this->assertEquals($first->all(), $second->all());
        $this->assertCount(5, $first, 'Thu 1 – Wed 7 Oct has five working days');
    }

    public function test_people_are_not_expected_before_they_start_or_once_inactive()
    {
        $newcomer = $this->makeUser(['start_working_date' => '2026-10-06']);
        $former = $this->makeUser(['status' => 'Inactive']);
        $this->engine->processDay('2026-10-05');

        $this->assertNull($this->record($newcomer, '2026-10-05'));
        $this->assertNull($this->record($former, '2026-10-05'));
    }

    public function test_no_absences_before_tracking_began()
    {
        DB::table('system_configurations')->update(['start_date' => '2026-10-06']);
        \App\Models\SystemConfiguration::forgetCurrent();
        $u = $this->makeUser();
        $this->engine->processDay('2026-10-05');

        $this->assertNull($this->record($u, '2026-10-05'));
    }

    public function test_failed_face_verification_is_not_a_clock_in()
    {
        $u = $this->makeUser();
        DB::table('event_logs')->insert([
            'event_time' => '2026-10-05 08:00:00', 'employee_no' => $u->employee_no, 'user_id' => $u->id,
            'major' => 5, 'minor' => 76, 'source' => 'webhook', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->engine->processDay('2026-10-05');

        $this->assertSame('Absent', $this->record($u, '2026-10-05')->status, 'minor 76 = face not recognised');
    }

    public function test_face_recognised_event_with_codes_is_a_clock_in()
    {
        $u = $this->makeUser();
        DB::table('event_logs')->insert([
            'event_time' => '2026-10-05 08:00:00', 'employee_no' => $u->employee_no, 'user_id' => $u->id,
            'major' => 5, 'minor' => 75, 'source' => 'webhook', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->engine->processDay('2026-10-05');

        $this->assertSame('Present', $this->record($u, '2026-10-05')->status);
    }

    private function leave($user, string $from, string $to, string $status, array $extra = []): Leave
    {
        return Leave::create(array_merge([
            'user_id' => $user->id, 'start_date' => $from, 'end_date' => $to, 'status' => $status,
            'leave_type' => 'annual', 'reason' => 'Test', 'days' => 1,
        ], $extra));
    }
}
