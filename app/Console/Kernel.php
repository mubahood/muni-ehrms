<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // The host runs `schedule:run` every 20 minutes (:00, :20, :40) and caps the
        // number of processes per account, so every task runs inside the scheduler's
        // own process (call(), never command(), which forks a new PHP process) and
        // on a minute the cron actually reaches.

        // Turn device punches that arrived since the last run into attendance.
        $schedule->call(function () {
            app(\App\Services\AttendanceProcessingService::class)->processBatchUnprocessedEvents(500);
        })
            ->name('attendance:process-events')
            ->cron('*/20 * * * *')
            ->withoutOverlapping(30);

        // Keep today current: who is in, late, on leave, and who is not in yet.
        $schedule->call(function () {
            app(\App\Services\AttendanceEngine::class)->processDay(today());
        })
            ->name('attendance:refresh-today')
            ->cron('*/20 * * * *')
            ->withoutOverlapping(30);

        // Settle the day that just ended (absences, early departures, half days).
        $schedule->call(function () {
            \Illuminate\Support\Facades\Artisan::call('attendance:evaluate-eod');
        })
            ->name('attendance:evaluate-eod')
            ->dailyAt('00:20')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping(60);

        // The demo sandbox: today's simulated clock-ins as the day goes on, and
        // a fresh build every Sunday night so it always shows the last three
        // months with leave at every stage. Nothing happens without demo data.
        $schedule->call(function () {
            if (\App\Services\DemoSandbox::exists()) {
                (new \App\Services\DemoSandbox())->topUp();
            }
        })
            ->name('ehrms:demo-top-up')
            ->cron('*/20 * * * *')
            ->withoutOverlapping(30);

        $schedule->call(function () {
            if (config('demo.weekly_reset') && \App\Services\DemoSandbox::exists()) {
                \App\Services\DemoSandbox::purge();
                (new \App\Services\DemoSandbox())->build();
            }
        })
            ->name('ehrms:demo-weekly-reset')
            ->weeklyOn(0, '01:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping(60);

        // Fill in anything missing: leave allocations for new staff or a new
        // leave year, next year's public holidays, roles. Never overwrites.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Artisan::call('ehrms:ensure-baseline');
        })
            ->name('ehrms:ensure-baseline')
            ->dailyAt('00:40')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping(60);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
