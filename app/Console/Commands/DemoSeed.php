<?php

namespace App\Console\Commands;

use App\Services\DemoSandbox;
use Illuminate\Console\Command;

/**
 * Builds the demonstration university (see config/demo.php and
 * App\Services\DemoSandbox). Safe on the production database: everything it
 * creates is marked as demo data, sealed off from the real university, and
 * removed again by ehrms:demo-purge or from Administration → Demo data.
 *
 *   php artisan ehrms:demo-seed            # refuses if the sandbox exists
 *   php artisan ehrms:demo-seed --fresh    # rebuilds it
 */
class DemoSeed extends Command
{
    protected $signature = 'ehrms:demo-seed {--fresh : Delete the existing sandbox and build it again} {--days= : Days of clock-in history (default 90)}';

    protected $description = 'Build the demo sandbox: 15 demo accounts with three months of attendance and leave';

    public function handle(): int
    {
        if (config('ehrms.notify_email')) {
            $this->warn('E-mail notifications are on; demo accounts are never e-mailed, so this is safe.');
        }
        if (DemoSandbox::exists()) {
            if (!$this->option('fresh')) {
                $this->error('The demo sandbox already exists. Use --fresh to rebuild it, or ehrms:demo-purge to remove it.');

                return 1;
            }
            $this->info('Removed ' . DemoSandbox::purge() . ' demo account(s).');
        }

        $stats = (new DemoSandbox())->build($this->option('days') ? (int) $this->option('days') : null);
        $this->table(['', 'Count'], collect($stats)->except('built_at')->map(fn ($v, $k) => [ucfirst(str_replace('_', ' ', $k)), $v])->values()->all());
        $this->info('Sign in as any demo.* account with the password ' . config('demo.password') . '.');

        return 0;
    }
}
