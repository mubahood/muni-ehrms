<?php

namespace App\Support;

use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\LeaveWorkflow;

/**
 * The sidebar menu. Every entry is tied to an AccessPolicy area, so the menu
 * only ever offers pages the person can open. Entries marked 'managers' are
 * hidden from people who look after nobody (they reach the same data through
 * My attendance).
 */
class Navigation
{
    /**
     * @return array<int, array{title:string, items:array<int, array{title:string, uri:string, icon:string, count?:int}>}>
     */
    public static function for(User $user): array
    {
        $isManager = (bool) array_diff(AccessPolicy::rolesOf($user), ['employee']);
        $approvals = AccessPolicy::allows($user, 'leave.approve') ? LeaveWorkflow::pendingFor($user)->count() : 0;

        $groups = [
            'My work' => [
                ['Dashboard', '/', 'fa-th-large', 'dashboard'],
                ['My attendance', 'me', 'fa-calendar-check-o', 'my.dashboard'],
                ['My leave', 'my-leave', 'fa-plane', 'my.leave'],
            ],
            'Leave' => [
                ['Leave approvals', 'leave/approvals', 'fa-check-square-o', 'leave.approve', $approvals],
                ['All leave', 'leave/all', 'fa-list-ul', 'leave.all'],
                ['Leave planning', 'leave/planning', 'fa-sliders', 'leave.manage'],
                ['Record leave', 'leave/record', 'fa-pencil-square-o', 'leave.manage'],
            ],
            'Attendance' => [
                ['Attendance records', 'attendance-records', 'fa-clock-o', 'attendance.view', null, true],
                ['Reports', 'reports', 'fa-file-text-o', 'reports'],
            ],
            'Organisation' => [
                ['Employees', 'users', 'fa-users', 'employees.view'],
                ['Departments', 'departments', 'fa-sitemap', 'organisation.view'],
                ['Faculties', 'faculties', 'fa-university', 'organisation.view'],
                ['Public holidays', 'public-holidays', 'fa-calendar', 'holidays.view', null, true],
            ],
            'Administration' => [
                ['System settings', 'settings', 'fa-cog', 'configuration'],
                ['System users', 'auth/users', 'fa-key', 'system.users'],
                ['Audit log', 'audit-log', 'fa-shield', 'audit'],
                ['Device events', 'event-logs', 'fa-podcast', 'device.events'],
                ['Import attendance', 'import-attendance-records', 'fa-upload', 'imports'],
                ['Demo data', 'demo-data', 'fa-flask', 'demo.manage'],
            ],
        ];

        $menu = [];
        foreach ($groups as $title => $entries) {
            $items = [];
            foreach ($entries as $entry) {
                [$label, $uri, $icon, $area] = $entry;
                $count = $entry[4] ?? null;
                $managersOnly = $entry[5] ?? false;
                if (!AccessPolicy::allows($user, $area) || ($managersOnly && !$isManager)) {
                    continue;
                }
                $items[] = array_filter(['title' => $label, 'uri' => $uri, 'icon' => $icon, 'count' => $count]);
            }
            if ($items) {
                $menu[] = ['title' => $title, 'items' => $items];
            }
        }

        return $menu;
    }

    /** Whether a menu uri is the page being shown (or a page under it). */
    public static function isActive(string $uri, string $path): bool
    {
        $uri = trim($uri, '/');
        $path = trim($path, '/');
        if ($uri === '') {
            return $path === '';
        }

        return $path === $uri || strpos($path, $uri . '/') === 0;
    }
}
