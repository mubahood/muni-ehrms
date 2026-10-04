<?php

namespace App\Console\Commands;

use App\Models\LeaveEntitlement;
use App\Models\PublicHoliday;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Audit;
use App\Services\LeaveRules;
use App\Support\UgandaHolidays;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the reference data the system depends on in place. Safe to run at
 * any time and runs nightly: it only fills what is missing and never changes
 * a value someone has set.
 *
 *  - Every active person has an annual leave allocation for the current
 *    leave year (the standard days from System settings, nothing carried
 *    forward; Leave planning adjusts it). Without one their balance is 0 and
 *    they cannot apply for annual leave.
 *  - The current and next calendar year have their Uganda public holidays,
 *    but only a year with none at all is filled, so a holiday HR removed on
 *    purpose stays removed.
 *  - Everyone has at least the Employee role (no role = no access at all).
 *  - Role assignments left behind by deleted accounts are removed.
 */
class EnsureBaseline extends Command
{
    protected $signature = 'ehrms:ensure-baseline {--dry-run : Report what would change without changing it}';

    protected $description = 'Fill in missing leave allocations, public holidays and roles (only what is missing)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $done = [];

        // 1. Annual leave allocations for the current leave year.
        $year = LeaveRules::currentLeaveYear();
        $days = SystemConfiguration::current()->annual_leave_days ?: 30;
        $missing = User::where('status', 'Active')
            ->whereNotIn('id', LeaveEntitlement::where('leave_year', $year)->pluck('user_id'))
            ->get(['id', 'name']);
        if ($missing->isNotEmpty()) {
            if (!$dry) {
                foreach ($missing as $user) {
                    LeaveEntitlement::firstOrCreate(
                        ['user_id' => $user->id, 'leave_year' => $year],
                        ['days_due' => $days, 'carried_forward' => 0]
                    );
                }
            }
            $done[] = ["Annual leave allocated for " . LeaveRules::yearLabel($year) . " ({$days} days)", $missing->count(), $missing->pluck('name')->take(5)->implode(', ') . ($missing->count() > 5 ? ' …' : '')];
        }

        // 2. Public holidays for this year and next, only for a year with none.
        foreach ([now()->year, now()->year + 1] as $y) {
            if (PublicHoliday::whereYear('date', $y)->exists()) {
                continue;
            }
            $holidays = UgandaHolidays::forYear($y);
            if (!$dry) {
                foreach ($holidays as $date => $name) {
                    PublicHoliday::firstOrCreate(['date' => $date], ['name' => $name]);
                }
            }
            $done[] = ["Uganda public holidays loaded for {$y}", count($holidays), ''];
        }

        // 3. People without any role get the Employee role.
        $employee = Role::where('slug', 'employee')->first();
        $roleless = User::whereDoesntHave('roles')->get(['id', 'name']);
        if ($employee && $roleless->isNotEmpty()) {
            if (!$dry) {
                foreach ($roleless as $user) {
                    $user->roles()->syncWithoutDetaching([$employee->id]);
                }
            }
            $done[] = ['Employee role given to people with no role', $roleless->count(), $roleless->pluck('name')->implode(', ')];
        }

        // 4. Role assignments of accounts that no longer exist.
        $table = config('admin.database.role_users_table', 'admin_role_users');
        $orphans = DB::table($table)->whereNotIn('user_id', User::query()->select('id'))->count();
        if ($orphans) {
            if (!$dry) {
                DB::table($table)->whereNotIn('user_id', User::query()->select('id'))->delete();
            }
            $done[] = ['Role assignments of deleted accounts removed', $orphans, ''];
        }

        if (!$done) {
            $this->info('Nothing missing: allocations, holidays and roles are all in place.');

            return 0;
        }

        $this->table(['Change', 'Count', 'Who'], $done);
        if ($dry) {
            $this->warn('Dry run: nothing was changed.');
        } else {
            Audit::log('system.baseline', collect($done)->map(fn ($d) => "{$d[0]}: {$d[1]}")->implode('; '));
            $this->info('Done.');
        }

        return 0;
    }
}
