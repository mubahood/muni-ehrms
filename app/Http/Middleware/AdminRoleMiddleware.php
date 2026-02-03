<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Auth\Database\Administrator;
use App\Services\AccessControlService;

class AdminRoleMiddleware
{
    /**
     * Handle an incoming request - BULLETPROOF ADMIN ACCESS CONTROL
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission = null)
    {
        $path = $request->path();
        
        // Allow ALL authentication-related routes to bypass all checks
        if (str_contains($path, 'auth/login') || str_contains($path, 'auth/logout') || str_contains($path, 'auth/setting') ||
            str_contains($path, '/login') || str_contains($path, '/logout') ||
            $path === 'auth/login' || $path === 'auth/logout' || 
            $path === 'login' || $path === 'logout') {
            return $next($request);
        }
        
        // STEP 1: Check authentication
        if (Admin::guard()->guest()) {
            return $this->redirectToLogin($request);
        }
        
        $user = Admin::user();
        if (!$user) {
            return $this->redirectToLogin($request);
        }
        
        // STEP 2: BULLETPROOF CHECK - Only admin role users can access ANY admin routes
        // This is the core security enforcement the user requested
        if (!$user->isRole('admin')) {
            // Log unauthorized access attempt for security monitoring
            \Log::warning('SECURITY: Non-admin user attempted admin access', [
                'user_id' => $user->id,
                'username' => $user->username ?? 'unknown',
                'user_role' => $user->role ?? 'unknown',
                'attempted_path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now(),
            ]);
            
            return $this->denyAccess($request, 'Administrative privileges required');
        }
        
        // STEP 3: Additional permission check if specified
        if ($permission) {
            $accessControl = app(AccessControlService::class);
            if (!$accessControl->hasAccess($permission)) {
                return $this->denyAccess($request, "Missing required permission: {$permission}");
            }
        }
        
        return $next($request);
    }
    
    /**
     * Redirect to admin login
     */
    protected function redirectToLogin(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $loginPath = config('admin.route.prefix', '') . '/auth/login';
        return redirect()->to($loginPath)->with('message', 'Please log in to continue');
    }
    
    /**
     * Deny access with appropriate response
     */
    protected function denyAccess(Request $request, string $message = 'Access denied')
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }
        
        // For non-JSON requests, show error and redirect
        admin_toastr($message, 'error');
        
        // If trying to access admin root, redirect to login
        $adminPrefix = config('admin.route.prefix', '');
        if ($request->path() === $adminPrefix || $request->path() === $adminPrefix . '/') {
            return $this->redirectToLogin($request);
        }
        
        // Otherwise show 403 error
        abort(403, $message);
    }
}