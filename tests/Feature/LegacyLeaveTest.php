<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Models\LeaveAction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Leave recorded before the approval workflow is brought into it at the right
 * stage, without losing its decision.
 */
class LegacyLeaveTest extends TestCase
{
    public function test_old_requests_join_the_workflow_at_the_right_stage()
    {
        $dept = $this->makeDepartment(); // no Head of Department
        $this->makeUser([], ['hr']);
        $this->makeUser([], ['us']);
        $staff = $this->makeUser(['department_id' => $dept->id]);

        $insert = fn (string $status) => DB::table('leaves')->insertGetId([
            'user_id' => $staff->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-06',
            'status' => $status, 'leave_type' => 'sick', 'reason' => 'Old', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pending = $insert('pending');
        $hrApproved = $insert('hr_approved');
        $rejected = $insert('rejected');

        $this->artisan('leave:normalise-legacy --dry-run')->assertExitCode(0);
        $this->assertNull(Leave::find($pending)->route, 'a dry run changes nothing');

        $this->artisan('leave:normalise-legacy')->assertExitCode(0);

        $p = Leave::find($pending);
        $this->assertSame(Leave::PENDING, $p->status);
        $this->assertSame('hr', $p->stage, 'the vacant Head stage is passed');
        $this->assertSame(5, $p->days);
        $this->assertNotNull($p->reference);
        $this->assertSame(1, $p->actions()->where('action', LeaveAction::SKIPPED)->count());

        $this->assertSame('us', Leave::find($hrApproved)->stage);
        $this->assertSame(Leave::REJECTED, Leave::find($rejected)->status);

        $this->artisan('leave:normalise-legacy')->expectsOutput('No legacy leave to bring in.');
    }
}
