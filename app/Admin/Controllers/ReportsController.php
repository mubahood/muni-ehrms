<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\Audit;
use App\Services\Reports;
use App\Services\Scope;
use Carbon\Carbon;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Reports, as PDF: an individual's attendance, a summary for any group in
 * the viewer's scope, a daily register and a leave report.
 */
class ReportsController extends Controller
{
    public function index(Content $content)
    {
        /** @var User $me */
        $me = Admin::user();

        return $content->title('Reports')
            ->description('PDF and spreadsheet reports for ' . Str::lower(Scope::label($me)))
            ->body(view('ehrms.reports', [
                'scopes' => Reports::scopeOptions($me),
                'lastWorkingDay' => \App\Services\AttendanceStats::referenceDay(Scope::userIds($me)),
            ]));
    }

    /** The same report as a spreadsheet (CSV, opens in Excel), from the same figures as the PDF. */
    public function csv(Request $request, string $type)
    {
        return $this->pdf($request, $type, 'csv');
    }

    public function pdf(Request $request, string $type, string $format = 'pdf')
    {
        /** @var User $me */
        $me = Admin::user();
        [$from, $to] = Reports::period($request->input('period'), $request->input('from'), $request->input('to'));

        switch ($type) {
            case 'individual':
                $user = User::with('department')->findOrFail((int) $request->input('user', $me->id));
                abort_unless(Scope::canSee($me, (int) $user->id), 403);
                if ($request->filled('from') && !$request->filled('period')) {
                    [$from, $to] = Reports::period('custom', $request->input('from'), $request->input('to'));
                }
                $data = Reports::individual($user, $from, $to);
                $file = 'Attendance ' . $user->displayName() . ' ' . $from->format('Y-m-d');
                $pdf = $format === 'csv' ? null : Reports::pdf('pdf.individual', $data, 'portrait', $me->displayName());
                break;
            case 'summary':
                abort_unless(AccessPolicy::allows($me, 'reports'), 403);
                [$label, $ids] = Reports::resolveScope($me, (string) $request->input('scope', 'university'));
                $data = Reports::summary($label, $ids, $from, $to);
                $file = 'Attendance summary ' . $label . ' ' . $from->format('Y-m-d');
                $pdf = $format === 'csv' ? null : Reports::pdf('pdf.summary', $data, 'landscape', $me->displayName());
                break;
            case 'daily':
                abort_unless(AccessPolicy::allows($me, 'reports'), 403);
                [$label, $ids] = Reports::resolveScope($me, (string) $request->input('scope', 'university'));
                $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today();
                $data = Reports::daily($label, $ids, $date);
                $file = 'Daily register ' . $label . ' ' . $date->format('Y-m-d');
                $pdf = $format === 'csv' ? null : Reports::pdf('pdf.daily', $data, 'portrait', $me->displayName());
                break;
            case 'leave':
                abort_unless(AccessPolicy::allows($me, 'reports'), 403);
                [$label, $ids] = Reports::resolveScope($me, (string) $request->input('scope', 'university'));
                $data = Reports::leave($label, $ids, $from, $to);
                $file = 'Leave report ' . $label . ' ' . $from->format('Y-m-d');
                $pdf = $format === 'csv' ? null : Reports::pdf('pdf.leave', $data, 'landscape', $me->displayName());
                break;
            default:
                abort(404);
        }

        Audit::log('report.downloaded', $data['title'] . ': ' . $data['subtitle'] . ($format === 'csv' ? ' (spreadsheet)' : ''));

        if ($format === 'csv') {
            return Reports::csv($type, $data, Str::slug($file) . '.csv');
        }

        return $pdf->stream(Str::slug($file) . '.pdf');
    }
}
