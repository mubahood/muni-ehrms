<?php

namespace App\Console\Commands;

use App\Models\Leave;
use App\Services\LeaveRules;
use App\Services\LeaveWorkflow;
use Illuminate\Console\Command;

/**
 * One-off: brings leave recorded before the approval workflow into it.
 *
 * Old rows have no route, working-day count or leave year, and some carry the
 * earlier statuses "hod_approved" / "hr_approved". Each is given its days, year
 * and return date, and:
 *   pending       → waits at the first stage of its route
 *   hod_approved  → waits at the stage after the Head of Department
 *   hr_approved   → waits for the University Secretary
 *   approved / rejected → kept as decided
 * Run with --dry-run first to see what would change.
 */
class NormaliseLegacyLeave extends Command
{
    protected $signature = 'leave:normalise-legacy {--dry-run : List the changes without making them}';

    protected $description = 'Bring leave recorded before the approval workflow into it';

    public function handle()
    {
        $legacy = Leave::with('user.department.faculty')->whereNull('route')->whereNull('reference')->orderBy('id')->get();
        if ($legacy->isEmpty()) {
            $this->info('No legacy leave to bring in.');

            return 0;
        }

        $rows = [];
        foreach ($legacy as $leave) {
            if (!$leave->user) {
                $rows[] = [$leave->id, '—', $leave->status, 'skipped: no such employee'];
                continue;
            }
            $leave->days = LeaveRules::workingDays($leave->start_date, $leave->end_date);
            $leave->leave_year = LeaveRules::leaveYearOf($leave->start_date);
            $leave->return_date = LeaveRules::returnDateAfter($leave->end_date);
            $route = LeaveWorkflow::buildRoute($leave->user);
            $status = strtolower((string) $leave->status);

            switch ($status) {
                case 'pending':
                    $index = 0;
                    break;
                case 'hod_approved':
                    $hod = array_search(Leave::STAGE_HOD, $route, true);
                    $index = $hod === false ? 0 : $hod + 1;
                    break;
                case 'hr_approved':
                    $index = (int) array_search(Leave::STAGE_US, $route, true);
                    break;
                case 'approved':
                case 'rejected':
                    $index = null;
                    break;
                default:
                    $rows[] = [$leave->id, $leave->user->name, $status, 'skipped: unknown status'];
                    continue 2;
            }

            if ($index === null) {
                $outcome = 'kept as ' . $status;
            } else {
                // Vacant Head / Dean stages are passed automatically, as for any request.
                $at = min($index, count($route) - 1);
                while ($at < count($route) - 1
                    && in_array($route[$at], [Leave::STAGE_HOD, Leave::STAGE_DEAN], true)
                    && LeaveWorkflow::stageApprovers($leave, $route[$at])->isEmpty()) {
                    $at++;
                }
                $outcome = 'waits for the ' . Leave::STAGES[$route[$at]];
            }
            $rows[] = [$leave->id, $leave->user->name, $status, "{$leave->days} day(s); {$outcome}"];

            if ($this->option('dry-run')) {
                continue;
            }
            if ($index === null) {
                $leave->status = $status;
                $leave->source = Leave::SOURCE_APPLICATION;
                $leave->decided_at = $leave->decided_at ?: $leave->updated_at;
                $leave->save();
                $leave->reference = sprintf('LV-%s-%05d', $leave->created_at->format('Y'), $leave->id);
                $leave->save();
            } else {
                $leave->status = Leave::PENDING;
                $leave->save();
                LeaveWorkflow::adoptLegacy($leave, $index);
            }
        }

        $this->table(['Id', 'Employee', 'Old status', 'Result'], $rows);
        $this->info($this->option('dry-run') ? 'Dry run: nothing was changed.' : 'Done.');

        return 0;
    }
}
