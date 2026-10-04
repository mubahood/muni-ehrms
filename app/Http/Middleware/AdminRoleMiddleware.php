<?php

namespace App\Http\Middleware;

use App\Services\AccessPolicy;
use Closure;
use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The gate in front of every page of the system.
 *
 * Signed-out visitors go to the sign-in page. Signed-in people reach a page
 * only if their role is allowed into its area (App\Services\AccessPolicy);
 * pages not assigned to any area are for the System Administrator alone.
 * Every refusal is logged.
 */
class AdminRoleMiddleware
{
    /** Pages that must work without being signed in. */
    private const OPEN = ['auth/login', 'auth/logout'];

    public function handle(Request $request, Closure $next, ...$ignored)
    {
        $path = trim($request->path(), '/');
        foreach (self::OPEN as $open) {
            if ($path === $open || substr($path, -strlen($open) - 1) === '/' . $open) {
                return $next($request);
            }
        }

        $user = Admin::guard()->guest() ? null : Admin::user();
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return redirect()->guest(admin_url('auth/login'));
        }

        // An account found with a weak password must choose a new one first.
        if ($user->must_change_password && !$user->isDemo() && !preg_match('#(^|/)auth/(setting|logout)$#', $path)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Please choose a new password before continuing.'], 403);
            }
            session()->flash('ehr_password_notice', true);

            return redirect(admin_url('auth/setting'));
        }

        $area = AccessPolicy::areaFor($request);
        if (AccessPolicy::allows($user, $area)) {
            return $next($request);
        }

        Log::warning('SECURITY: access refused', [
            'user_id' => $user->id,
            'username' => $user->username,
            'roles' => AccessPolicy::rolesOf($user),
            'area' => $area,
            'path' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['error' => 'You do not have access to this page.'], 403);
        }

        return response()->view('errors.no-access', ['user' => $user], 403);
    }
}
