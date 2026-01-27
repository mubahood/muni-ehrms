<?php

namespace App\Providers;

use App\Models\EventLog;
use App\Observers\EventLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register EventLog observer for automatic attendance processing
        EventLog::observe(EventLogObserver::class);
    }
}
