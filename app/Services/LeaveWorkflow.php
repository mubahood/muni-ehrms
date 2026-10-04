<?php

namespace App\Services;

use App\Exceptions\LeaveActionRefused;
use App\Models\Leave;
use App\Models\LeaveAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The leave approval process.
 *
 * Route, fixed when the request is submitted:
 *
 *   academic staff        Head of Department → Faculty Dean → Human Resource → University Secretary
 *   administrative staff  Head of Department → Human Resource → University Secretary
 *
 *  - A Head of Department applying skips their own stage; a Dean skips the
 *    Head and Dean stages.
 *  - A department with no Head (or a faculty with no Dean) is passed
 *    automatically and the trail says so. Human Resource and the University
 *    Secretary are never skipped: the request waits for them.
 *  - Nobody acts on their own request, and only the people at the current
 *    stage can act.
 *  - Every step tells the applicant where the request is, and tells whoever
 *    must act next.
 */
class LeaveWorkflow
{
    /** What each stage's officer does when they agree. */
    public const STAGE_VERB = [
        Leave::STAGE_HOD => LeaveAction::RECOMMENDED,
        Leave::STAGE_DEAN => LeaveAction::RECOMMENDED,
        Leave::STAGE_HR => LeaveAction::VERIFIED,
        Leave::STAGE_US => LeaveAction::APPROVED,
    ];

    private const SKIPPABLE = [Leave::STAGE_HOD, Leave::STAGE_DEAN];

    /*
    |--------------------------------------------------------------------------
    | Route and approvers
    |--------------------------------------------------------------------------
    */

    /** @return string[] */
    public static function buildRoute(User $applicant): array
    {
        $department = $applicant->department;
        $faculty = $department ? $department->faculty : null;

        $isHod = $department && (
            (int) $department->hod_id === (int) $applicant->id
            || (!$department->hod_id && $applicant->hasAnyRole('hod'))
        );
        $isDean = $department && $department->isAcademic() && $faculty && (
            (int) $faculty->dean_id === (int) $applicant->id
            || (!$faculty->dean_id && $applicant->hasAnyRole('dean'))
        );

        $route = [];
        if (!$isHod && !$isDean) {
            $route[] = Leave::STAGE_HOD;
        }
        if ($department && $department->isAcademic() && !$isDean) {
            $route[] = Leave::STAGE_DEAN;
        }

        return array_merge($route, [Leave::STAGE_HR, Leave::STAGE_US]);
    }

    /** The people who may act at $stage on this request (never the applicant). */
    public static function stageApprovers(Leave $leave, string $stage): Collection
    {
        $applicant = $leave->user;
        $department = optional($applicant)->department;
        $active = fn (string $role) => User::query()
            ->where('status', 'Active')
            ->whereHas('roles', fn (Builder $q) => $q->where('slug', $role));

        switch ($stage) {
            case Leave::STAGE_HOD:
                if (!$department) {
                    return new Collection();
                }
                $query = $department->hod_id
                    ? $active('hod')->where('id', $department->hod_id)
                    : $active('hod')->where('department_id', $department->id);
                break;
            case Leave::STAGE_DEAN:
                $faculty = $department ? $department->faculty : null;
                if (!$faculty || !$faculty->dean_id) {
                    return new Collection();
                }
                $query = $active('dean')->where('id', $faculty->dean_id);
                break;
            case Leave::STAGE_HR:
                $query = $active('hr');
                break;
            case Leave::STAGE_US:
                $query = $active('us');
                break;
            default:
                return new Collection();
        }

        // Approvers come from the applicant's own world: a demo HR officer never
        // decides real leave, and real staff never see demo requests.
        return $query->where('id', '!=', $leave->user_id)
            ->where('is_demo', (bool) optional($applicant)->is_demo)
            ->orderBy('name')->get();
    }

    public static function canAct(User $user, Leave $leave): bool
    {
        return $leave->status === Leave::PENDING
            && $leave->stage
            && self::stageApprovers($leave, $leave->stage)->contains('id', $user->id);
    }

