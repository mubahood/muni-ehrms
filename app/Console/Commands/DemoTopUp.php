<?php

namespace App\Console\Commands;

use App\Services\DemoSandbox;
use Illuminate\Console\Command;

/** Adds today's simulated clock-ins for demo staff (up to now), so the sandbox stays current. */
class DemoTopUp extends Command
{
    protected $signature = 'ehrms:demo-top-up';

    protected $description = 'Add the day\'s simulated clock-ins for demo staff';

    public function handle(): int
    {
        $this->info((new DemoSandbox())->topUp() . ' demo clock-in(s) added.');

        return 0;
    }
}
