<?php

namespace App\Admin\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\Audit;
use App\Services\AttendanceEngine;
use App\Services\Scope;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\DB;

/**
 * Daily attendance records. Everyone sees the people in their scope (an
 * employee, only themselves). Human Resource and the System Administrator can
 * correct a day, giving a reason; a corrected day is never rebuilt
 * automatically until it is returned to automatic calculation.
 */
class AttendanceRecordController extends AdminController
{
    protected $title = 'Attendance records';

    protected $description = [
        'index' => 'One record per person per working day, built from the terminal captures',
        'show' => 'Details',
        'edit' => 'Edit',
        'create' => 'New',
    ];

    private const STATUS_FILTER = [
        'present' => 'On time',
        'late' => 'Late',
        'absent' => 'Absent',
        'leave' => 'On leave',
        'half' => 'Half day (not seen leaving)',
    ];

    protected function grid()
    {
        $viewer = Admin::user();
        $grid = new Grid(new AttendanceRecord());
        $grid->model()->with(['user.department']);
        Scope::apply($grid->model(), $viewer, 'user_id');
        $grid->model()->orderBy('attendance_date', 'desc')->orderBy('user_id');

        $grid->header(fn () => view('ehrms.partials.record-chips', [
            'statuses' => self::STATUS_FILTER,
            'lastDay' => \App\Services\AttendanceStats::referenceDay(Scope::userIds($viewer)),
        ])->render());

        $canCorrect = AccessPolicy::allows($viewer, 'attendance.correct');
        $grid->disableCreateButton();
        $grid->disableBatchActions();
        $grid->actions(function (Grid\Displayers\Actions $actions) use ($canCorrect) {
            $actions->disableDelete();
            if (!$canCorrect) {
                $actions->disableEdit();
            }
        });

        $grid->filter(function (Grid\Filter $filter) use ($viewer) {
            $filter->disableIdFilter();
            $filter->column(1 / 2, function ($filter) use ($viewer) {
                $people = Scope::apply(User::query(), $viewer)->where('status', 'Active')->orderBy('name')->pluck('name', 'id');
                $filter->equal('user_id', 'Employee')->select($people);
                $filter->where(function ($query) {
                    $query->whereIn('user_id', User::where('department_id', $this->input)->pluck('id'));
                }, 'Department', 'department')->select(Scope::departments($viewer)->pluck('name', 'id'));
            });
            $filter->column(1 / 2, function ($filter) {
                $filter->between('attendance_date', 'Date')->date();
                $filter->where(function ($query) {
                    switch ($this->input) {
                        case 'present':
                            $query->where('status', 'Present')->where('is_late', 'No');
                            break;
                        case 'late':
                            $query->where('status', 'Present')->where('is_late', 'Yes');
                            break;
                        case 'absent':
                            $query->where('status', 'Absent');
                            break;
                        case 'leave':
                            $query->where('status', 'On Leave');
                            break;
                        case 'half':
                            $query->where('is_half_day', true);
                            break;
                    }
                }, 'Status', 'status_key')->select(self::STATUS_FILTER);
                $filter->equal('is_manual', 'Corrected by HR')->select([1 => 'Corrected', 0 => 'Automatic']);
            });
        });

        $grid->column('attendance_date', 'Date')->display(function ($date) {
            return Carbon::parse($date)->format('D d M Y');
        })->sortable();
        $grid->column('user.name', 'Employee')->display(function ($name) {
            $dept = optional(optional($this->user)->department)->name;

            return e($name) . ($dept ? '<div class="cell-sub">' . e($dept) . '</div>' : '');
        });
        $grid->column('status', 'Status')->display(function () {
            $chip = '<span class="st st-' . $this->statusKey() . '">' . e($this->statusLabel()) . '</span>';
            if ($this->holiday_name) {
                $chip .= ' <span class="cell-sub">' . e($this->holiday_name) . '</span>';
            } elseif (!$this->is_working_day) {
                $chip .= ' <span class="cell-sub">Day off</span>';
            }

            return $chip;
        });
        $grid->column('check_in_time', 'Arrived')->display(fn ($t) => $t ? substr($t, 0, 5) : '—');
        $grid->column('check_out_time', 'Left')->display(function ($t) {
            if ($t) {
                return substr($t, 0, 5);
            }
            if ($this->status !== 'Present') {
                return '—';
            }

            return Carbon::parse($this->attendance_date)->isToday()
                ? '<span class="cell-sub">In progress</span>'
                : '<span class="cell-sub">Not seen leaving</span>';
        });
        $grid->column('hours', 'Hours')->display(function ($hours) {
            if ($this->status !== 'Present') {
                return '—';
            }
            $text = AttendanceRecordController::hm((float) $hours);

            return $this->is_half_day ? $text . ' <span class="cell-sub">half day</span>' : $text;
        });
        $grid->column('late_minutes', 'Late by')->display(fn ($m) => $m ? AttendanceRecordController::hm($m / 60) : '—');
        $grid->column('is_manual', 'Source')->display(function ($manual) {
            if ($manual) {
                return '<span class="st st-corrected" title="' . e($this->correction_reason) . '">Corrected</span>';
            }

            return $this->source === 'import' || $this->is_imported === 'Yes' ? 'Imported' : 'Terminal';
        });

        return $grid;
    }

