<?php

namespace Tests;

use App\Models\SystemConfiguration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;
    use Concerns\BuildsOrganisation;

    /** Wednesday 7 October 2026. Friday 9 October is Independence Day. */
    public const TODAY = '2026-10-07';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TODAY . ' 14:00:00'));
        SystemConfiguration::forgetCurrent();
        \DB::table('system_configurations')->delete();
        \DB::table('public_holidays')->delete();
        \DB::table('system_configurations')->insert([
            'company_name' => 'Muni University',
            'company_address' => 'P.O. Box 725 Arua, Uganda',
            'company_phone' => '+256 476 420312/3/4',
            'company_email' => 'info@muni.ac.ug',
            'company_logo' => '',
            'start_date' => '2026-01-01',
            'late_time' => '08:30:00',
            'working_days' => '1,2,3,4,5',
            'full_day_hours' => 8,
            'repeat_capture_minutes' => 30,
            'leave_year_start_month' => 7,
            'annual_leave_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        SystemConfiguration::forgetCurrent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        SystemConfiguration::forgetCurrent();
        parent::tearDown();
    }
}
