<?php

namespace Tests\Feature;

use App\Models\EventLog;
use Tests\TestCase;

/**
 * Events arriving from the terminals, end to end: the EventLog observer links
 * the person and the engine rebuilds their day.
 */
class DeviceEventPipelineTest extends TestCase
{
    private function event(string $employeeNo, string $time, array $extra = []): EventLog
    {
        return EventLog::create(array_merge([
            'employee_no' => $employeeNo,
            'employee_name' => 'From terminal',
            'event_time' => $time,
            'source' => 'webhook',
            'process_status' => EventLog::STATUS_UNPROCESSED,
        ], $extra))->fresh();
    }

    public function test_arrival_and_departure_from_the_terminal_build_the_day()
    {
        $u = $this->makeUser(['employee_no' => 'MU0101']);
        $this->event('MU0101', '2026-10-05 08:41:00');
        $last = $this->event('MU0101', '2026-10-05 17:05:00');

        $r = $this->record($u, '2026-10-05');
        $this->assertSame('Present', $r->status);
        $this->assertSame('08:41:00', $r->check_in_time);
        $this->assertSame('17:05:00', $r->check_out_time);
        $this->assertSame(11, (int) $r->late_minutes);
        $this->assertSame(EventLog::STATUS_PROCESSED, $last->process_status);
        $this->assertSame($r->id, $last->attendance_record_id);
    }

    public function test_events_arriving_out_of_order_give_the_same_day()
    {
        $u = $this->makeUser(['employee_no' => 'MU0102']);
        $this->event('MU0102', '2026-10-05 17:00:00'); // the bridge delivered the departure first
        $this->event('MU0102', '2026-10-05 08:10:00');

        $r = $this->record($u, '2026-10-05');
        $this->assertSame('08:10:00', $r->check_in_time);
        $this->assertSame('17:00:00', $r->check_out_time);
    }

    public function test_unknown_terminal_id_is_kept_as_failed_without_touching_anyone()
    {
        $event = $this->event('NOBODY-999', '2026-10-05 08:00:00');

        $this->assertSame(EventLog::STATUS_FAILED, $event->process_status);
        $this->assertNull($event->user_id);
    }

    public function test_terminal_id_never_lands_on_someone_who_has_their_own_number()
    {
        $owner = $this->makeUser(['employee_no' => 'MU0103']);
        // A terminal sends the owner's database id as its ID: it must not be credited to them.
        $event = $this->event((string) $owner->id, '2026-10-05 08:00:00');

        $this->assertNotSame($owner->id, $event->user_id);
        $this->assertNull($this->record($owner, '2026-10-05'));
    }

    public function test_legacy_user_without_employee_number_still_matches_by_id()
    {
        $legacy = $this->makeUser(['employee_no' => null, 'name' => 'Brenda Nakacwa', 'first_name' => 'Brenda', 'last_name' => 'Nakacwa']);
        $this->event((string) $legacy->id, '2026-10-05 08:00:00', ['employee_name' => 'NAKACWA BRENDA']);

        $this->assertSame('Present', $this->record($legacy, '2026-10-05')->status);
    }

    public function test_legacy_match_by_id_is_refused_when_the_terminal_names_someone_else()
    {
        // Seen live: terminal ID 2 belongs to "Androa Hellen", while user 2 is someone else.
        $legacy = $this->makeUser(['employee_no' => null, 'name' => 'Brenda Nakacwa', 'first_name' => 'Brenda', 'last_name' => 'Nakacwa']);
        $event = $this->event((string) $legacy->id, '2026-10-05 08:00:00', ['employee_name' => 'Androa Hellen']);

        $this->assertNull($event->fresh()->user_id);
        $this->assertSame(EventLog::STATUS_FAILED, $event->fresh()->process_status);
        $this->assertStringContainsString('Androa Hellen', $event->fresh()->process_error);
    }

    public function test_terminal_events_never_land_on_a_demo_account()
    {
        $demo = $this->makeUser(['employee_no' => 'DEMO-001', 'is_demo' => true]);
        $event = $this->event('DEMO-001', '2026-10-05 08:00:00');

        $this->assertNull($event->fresh()->user_id);
        $this->assertNull($this->record($demo, '2026-10-05'));
    }

    public function test_failed_face_event_is_skipped()
    {
        $u = $this->makeUser(['employee_no' => 'MU0104']);
        $event = $this->event('MU0104', '2026-10-05 08:00:00', ['major' => 5, 'minor' => 76]);

        $this->assertSame(EventLog::STATUS_SKIPPED, $event->process_status);
        $this->assertNull($this->record($u, '2026-10-05'));
    }

    public function test_event_before_start_date_is_skipped_with_reason()
    {
        $this->makeUser(['employee_no' => 'MU0105', 'start_working_date' => '2026-10-06']);
        $event = $this->event('MU0105', '2026-10-05 08:00:00');

        $this->assertSame(EventLog::STATUS_SKIPPED, $event->process_status);
        $this->assertStringContainsString('start working date', $event->process_error);
    }
}
