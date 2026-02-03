<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Encore\Admin\Facades\Admin;
use App\Admin\Extensions\PermissionChecker;

class AdminAccessControlMiddleware
{
    /**
     * Handle an incoming request - comprehensive access control
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $adminPrefix = config('admin.route.prefix', '');
        $path = $request->path();
        
        // If admin prefix is empty, all routes are potentially admin routes
        // If admin prefix is set, check if path starts with it
        if ($adminPrefix !== '') {
            if (!str_starts_with($path, $adminPrefix . '/')) {
                return $next($request);
            }
        }
        
        // Allow ALL authentication-related routes to bypass checks
        if (str_contains($path, 'auth/login') || 
            str_contains($path, 'auth/logout') || 
            str_contains($path, 'auth/setting') ||
            str_contains($path, '/login') ||
            str_contains($path, '/logout') ||
            $path === 'auth/login' ||
            $path === 'auth/logout' ||
            $path === 'login' ||
            $path === 'logout') {
            return $next($request);
        }
        
        $user = Admin::user();
        
        // Must be authenticated for admin routes
        if (!$user) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            admin_toastr('Please log in to access this resource.', 'error');
            return redirect(admin_base_path('auth/login'));
        }
        
        // Check route-specific permissions
        if (!PermissionChecker::checkRouteAccess($request)) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Access denied. Insufficient permissions.'], 403);
            }
            admin_toastr('Access denied. You do not have permission to access this resource.', 'error');
            return redirect(admin_url('/'));
        }
        
        return $next($request);
    }
}