    /** Requests waiting for this person to act on. */
    public static function pendingFor(User $user): Builder
    {
        $query = Leave::query()->with('user.department')
            ->where('status', Leave::PENDING)
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', fn (Builder $u) => $u->where('is_demo', $user->isDemo()));

        return $query->where(function (Builder $q) use ($user) {
            $none = true;
            if ($user->hasAnyRole('hod')) {
                $none = false;
                $headed = $user->headedDepartments()->pluck('id')->all();
                $q->orWhere(function (Builder $r) use ($user, $headed) {
                    $r->where('stage', Leave::STAGE_HOD)->whereHas('user', function (Builder $u) use ($user, $headed) {
                        $u->whereIn('department_id', $headed)
                            ->orWhereHas('department', function (Builder $d) use ($user) {
                                $d->whereNull('hod_id')->where('id', $user->department_id);
                            });
                    });
                });
            }
            if ($user->hasAnyRole('dean')) {
                $none = false;
                $faculties = $user->deanOfFaculties()->pluck('id')->all();
                $q->orWhere(function (Builder $r) use ($faculties) {
                    $r->where('stage', Leave::STAGE_DEAN)
                        ->whereHas('user.department', fn (Builder $d) => $d->whereIn('faculty_id', $faculties));
                });
            }
            if ($user->hasAnyRole('hr')) {
                $none = false;
                $q->orWhere('stage', Leave::STAGE_HR);
            }
            if ($user->hasAnyRole('us')) {
                $none = false;
                $q->orWhere('stage', Leave::STAGE_US);
            }
            if ($none) {
                $q->whereRaw('1 = 0');
            }
        })->orderBy('submitted_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Steps
    |--------------------------------------------------------------------------
    */

    /** Submit a new online application (already checked by LeaveRules::check). */
    public static function submit(Leave $leave, User $by): Leave
    {
        return DB::transaction(function () use ($leave, $by) {
            $leave->source = Leave::SOURCE_APPLICATION;
            $leave->status = Leave::PENDING;
            $leave->route = implode(',', self::buildRoute($leave->user));
            $leave->submitted_at = now();
            $leave->recorded_by = $by->id;
            $leave->save();
            self::assignReference($leave);

            self::log($leave, LeaveAction::SUBMITTED, $by, null, $leave->reason);
            $stage = self::moveTo($leave, 0);

            Notifier::send(
                $leave->user,
                'Leave application submitted',
                sprintf(
                    'Your application for %s (%d working day(s) from %s) has been submitted and is with the %s.',
                    strtolower($leave->typeLabel()), $leave->days, $leave->start_date->format('d M Y'), Leave::STAGES[$stage]
                ),
                self::url($leave)
            );
            Audit::log('leave.submitted', "Applied for {$leave->typeLabel()} {$leave->reference} ({$leave->days} day(s))", $leave, $by);

            return $leave;
        });
    }

    /** Recommend (Head, Dean), verify (Human Resource) or approve (University Secretary). */
    public static function approve(Leave $leave, User $by, string $comment = ''): Leave
    {
        return DB::transaction(function () use ($leave, $by, $comment) {
            $leave = Leave::lockForUpdate()->findOrFail($leave->id);
            self::requireActor($by, $leave);
            $stage = $leave->stage;

            if (in_array($stage, [Leave::STAGE_HR, Leave::STAGE_US], true)) {
                self::checkBalanceStillOk($leave);
            }
            if ($stage === Leave::STAGE_HR) {
                $balance = LeaveRules::annualBalance($leave->user, $leave->leave_year, $leave);
                $leave->hr_days_due = $balance->daysDue;
                $leave->hr_carried_forward = $balance->carriedForward;
                $leave->hr_days_taken = $balance->taken;
                $leave->hr_balance = $balance->balance();
                $leave->save();
            }
            $verb = self::STAGE_VERB[$stage];
            self::log($leave, $verb, $by, $stage, $comment);
            Audit::log('leave.' . $verb, LeaveAction::LABELS[$verb] . " {$leave->reference} for {$leave->user->name}", $leave, $by);

            $route = $leave->routeList();
            $index = array_search($stage, $route, true);

            if ($index === count($route) - 1) {
                $leave->status = Leave::APPROVED;
                $leave->stage = null;
                $leave->decided_at = now();
                $leave->save();
                self::syncAttendance($leave);
                self::announceApproval($leave, $comment);
            } else {
                $next = self::moveTo($leave, $index + 1);
                Notifier::send(
                    $leave->user,
                    'Leave request moved to the ' . Leave::STAGES[$next],
                    sprintf(
                        'Your leave request %s was %s by the %s (%s) and is now with the %s.%s',
                        $leave->reference,
                        strtolower(LeaveAction::LABELS[$verb]),
                        Leave::STAGES[$stage],
                        $by->name,
                        Leave::STAGES[$next],
                        $comment ? " Comment: {$comment}" : ''
                    ),
                    self::url($leave)
                );
            }

            return $leave->fresh();
        });
    }

    public static function reject(Leave $leave, User $by, string $comment): Leave
    {
        return DB::transaction(function () use ($leave, $by, $comment) {
            $leave = Leave::lockForUpdate()->findOrFail($leave->id);
            self::requireActor($by, $leave);
            if (trim($comment) === '') {
                throw new LeaveActionRefused('Give a reason for not approving the request.');
            }
            $stage = $leave->stage;
            self::log($leave, LeaveAction::REJECTED, $by, $stage, $comment);
            $leave->status = Leave::REJECTED;
            $leave->stage = null;
            $leave->decided_at = now();
            $leave->save();

            Notifier::send(
                $leave->user,
                'Leave request not approved',
                sprintf(
                    'Your leave request %s (%s – %s) was not approved by the %s. Reason: %s',
                    $leave->reference, $leave->start_date->format('d M'), $leave->end_date->format('d M Y'),
                    Leave::STAGES[$stage], $comment
                ),
                self::url($leave)
            );
            Audit::log('leave.rejected', "Did not approve {$leave->reference} for {$leave->user->name}: {$comment}", $leave, $by);

            return $leave->fresh();
        });
    }

    public static function withdraw(Leave $leave, User $by, string $comment = ''): Leave
    {
        return DB::transaction(function () use ($leave, $by, $comment) {
            $leave = Leave::lockForUpdate()->findOrFail($leave->id);
            if ($leave->status !== Leave::PENDING || (int) $leave->user_id !== (int) $by->id) {
                throw LeaveActionRefused::forbidden('Only the applicant can withdraw a request that is still awaiting approval.');
            }
            $stage = $leave->stage;
            $waiting = $stage ? self::stageApprovers($leave, $stage) : new Collection();
            self::log($leave, LeaveAction::WITHDRAWN, $by, $stage, $comment);
            $leave->status = Leave::WITHDRAWN;
            $leave->stage = null;
            $leave->decided_at = now();
            $leave->save();

            Notifier::send(
                $waiting,
                "Leave request withdrawn – {$leave->user->name}",
                "{$leave->user->name} withdrew leave request {$leave->reference}. No action is needed.",
                self::url($leave),
                false
            );
            Audit::log('leave.withdrawn', "Withdrew {$leave->reference}", $leave, $by);

            return $leave->fresh();
        });
    }

    /** Cancel approved leave that has not started; every day returns to the balance. */
    public static function cancel(Leave $leave, User $by, string $reason): Leave
    {
        return DB::transaction(function () use ($leave, $by, $reason) {
            $leave = Leave::lockForUpdate()->findOrFail($leave->id);
            if (!AccessPolicy::allows($by, 'leave.manage')) {
                throw LeaveActionRefused::forbidden('Only Human Resource can cancel approved leave.');
            }
            if ($leave->status !== Leave::APPROVED) {
                throw new LeaveActionRefused('Only approved leave can be cancelled.');
            }
            if ($leave->hasStarted()) {
                throw new LeaveActionRefused('This leave has already started – recall the employee instead.');
            }
            if (trim($reason) === '') {
                throw new LeaveActionRefused('Give a reason for the cancellation.');
            }
            self::log($leave, LeaveAction::CANCELLED, $by, null, $reason);
            $leave->status = Leave::CANCELLED;
            $leave->days_restored = $leave->days;
            $leave->save();

            Notifier::send(
                $leave->user,
                'Leave cancelled',
                sprintf(
                    'Your approved %s from %s has been cancelled by %s. Reason: %s. All %d day(s) are back in your balance.',
                    strtolower($leave->typeLabel()), $leave->start_date->format('d M Y'), $by->name, $reason, $leave->days
                ),
                self::url($leave)
            );
            Audit::log('leave.cancelled', "Cancelled {$leave->reference} for {$leave->user->name}: {$reason}", $leave, $by);

            return $leave->fresh();
        });
    }

    /**
     * The University recalls the employee: they resume duty on $resume, and the
     * unused working days from that date go back to their balance.
     */
    public static function recall(Leave $leave, User $by, $resume, string $reason): Leave
    {
        return DB::transaction(function () use ($leave, $by, $resume, $reason) {
            $leave = Leave::lockForUpdate()->findOrFail($leave->id);
            if (!AccessPolicy::allows($by, 'leave.recall')) {
                throw LeaveActionRefused::forbidden('Only Human Resource or the University Secretary can recall staff from leave.');
            }
            if ($leave->status !== Leave::APPROVED) {
                throw new LeaveActionRefused('Only approved leave can be recalled.');
            }
            if (trim($reason) === '') {
                throw new LeaveActionRefused('Give the reason for the recall.');
            }
            $resume = Carbon::parse($resume)->startOfDay();
            if ($resume->lte($leave->start_date)) {
                throw new LeaveActionRefused('The leave has not started by that date – cancel it instead of recalling.');
            }
            if ($resume->gt($leave->end_date)) {
                throw new LeaveActionRefused('The leave already ends on ' . $leave->end_date->format('d M Y') . '.');
            }

            $restored = LeaveRules::workingDays($resume, $leave->end_date);
            $leave->status = Leave::RECALLED;
            $leave->recall_date = $resume;
            $leave->recall_reason = $reason;
            $leave->recalled_by = $by->id;
            $leave->recalled_at = now();
            $leave->days_restored = $restored;
            $leave->save();

            self::log($leave, LeaveAction::RECALLED, $by, null, sprintf(
                'Resume duty on %s. %d working day(s) restored. Reason: %s', $resume->format('l j F Y'), $restored, $reason
            ));
            self::syncAttendance($leave, $resume, $leave->end_date);

            Notifier::send(
                $leave->user,
                'Recalled from leave – please resume duty',
                sprintf(
                    'The University has recalled you from %s. Please resume duty on %s. Reason: %s. %d unused working day(s) have been restored to your balance.',
                    strtolower($leave->typeLabel()), $resume->format('l j F Y'), $reason, $restored
                ),
                self::url($leave)
            );
            Notifier::send(
                self::stageApprovers($leave, Leave::STAGE_HOD),
                "{$leave->user->name} recalled from leave",
                "{$leave->user->name} resumes duty on {$resume->format('d M Y')}.",
                self::url($leave),
                false
            );
            Audit::log('leave.recalled', "Recalled {$leave->user->name} from {$leave->reference}; resumes {$resume->toDateString()}", $leave, $by);

            return $leave->fresh();
        });
    }

    /** Leave entered directly by Human Resource (approved on paper, or official duty). */
    public static function recordByHr(Leave $leave, User $by): Leave
    {
        return DB::transaction(function () use ($leave, $by) {
            if (!AccessPolicy::allows($by, 'leave.manage')) {
                throw LeaveActionRefused::forbidden('Only Human Resource can record leave.');
            }
            $leave->source = Leave::SOURCE_HR;
            $leave->status = Leave::APPROVED;
            $leave->stage = null;
            $leave->route = null;
            $leave->recorded_by = $by->id;
            $leave->submitted_at = $leave->submitted_at ?: now();
            $leave->decided_at = now();
            $leave->save();
            self::assignReference($leave);

            self::log($leave, LeaveAction::RECORDED, $by, null, $leave->reason);
            self::syncAttendance($leave);

            Notifier::send(
                $leave->user,
                'Leave recorded by Human Resource',
                sprintf(
                    '%s from %s to %s (%d working day(s)) has been recorded for you.',
                    $leave->typeLabel(), $leave->start_date->format('d M'), $leave->end_date->format('d M Y'), $leave->days
                ),
                self::url($leave)
            );
            Audit::log('leave.recorded', "Recorded {$leave->typeLabel()} {$leave->reference} for {$leave->user->name}", $leave, $by);

            return $leave->fresh();
        });
    }

    /**
     * Bring a request made before this workflow existed into it, waiting at
     * route[$index] (earlier stages count as already done). Used once, by
     * the leave:normalise-legacy command.
     */
    public static function adoptLegacy(Leave $leave, int $index): Leave
    {
        return DB::transaction(function () use ($leave, $index) {
            $leave->route = implode(',', self::buildRoute($leave->user));
            $leave->source = Leave::SOURCE_APPLICATION;
            $leave->submitted_at = $leave->submitted_at ?: $leave->created_at;
            $leave->save();
            self::assignReference($leave);
            self::log($leave, LeaveAction::SUBMITTED, $leave->user, null,
                trim($leave->reason . ' (Request made before the online approval process; brought into it.)'));
            $index = min($index, count($leave->routeList()) - 1);
            self::moveTo($leave, $index);
            // The trail is dated from the original request, not from when it was brought in.
            $leave->actions()->update(['created_at' => $leave->submitted_at]);

            return $leave->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Progress for the request page
    |--------------------------------------------------------------------------
    */

    /**
     * One step per stage of the route, each "done", "current", "waiting",
     * "skipped", "rejected", "withdrawn" or "unused".
     *
     * @return array<int, array{key:string, label:string, state:string, action:?LeaveAction, approvers:\Illuminate\Support\Collection}>
     */
    public static function timeline(Leave $leave): array
    {
        $actions = $leave->actions()->get();
        $steps = [[
            'key' => 'applicant',
            'label' => 'Applicant',
            'state' => 'done',
            'action' => $actions->firstWhere('action', LeaveAction::SUBMITTED) ?: $actions->firstWhere('action', LeaveAction::RECORDED),
            'approvers' => collect(),
        ]];

        $finished = false;
        foreach ($leave->routeList() as $stage) {
            $act = $actions->filter(fn ($a) => $a->stage === $stage && $a->action !== LeaveAction::SUBMITTED)->last();
            $state = 'unused';
            if ($act && $act->action === LeaveAction::REJECTED) {
                $state = 'rejected';
                $finished = true;
            } elseif ($act && $act->action === LeaveAction::SKIPPED) {
                $state = 'skipped';
            } elseif ($act && in_array($act->action, [LeaveAction::RECOMMENDED, LeaveAction::VERIFIED, LeaveAction::APPROVED], true)) {
                $state = 'done';
            } elseif ($act && $act->action === LeaveAction::WITHDRAWN) {
                $state = 'withdrawn';
                $finished = true;
            } elseif ($leave->status === Leave::PENDING && $leave->stage === $stage) {
                $state = 'current';
            } elseif (!$finished && $leave->status === Leave::PENDING) {
                $state = 'waiting';
            }
            $steps[] = [
                'key' => $stage,
                'label' => Leave::STAGES[$stage],
                'state' => $state,
                'action' => $act,
                'approvers' => $state === 'current' ? self::stageApprovers($leave, $stage) : collect(),
            ];
        }

        return $steps;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Put the request at route[$index], passing vacant Head / Dean stages. */
    private static function moveTo(Leave $leave, int $index): string
    {
        $route = $leave->routeList();
        while ($index < count($route)) {
            $stage = $route[$index];
            $approvers = self::stageApprovers($leave, $stage);
            if ($approvers->isNotEmpty() || !in_array($stage, self::SKIPPABLE, true)) {
                $leave->stage = $stage;
                $leave->save();
                $name = $leave->user->name;
                Notifier::send(
                    $approvers,
                    "Leave request awaiting your action – {$name}",
                    sprintf(
                        '%s (%s) has applied for %s: %d working day(s), %s – %s. It is now with you as %s.',
                        $name, optional($leave->user->department)->name ?: 'no department', strtolower($leave->typeLabel()),
                        $leave->days, $leave->start_date->format('d M'), $leave->end_date->format('d M Y'), Leave::STAGES[$stage]
                    ),
                    self::url($leave)
                );
                if ($approvers->isEmpty()) {
                    Notifier::send(
                        User::where('status', 'Active')->where('is_demo', (bool) optional($leave->user)->is_demo)->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get(),
                        'No ' . Leave::STAGES[$stage] . ' account to act on leave',
                        "Leave request {$leave->reference} is waiting at the " . Leave::STAGES[$stage]
                            . ' stage, but no active user holds that role. Give someone the role under System users.',
                        self::url($leave)
                    );
                }

                return $stage;
            }
            self::log($leave, LeaveAction::SKIPPED, null, $stage,
                'No active ' . Leave::STAGES[$stage] . ' account – passed to the next stage automatically.');
            $index++;
        }

        throw new LeaveActionRefused('The approval route has no remaining stages.');
    }

    private static function requireActor(User $user, Leave $leave): void
    {
        if (!self::canAct($user, $leave)) {
            throw LeaveActionRefused::forbidden('This request is not waiting for you.');
        }
    }

    private static function checkBalanceStillOk(Leave $leave): void
    {
        if (!$leave->isAnnual()) {
            return;
        }
        $balance = LeaveRules::annualBalance($leave->user, $leave->leave_year, $leave);
        if (!$balance->hasAllocation || $leave->days > $balance->available()) {
            throw new LeaveActionRefused(sprintf(
                'Only %d annual leave day(s) are available for %s; this request is for %d. It cannot go forward – decline it or ask Human Resource to review the allocation.',
                max($balance->available(), 0), $balance->label(), $leave->days
            ));
        }
    }

    private static function announceApproval(Leave $leave, string $comment): void
    {
        Notifier::send(
            $leave->user,
            'Leave approved',
            sprintf(
                'Your %s from %s to %s has been approved by the University Secretary. Please report back on %s.%s',
                strtolower($leave->typeLabel()), $leave->start_date->format('d M'), $leave->end_date->format('d M Y'),
                optional($leave->return_date)->format('l j F Y'), $comment ? " Comment: {$comment}" : ''
            ),
            self::url($leave)
        );
        Notifier::send(
            self::stageApprovers($leave, Leave::STAGE_HOD),
            "Leave approved – {$leave->user->name}",
            "{$leave->user->name} will be on " . strtolower($leave->typeLabel()) . " from {$leave->start_date->format('d M')} to {$leave->end_date->format('d M Y')}.",
            self::url($leave),
            false
        );
        if ($leave->acting_user_id && $leave->actingUser) {
            Notifier::send(
                $leave->actingUser,
                "You will take charge while {$leave->user->name} is on leave",
                "You were named to take charge from {$leave->start_date->format('d M')} to {$leave->end_date->format('d M Y')}.",
                self::url($leave)
            );
        }
    }

    /** Attendance for the leave's days that have already happened is rebuilt. */
    private static function syncAttendance(Leave $leave, $from = null, $to = null): void
    {
        $from = Carbon::parse($from ?: $leave->start_date);
        $to = Carbon::parse($to ?: $leave->end_date)->min(today());
        if ($from->lte($to)) {
            app(AttendanceEngine::class)->processRange($from, $to, [$leave->user_id]);
        }
    }

    private static function log(Leave $leave, string $action, ?User $actor, ?string $stage, ?string $comment): LeaveAction
    {
        $title = null;
        if ($actor) {
            $title = $actor->roleLabel();
            if ($actor->position) {
                // "Lecturer (Head of Department)", but not "Human Resource Manager (Human Resource)".
                $title = stripos($actor->position, $actor->roleLabel()) !== false
                    ? $actor->position
                    : "{$actor->position} ({$actor->roleLabel()})";
            }
        }

        return LeaveAction::create([
            'leave_id' => $leave->id,
            'stage' => $stage,
            'action' => $action,
            'actor_id' => optional($actor)->id,
            'actor_name' => $actor ? $actor->displayName() : 'System',
            'actor_title' => $title,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    private static function assignReference(Leave $leave): void
    {
        if (!$leave->reference) {
            $leave->reference = sprintf('LV-%s-%05d', now()->format('Y'), $leave->id);
            $leave->save();
        }
    }

    public static function url(Leave $leave): string
    {
        return admin_url('leave/' . $leave->id);
    }
}
