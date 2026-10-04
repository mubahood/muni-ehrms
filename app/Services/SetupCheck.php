<?php

namespace App\Services;

use App\Models\Department;
use App\Models\EventLog;
use App\Models\Faculty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gaps in the real university's set-up that make figures wrong or leave
 * holes, shown to the System Administrator and Human Resource on the
 * dashboard until they are fixed. Never shown inside the demo sandbox.
 */
class SetupCheck
{
    /** @return array<int, array{icon:string, title:string, detail:string, url:?string, action:?string}> */
    public static function items(User $viewer): array
    {
        if ($viewer->isDemo() || !$viewer->hasAnyRole('admin', 'hr')) {
            return [];
        }
        $items = [];
        $real = User::where('is_demo', false)->where('status', 'Active');

        $weak = (clone $real)->where('must_change_password', true)->get(['name']);
        if ($weak->isNotEmpty() && $viewer->hasAnyRole('admin')) {
            $items[] = ['fa-lock', $weak->count() . ' account(s) must choose a new password',
                'Their old password was too easy to guess; they are asked for a new one at their next sign-in: ' . $weak->pluck('name')->implode(', ') . '.', null, null];
        }

        $noTerminal = (clone $real)->where(fn ($q) => $q->whereNull('employee_no')->orWhere('employee_no', ''))->count();
        if ($noTerminal) {
            $items[] = ['fa-id-badge', "{$noTerminal} staff without a Terminal ID",
                'Clock-ins can only be matched to a person whose record carries the ID they are enrolled under on the terminal.', admin_url('users'), 'Employees'];
        }

        $unlinked = EventLog::query()
            ->where('process_status', 'failed')->whereNull('user_id')
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', \App\Support\DemoManifest::EVENT_SOURCE))
            ->where('event_time', '>=', today()->subDays(30))
            ->whereNotNull('employee_no')->where('employee_no', '!=', '')
            ->selectRaw('employee_no, MAX(employee_name) AS name, COUNT(*) AS n, MAX(event_time) AS last')
            ->groupBy('employee_no')->orderByDesc('n')->limit(6)->get();
        if ($unlinked->isNotEmpty()) {
            $items[] = ['fa-user-times', $unlinked->count() . ' person(s) clocking in who match no employee',
                'Last 30 days: ' . $unlinked->map(fn ($u) => ($u->name ?: 'unnamed') . " (ID {$u->employee_no}, {$u->n}×)")->implode(', ')
                . '. Give the right employee that Terminal ID, or add them.', admin_url('users'), 'Employees'];
        }

        $noDept = (clone $real)->whereNull('department_id')->count();
        if ($noDept) {
            $items[] = ['fa-sitemap', "{$noDept} staff without a department", 'They are left out of department and faculty reports and their leave skips the Head of Department.', admin_url('users'), 'Employees'];
        }

        if (!Faculty::where('is_demo', false)->where('is_active', true)->exists()) {
            $items[] = ['fa-university', 'No faculties are set up', 'Academic departments belong to a faculty; its Dean recommends leave and sees faculty figures.', admin_url('faculties/create'), 'Add a faculty'];
        }

        // A terminal whose clock or time zone is wrong: punch times are being corrected.
        $skewed = EventLog::whereNotNull('event_time_raw')->where('event_time', '>=', today()->subDays(7))
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', \App\Support\DemoManifest::EVENT_SOURCE))
            ->orderByDesc('id')->limit(20)->get(['event_time', 'event_time_raw'])
            ->map(function ($e) {
                try {
                    $wall = Carbon::parse(preg_replace('/(Z|[+-]\d{2}:?\d{2})$/', '', $e->event_time_raw));

                    return (int) round(($wall->getTimestamp() - Carbon::parse($e->getRawOriginal('event_time'))->getTimestamp()) / 3600);
                } catch (\Throwable $x) {
                    return 0;
                }
            })->filter()->first();
        if ($skewed) {
            $items[] = ['fa-clock-o', "A terminal's clock is {$skewed} hour(s) off",
                'Punch times are corrected automatically, but set the terminal to time zone UTC+03:00 (Kampala) with automatic time sync.', null, null];
        }

        return $items;
    }
}
