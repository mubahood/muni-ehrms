<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Who may open which part of the system.
 *
 * Deny by default: an area is open to the roles listed for it and to nobody
 * else, and any page not mapped to an area is open to the System Administrator
 * only. What a person then *sees* inside an area is narrowed by Scope
 * (department, faculty, self).
 */
class AccessPolicy
{
    /** Every signed-in person. */
    private const EVERYONE = ['admin', 'us', 'hr', 'dean', 'hod', 'employee'];

    /** People who look after others: see staff in their scope. */
    private const MANAGERS = ['admin', 'us', 'hr', 'dean', 'hod'];

    /** @var array<string, string[]> area => roles */
    public const AREAS = [
        // Everyone's own work
        'dashboard' => self::EVERYONE,
        'my.dashboard' => self::EVERYONE,
        'my.leave' => self::EVERYONE,
        'my.profile' => self::EVERYONE,
        'notifications' => self::EVERYONE,
        'attendance.view' => self::EVERYONE,   // scoped: employees see only their own

        // Looking after staff
        'attendance.correct' => ['admin', 'hr'],
        'employees.view' => self::MANAGERS,
        'employees.manage' => ['admin', 'hr'],
        'organisation.view' => self::MANAGERS,
        'organisation.manage' => ['admin', 'hr'],
        'holidays.view' => self::EVERYONE,
        'holidays.manage' => ['admin', 'hr'],
        'reports' => self::MANAGERS,

        // Leave
        'leave.approve' => ['hod', 'dean', 'hr', 'us'],
        'leave.all' => self::MANAGERS,
        'leave.manage' => ['admin', 'hr'],     // planning, record leave, cancel
        'leave.recall' => ['hr', 'us'],

        // Running the system
        'configuration' => ['admin'],
        'configuration.write' => ['admin'],
        'system.users' => ['admin'],
        'audit' => ['admin', 'hr'],
        'device.events' => ['admin'],
        'imports' => ['admin'],
        'demo.manage' => ['admin'],
        'reports.legacy' => self::MANAGERS,
    ];

    /**
     * Areas a demo account may never use, whatever its role: anything that
     * reaches beyond the sandbox (University-wide settings and holidays, the
     * terminals' raw data, imports, system accounts, the older modules) and
     * the demo controls themselves. Demo accounts still see the settings and
     * holidays pages, read-only.
     */
    public const DEMO_DENIED = [
        'configuration.write', 'holidays.manage', 'system.users', 'device.events', 'imports',
        'demo.manage', 'reports.legacy', 'unmapped',
    ];

    /**
     * Admin paths and the area each belongs to, most specific first. A path
     * maps by prefix; [read area, write area] splits viewing from changing.
     */
    private const ROUTES = [
        'me' => 'my.dashboard',
        'lookup' => 'dashboard',               // pickers: the controller limits results to the viewer's scope
        'my-leave' => 'my.leave',
        'notifications' => 'notifications',
        'auth/setting' => 'my.profile',
        'leave/approvals' => 'leave.approve',
        'leave/planning' => 'leave.manage',
        'leave/record' => 'leave.manage',
        'leave/all' => 'leave.all',
        'leave' => 'my.leave',                 // a request page: the controller checks who may see it
        'staff' => 'employees.view',
        'leaves' => 'leave.all',
        'attendance-records' => ['attendance.view', 'attendance.correct'],
        'attendance-rebuild' => 'imports',
        'users' => ['employees.view', 'employees.manage'],
        'departments' => ['organisation.view', 'organisation.manage'],
        'faculties' => ['organisation.view', 'organisation.manage'],
        'public-holidays' => ['holidays.view', 'holidays.manage'],
        'reports/individual.pdf' => 'my.dashboard', // anyone, for people in their scope (the controller checks)
        'reports' => 'reports',
        'general-reports' => 'reports.legacy',
        'print-general-reports' => 'reports.legacy',
        'api/reports' => 'reports.legacy',
        'process-event-logs' => 'device.events',
        'settings' => ['configuration', 'configuration.write'],
        'demo-data' => 'demo.manage',
        'demo/leave' => 'dashboard',          // back from exploring the demo (the controller checks the session)
        'system-configurations' => 'configuration',
        'audit-log' => 'audit',
        'event-logs-dashboard' => 'device.events',
        'event-logs' => 'device.events',
        'import-user-datas' => 'imports',
        'import-attendance-records' => 'imports',
        'auth/users' => 'system.users',
        'auth/roles' => 'system.users',
        'auth/permissions' => 'system.users',
        'auth/menu' => 'system.users',
        'auth/logs' => 'system.users',
    ];

    public static function allows(?User $user, string $area): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isDemo() && in_array($area, self::DEMO_DENIED, true)) {
            return false;
        }
        $roles = self::AREAS[$area] ?? ['admin'];

        return (bool) array_intersect($roles, self::rolesOf($user));
    }

    /**
     * The area a request belongs to. Unmapped paths fall to 'unmapped', which
     * only the System Administrator may open.
     */
    public static function areaFor(Request $request): string
    {
        $prefix = trim((string) config('admin.route.prefix'), '/');
        $path = trim($request->path(), '/');
        if ($prefix !== '' && strpos($path, $prefix) === 0) {
            $path = trim(substr($path, strlen($prefix)), '/');
        }
        if ($path === '') {
            return 'dashboard';
        }

        $writing = !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            || preg_match('#/(create|edit)$#', $path);

        foreach (self::ROUTES as $route => $area) {
            if ($path === $route || strpos($path, $route . '/') === 0) {
                return is_array($area) ? ($writing ? $area[1] : $area[0]) : $area;
            }
        }

        return 'unmapped';
    }

    public static function allowsRequest(?User $user, Request $request): bool
    {
        return self::allows($user, self::areaFor($request));
    }

    /**
     * Every signed-in person counts as an employee as well as any role they hold.
     *
     * @return string[]
     */
    public static function rolesOf(User $user): array
    {
        return array_values(array_unique(array_merge($user->roleSlugs(), ['employee'])));
    }
}
