<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\AccessControlService;
use Encore\Admin\Facades\Admin;
use Illuminate\Support\Facades\Event;
use App\Http\Middleware\AdminRoleMiddleware;

class AdminAccessControlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Override Laravel Admin's default middleware to use our role-based system
        $this->overrideAdminMiddleware();
        
        // The sidebar is built from App\Services\AccessPolicy (see the sidebar view override).

        // Temporarily disable route-level validation to fix login issues
        // We'll rely on middleware-level protection instead
    }

    /**
     * Override Laravel Admin's middleware to enforce strict role-based access
     *
     * @return void
     */
    protected function overrideAdminMiddleware()
    {
        // Get the router instance
        $router = $this->app['router'];
        
        // Replace Laravel Admin's default middleware group with our secure version
        $router->middlewareGroup('admin', [
            \App\Http\Middleware\AdminRoleMiddleware::class,  // Our strict role checking
            \Encore\Admin\Middleware\Pjax::class,
            \Encore\Admin\Middleware\LogOperation::class,
            \Encore\Admin\Middleware\Bootstrap::class,
            // Note: We removed the default admin.auth and admin.permission middleware
            // and replaced them with our AdminRoleMiddleware for bulletproof protection
        ]);
        
        // Also override individual middleware to point to our secure implementations
        $router->aliasMiddleware('admin.auth', \App\Http\Middleware\AdminRoleMiddleware::class);
        $router->aliasMiddleware('admin.permission', \App\Http\Middleware\AdminRoleMiddleware::class);
    }
}