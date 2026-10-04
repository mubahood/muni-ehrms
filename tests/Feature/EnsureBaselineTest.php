<?php

namespace Tests\Feature;

use App\Models\LeaveEntitlement;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Services\LeaveRules;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnsureBaselineTest extends TestCase
{
    public function test_fills_only_what_is_missing_and_never_overwrites()
    {
        $year = LeaveRules::currentLeaveYear();
        $withAllocation = $this->makeUser(['name' => 'Has Allocation']);
        $without = $this->makeUser(['name' => 'No Allocation']);
        $roleless = $this->makeUser(['name' => 'No Role'], []);
        LeaveEntitlement::create(['user_id' => $withAllocation->id, 'leave_year' => $year, 'days_due' => 21, 'carried_forward' => 4]);
        DB::table('admin_role_users')->insert(['role_id' => 1, 'user_id' => 999999]);
        PublicHoliday::whereYear('date', now()->year + 1)->delete();
        // This year as HR keeps it: a single holiday, the rest removed on purpose.
        PublicHoliday::whereYear('date', now()->year)->delete();
        $this->holiday(now()->year . '-10-09', 'Independence Day');

        $before = PublicHoliday::whereYear('date', now()->year)->count();
        $this->artisan('ehrms:ensure-baseline')->assertExitCode(0);

        $kept = LeaveEntitlement::where('user_id', $withAllocation->id)->where('leave_year', $year)->first();
        $this->assertSame([21, 4], [$kept->days_due, $kept->carried_forward], 'an existing allocation is never changed');
        $this->assertNotNull(LeaveEntitlement::where('user_id', $without->id)->where('leave_year', $year)->first(), 'a missing allocation is created');
        $this->assertTrue($roleless->fresh()->roles()->where('slug', 'employee')->exists());
        $this->assertSame(0, DB::table('admin_role_users')->where('user_id', 999999)->count());
        $this->assertGreaterThan(5, PublicHoliday::whereYear('date', now()->year + 1)->count(), 'next year gets its holidays');
        $this->assertSame($before, PublicHoliday::whereYear('date', now()->year)->count(), 'a year with holidays is left as HR keeps it');

        // Running again changes nothing.
        $this->artisan('ehrms:ensure-baseline')->expectsOutput('Nothing missing: allocations, holidays and roles are all in place.')->assertExitCode(0);
    }
}