    protected function detail($id)
    {
        $record = AttendanceRecord::with('user.department', 'correctedBy')->findOrFail($id);
        abort_unless(Scope::canSee(Admin::user(), (int) $record->user_id), 403);

        $show = new Show($record);
        $show->panel()->title($record->user->name . ' · ' . Carbon::parse($record->attendance_date)->format('l j F Y'));
        $show->field('status', 'Status')->as(fn () => $record->statusLabel());
        $show->field('check_in_time', 'Arrived')->as(fn ($t) => $t ? substr($t, 0, 5) : '—');
        $show->field('check_out_time', 'Left')->as(fn ($t) => $t ? substr($t, 0, 5) : '—');
        $show->field('hours', 'Time on site')->as(fn ($h) => $record->status === 'Present' ? AttendanceRecordController::hm((float) $h) . ($record->is_half_day ? ' (half day credited)' : '') : '—');
        $show->field('late_minutes', 'Late by')->as(fn ($m) => $m ? AttendanceRecordController::hm($m / 60) : 'On time');
        $show->field('punch_count', 'Captures by the terminal');
        $show->field('captures', 'Capture times')->as(function () use ($record) {
            $times = AttendanceEngine::whereClockIn(
                DB::table('event_logs')->where('user_id', $record->user_id)
                    ->whereBetween('event_time', [$record->attendance_date . ' 00:00:00', $record->attendance_date . ' 23:59:59'])
            )->orderBy('event_time')->pluck('event_time');

            return $times->isEmpty() ? 'None' : $times->map(fn ($t) => substr($t, 11, 5))->implode(' · ');
        });
        if ($record->is_manual) {
            $show->field('correction_reason', 'Correction reason');
            $show->field('corrected_by', 'Corrected by')->as(fn () => optional($record->correctedBy)->name . ', ' . optional($record->corrected_at)->format('d M Y H:i'));
        }
        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
            if (!AccessPolicy::allows(Admin::user(), 'attendance.correct')) {
                $tools->disableEdit();
            }
        });

        return $show;
    }

    /**
     * The correction form. Only the outcome of the day can be changed, and a
     * reason is required.
     */
    protected function form()
    {
        $form = new Form(new AttendanceRecord());
        $form->display('user.name', 'Employee');
        $form->display('attendance_date', 'Date');

        $form->radio('status', 'Status')->options([
            'Present' => 'Present',
            'Absent' => 'Absent',
            'On Leave' => 'On leave',
        ])->rules('required');
        $form->time('check_in_time', 'Arrived')->format('HH:mm:ss')->help('Leave empty if absent or on leave.');
        $form->time('check_out_time', 'Left')->format('HH:mm:ss');
        $form->textarea('correction_reason', 'Reason for the correction')
            ->rows(2)->rules('required|min:5')
            ->help('Kept with the record and in the audit log, e.g. "Terminal offline on Monday; arrival confirmed by HoD".');
        $form->switch('restore_automatic', 'Return this day to automatic calculation')
            ->help('Discards the correction and rebuilds the day from the terminal captures.');
        $form->ignore(['restore_automatic']);

        $form->disableCreatingCheck();
        $form->disableViewCheck();
        $form->tools(fn ($tools) => $tools->disableDelete());

        $form->saving(function (Form $form) {
            abort_unless(Scope::canSee(Admin::user(), (int) $form->model()->user_id), 403);
            if (request('restore_automatic') === 'on') {
                return;
            }
            $in = $form->check_in_time ?: null;
            $out = $form->check_out_time ?: null;
            if ($form->status === 'Present' && !$in) {
                admin_toastr('A present day needs the arrival time.', 'error');

                return back()->withInput();
            }
            if ($in && $out && $out <= $in) {
                admin_toastr('The departure must be after the arrival.', 'error');

                return back()->withInput();
            }
            if ($form->status !== 'Present') {
                $form->check_in_time = null;
                $form->check_out_time = null;
            }
            $record = $form->model();
            $record->is_manual = true;
            $record->corrected_by = Admin::user()->id;
            $record->corrected_at = now();
            $record->source = 'manual';
            $record->is_late = 'No';
            $record->late_minutes = 0;
            $record->hours = 0;
            $record->is_half_day = false;
            if ($form->status === 'Present') {
                $engine = app(AttendanceEngine::class);
                $date = Carbon::parse($record->attendance_date);
                $arrival = Carbon::parse($record->attendance_date . ' ' . $in);
                $late = $record->is_working_day ? $engine->minutesLate($arrival, $record->user->lateTime()) : 0;
                $record->late_minutes = $late;
                $record->is_late = $late > 0 ? 'Yes' : 'No';
                $record->hours = $out ? round(Carbon::parse($record->attendance_date . ' ' . $out)->diffInSeconds($arrival) / 3600, 2) : 0;
            }
        });

        $form->saved(function (Form $form) {
            $record = $form->model()->fresh();
            if (request('restore_automatic') === 'on') {
                $record->forceFill(['is_manual' => false, 'corrected_by' => null, 'corrected_at' => null, 'correction_reason' => null, 'source' => 'device'])->save();
                app(AttendanceEngine::class)->processDay($record->attendance_date, [$record->user_id]);
                Audit::log('attendance.restored', "Returned {$record->user->name}'s {$record->attendance_date} to automatic calculation", $record);
                admin_toastr('The day was rebuilt from the terminal captures.');
            } else {
                Audit::log('attendance.corrected', "Corrected {$record->user->name}'s {$record->attendance_date} to {$record->statusLabel()}: {$record->correction_reason}", $record);
                admin_toastr('Correction saved. This day will not be recalculated automatically.');
            }

            return redirect(admin_url('attendance-records/' . $record->id));
        });

        return $form;
    }

    /** 7.25 → "7h 15m"; 0.5 → "30m". */
    public static function hm(float $hours): string
    {
        $minutes = (int) round($hours * 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
    }
}
