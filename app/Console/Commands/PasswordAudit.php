<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Finds real accounts whose password is easy to guess (a well-known
 * password, or their own username) and, with --flag, makes them choose a new
 * one at their next sign-in. Passwords are never shown.
 */
class PasswordAudit extends Command
{
    protected $signature = 'ehrms:password-audit {--flag : Require the weak accounts to choose a new password}';

    protected $description = 'Find accounts with easily guessed passwords';

    private const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', '123456', '1234567', '12345678', '123456789', '1234567890', '12345', '1234', '4321',
        '111111', '000000', 'qwerty', 'qwerty123', 'abc123', 'admin', 'admin123', 'administrator', 'root', 'secret', 'test', 'test123', 'testing',
        'welcome', 'welcome1', 'letmein', 'changeme', 'default', 'muni', 'muni123', 'muni2026', 'muni@2026', 'university', 'employee', 'staff',
        'hod', 'hr', 'dean', 'user', 'guest', 'Demo@2026', 'demo', 'uganda', 'arua', 'kampala',
    ];

    public function handle(): int
    {
        $weak = [];
        foreach (User::where('is_demo', false)->where('status', 'Active')->get() as $user) {
            $guesses = array_unique(array_merge(self::COMMON, array_filter([
                $user->username, strtolower((string) $user->username), $user->first_name, strtolower((string) $user->first_name),
                $user->last_name, strtolower((string) $user->last_name), $user->employee_no,
            ])));
            foreach ($guesses as $guess) {
                if ($guess !== '' && Hash::check($guess, (string) $user->password)) {
                    $weak[] = $user;
                    break;
                }
            }
        }

        if (!$weak) {
            $this->info('No active account uses an easily guessed password.');

            return 0;
        }
        $this->table(['Id', 'Username', 'Name'], array_map(fn ($u) => [$u->id, $u->username, $u->name], $weak));

        if ($this->option('flag')) {
            foreach ($weak as $user) {
                $user->forceFill(['must_change_password' => true])->save();
            }
            Audit::log('security.passwords', count($weak) . ' account(s) with an easily guessed password must choose a new one');
            $this->warn(count($weak) . ' account(s) must now choose a new password at their next sign-in.');
        } else {
            $this->warn(count($weak) . ' weak password(s). Run with --flag to require new ones.');
        }

        return 0;
    }
}
