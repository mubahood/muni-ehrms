<?php

namespace App\Admin\Controllers;

use App\Exceptions\LeaveActionRefused;
use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\Audit;
use App\Services\LeaveRules;
use App\Services\LeaveWorkflow;
use App\Services\Scope;
use Barryvdh\DomPDF\Facade\Pdf;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * One leave request: where it is, who has it, its trail, and the actions the
 * person looking at it may take. Also its printable leave form.
 */
class LeaveRequestController extends Controller
{
    public function show(Content $content, $id)
    {
        /** @var User $me */
        $me = Admin::user();
        $leave = Leave::with(['user.department.faculty', 'actingUser', 'actions', 'recalledBy'])->findOrFail($id);
        abort_unless(self::canSee($me, $leave), 403);

        $isApplicant = (int) $leave->user_id === (int) $me->id;
        $data = [
            'leave' => $leave,
            'me' => $me,
            'steps' => LeaveWorkflow::timeline($leave),
            'canAct' => LeaveWorkflow::canAct($me, $leave),
            'canWithdraw' => $isApplicant && $leave->status === Leave::PENDING,
            'canCancel' => AccessPolicy::allows($me, 'leave.manage') && $leave->status === Leave::APPROVED && !$leave->hasStarted(),
            'canRecall' => AccessPolicy::allows($me, 'leave.recall') && $leave->status === Leave::APPROVED && $leave->hasStarted(),
            'balance' => $leave->leave_year ? LeaveRules::annualBalance($leave->user, $leave->leave_year, $leave) : null,
            'last' => LeaveRules::lastLeaveTaken($leave->user, $leave->start_date),
            'nextWorkingDay' => LeaveRules::returnDateAfter(today()->subDay())->max($leave->start_date->copy()->addDay()),
        ];

        $crumbs = (int) $leave->user_id === (int) $me->id
            ? [['text' => 'My leave', 'url' => 'my-leave']]
            : (AccessPolicy::allows($me, 'leave.all') ? [['text' => 'All leave', 'url' => 'leave/all']] : [['text' => 'Leave approvals', 'url' => 'leave/approvals']]);
        $crumbs[] = ['text' => $leave->reference ?: 'Request'];

        return $content->breadcrumb(...$crumbs)->title($leave->reference ?: 'Leave request')
            ->description($leave->typeLabel() . ' · ' . $leave->user->name)
            ->body(view('ehrms.leave.show', $data));
    }

    public function act(Request $request, $id, string $action)
    {
        /** @var User $me */
        $me = Admin::user();
        $leave = Leave::with('user')->findOrFail($id);
        abort_unless(self::canSee($me, $leave), 403);
        $comment = trim((string) $request->input('comment', ''));

        try {
            switch ($action) {
                case 'approve':
                    $stage = $leave->stage;
                    $leave = LeaveWorkflow::approve($leave, $me, $comment);
                    $done = $leave->status === Leave::APPROVED
                        ? 'Leave approved. The applicant has been told.'
                        : LeaveAction::LABELS[LeaveWorkflow::STAGE_VERB[$stage]] . '. The request is now with the ' . $leave->stageLabel() . '.';
                    break;
                case 'reject':
                    LeaveWorkflow::reject($leave, $me, $comment);
                    $done = 'The request was not approved. The applicant has been told why.';
                    break;
                case 'withdraw':
                    LeaveWorkflow::withdraw($leave, $me, $comment);
                    $done = 'Your request was withdrawn.';
                    break;
                case 'cancel':
                    LeaveWorkflow::cancel($leave, $me, $comment);
                    $done = 'The leave was cancelled and its days returned to the balance.';
                    break;
                case 'recall':
                    $request->validate(['resume_date' => 'required|date']);
                    $leave = LeaveWorkflow::recall($leave, $me, $request->input('resume_date'), $comment);
                    $done = "Recall recorded: {$leave->days_restored} day(s) restored. The employee and their Head have been told.";
                    break;
                default:
                    abort(404);
            }
        } catch (LeaveActionRefused $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->forbidden ? 403 : 422);
            }
            admin_toastr($e->getMessage(), 'error');

            return back()->withInput()->with('leave_error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            $fresh = Leave::find($id);

            return response()->json([
                'message' => $done,
                'status' => $fresh->status,
                'stage' => $fresh->stage,
                'redirect' => $request->boolean('stay') ? null : admin_url('leave/' . $id),
                'waiting' => LeaveWorkflow::pendingFor($me)->count(),
            ]);
        }
        admin_toastr($done);

        return redirect(admin_url('leave/' . $id));
    }

    /** The University's leave application form, filled in from the request and its trail. */
    public function form($id)
    {
        /** @var User $me */
        $me = Admin::user();
        $leave = Leave::with(['user.department.faculty', 'actingUser', 'actions'])->findOrFail($id);
        abort_unless(self::canSee($me, $leave), 403);

        $acts = $leave->actions;
        $by = fn (string $stage) => $acts->filter(fn ($a) => $a->stage === $stage && $a->action !== LeaveAction::SUBMITTED)->last();

        $pdf = Pdf::loadView('pdf.leave-form', [
            'leave' => $leave,
            'config' => SystemConfiguration::current(),
            'hod' => $by(Leave::STAGE_HOD),
            'dean' => $by(Leave::STAGE_DEAN),
            'hr' => $by(Leave::STAGE_HR),
            'us' => $by(Leave::STAGE_US),
            'submitted' => $acts->firstWhere('action', LeaveAction::SUBMITTED) ?: $acts->firstWhere('action', LeaveAction::RECORDED),
            'last' => LeaveRules::lastLeaveTaken($leave->user, $leave->start_date),
            'balance' => $leave->leave_year ? LeaveRules::annualBalance($leave->user, $leave->leave_year, $leave) : null,
            'generatedBy' => $me->displayName(),
            'generatedAt' => now(),
        ])->setPaper('a4', 'portrait')->setOption('isPhpEnabled', true)->setOption('isFontSubsettingEnabled', true);

        Audit::log('report.downloaded', "Leave form {$leave->reference} for {$leave->user->name}", $leave);

        return $pdf->stream(($leave->reference ?: 'leave-' . $leave->id) . '.pdf');
    }

    /**
     * The applicant, whoever must act on it now, whoever acted on it before,
     * the person left in charge, and managers whose scope includes the applicant.
     */
    public static function canSee(User $me, Leave $leave): bool
    {
        if ((int) $leave->user_id === (int) $me->id || (int) $leave->acting_user_id === (int) $me->id) {
            return true;
        }
        if (AccessPolicy::allows($me, 'leave.all') && Scope::canSee($me, (int) $leave->user_id)) {
            return true;
        }
        if (LeaveWorkflow::canAct($me, $leave)) {
            return true;
        }

        return $leave->actions()->where('actor_id', $me->id)->exists();
    }
}
