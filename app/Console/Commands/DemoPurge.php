<?php

namespace App\Console\Commands;

use App\Services\DemoSandbox;
use Illuminate\Console\Command;

/** Removes the demo sandbox: every demo account and everything about it. Real data is not touched. */
class DemoPurge extends Command
{
    protected $signature = 'ehrms:demo-purge {--force : Do not ask for confirmation}';

    protected $description = 'Delete every demo account, its attendance and leave, and the demo departments and faculties';

    public function handle(): int
    {
        if (!DemoSandbox::exists()) {
            $this->info('There is no demo data.');

            return 0;
        }
        if (!$this->option('force') && !$this->confirm('Delete all demo accounts and their data?')) {
            return 1;
        }
        $this->info('Removed ' . DemoSandbox::purge() . ' demo account(s) and all their data.');

        return 0;
    }
}
