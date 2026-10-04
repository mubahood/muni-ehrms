<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every other test writes to the database, so this one makes sure that
 * database is the disposable test copy and never the development data.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_tests_run_against_the_test_database()
    {
        $this->assertSame('muni_ehrms_test', DB::connection()->getDatabaseName());
    }

    public function test_the_application_runs_in_kampala_time()
    {
        $this->assertSame('Africa/Kampala', config('app.timezone'));
        $this->assertSame('Africa/Kampala', now()->getTimezone()->getName());
    }
}
