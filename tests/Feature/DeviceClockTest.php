<?php

namespace Tests\Feature;

use App\Models\EventLog;
use Tests\TestCase;

class DeviceClockTest extends TestCase
{
    public function test_terminal_two_hours_ahead_with_wrong_zone_label_is_corrected()
    {
        // Seen live: received 16:38:56 Kampala, terminal stamped 18:38:53+08:00.
        $t = EventLog::deviceTimeToLocal('2026-10-03T18:38:53+08:00', '2026-10-03 16:38:56');
        $this->assertSame('2026-10-03 16:38:53', $t->format('Y-m-d H:i:s'));
    }

    public function test_correct_terminal_is_left_alone()
    {
        $t = EventLog::deviceTimeToLocal('2026-10-03T07:55:10+03:00', '2026-10-03 07:55:12');
        $this->assertSame('2026-10-03 07:55:10', $t->format('Y-m-d H:i:s'));
    }

    public function test_late_delivery_is_not_mistaken_for_a_clock_error()
    {
        // Relayed 40 minutes late: not a whole-hour gap, so the time stands.
        $t = EventLog::deviceTimeToLocal('2026-10-03 07:55:10', '2026-10-03 08:35:00');
        $this->assertSame('2026-10-03 07:55:10', $t->format('Y-m-d H:i:s'));
    }

    public function test_correction_crosses_midnight()
    {
        $t = EventLog::deviceTimeToLocal('2026-10-04T01:10:00+08:00', '2026-10-03 23:10:30');
        $this->assertSame('2026-10-03 23:10:00', $t->format('Y-m-d H:i:s'));
    }
}
