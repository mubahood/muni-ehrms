<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The audit trail: sign-ins (and failed attempts), corrections, leave
 * decisions, report downloads and setting changes, with who and from where.
 */
class AuditLogController extends Controller
{
    public function index(Content $content, Request $request)
    {
        // Each world sees its own trail: demo accounts only demo activity; real
        // staff everything else (sign-in failures and system jobs included).
        $demoIds = \App\Models\User::where('is_demo', true)->select('id');
        $mine = fn ($q) => Admin::user()->isDemo()
            ? $q->whereIn('user_id', $demoIds)
            : $q->where(fn ($w) => $w->whereNull('user_id')->orWhereNotIn('user_id', $demoIds));
        $query = $mine(AuditLog::query())
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->input('action') . '%'))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->where(fn ($w) => $w->where('user_name', 'like', $term)->orWhere('description', 'like', $term)->orWhere('ip_address', 'like', $term));
            })
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay()));

        $failedToday = $mine(AuditLog::where('action', 'auth.failed')->where('created_at', '>=', today()))->count();

        return $content->title('Audit log')
            ->description('Who did what, and when')
            ->body(view('ehrms.audit', [
                'logs' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(40)->withQueryString(),
                'filters' => $request->only(['action', 'q', 'from', 'to']),
                'failedToday' => $failedToday,
                'groups' => [
                    'auth' => 'Sign-ins', 'attendance' => 'Attendance', 'leave' => 'Leave',
                    'report' => 'Reports', 'settings' => 'Settings', 'holiday' => 'Holidays',
                ],
            ]));
    }
}
