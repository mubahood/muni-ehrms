<?php

namespace App\Admin\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class AttendanceRecordController extends AdminController
{
    protected $title = 'Attendance Records';

    protected function grid()
    {
        $grid = new Grid(new AttendanceRecord());

        $grid->model()->orderBy('updated_at', 'desc');
        $grid->column('id', __('Id'))->sortable()->sortable();

        $grid->filter(function ($filter) {
            $filter->equal('user_id', __('User'))->select(
                User::all()->pluck('name', 'id')
            );
            $filter->between('attendance_date', __('Attendance date'))->date();
            $filter->equal('day', __('Day'))->select([
                'Monday' => 'Monday',
                'Tuesday' => 'Tuesday',
                'Wednesday' => 'Wednesday',
                'Thursday' => 'Thursday',
                'Friday' => 'Friday',
                'Saturday' => 'Saturday',
                'Sunday' => 'Sunday',
            ]);
            $filter->equal('status', __('Status'))->select([
                'Present' => 'Present',
                'Absent' => 'Absent',
                'Missing' => 'Missing',
            ]);
            $filter->equal('is_imported', __('Is imported'))->select([
                'Yes' => 'Yes',
                'No' => 'No',
            ]);
        });



        $grid->column('user.name', __('Employee'))->sortable();

        $grid->column('attendance_date', __('Attendance date'))
            ->display(fn($date) => Utils::my_date($date))
            ->sortable();

        $grid->column('status', __('Status'))
            ->dot([
                'Present' => 'success',
                'Absent' => 'danger',
                'Missing' => 'warning',
            ])
            ->sortable();

        $grid->column('check_in_time', __('Check in'))
            ->display(function ($checkInTime) {
                if ($checkInTime == null || strlen($checkInTime) < 5) {
                    return '--:--';
                }
                return $checkInTime;
            })->sortable();
        $grid->column('check_out_time', __('Check out'))->sortable()
            ->display(function ($checkOutTime) {
                if ($checkOutTime == null || strlen($checkOutTime) < 5) {
                    return '--:--';
                }
                return $checkOutTime;
            });

        $grid->column('hours', __('Hours'))
            ->display(fn($hours) => $hours ?: '0')
            ->sortable();

        $grid->column('day', __('Day'))
            ->label([
                'Monday' => 'primary',
                'Tuesday' => 'primary',
                'Wednesday' => 'primary',
                'Thursday' => 'primary',
                'Friday' => 'primary',
                'Saturday' => 'warning',
                'Sunday' => 'warning',
            ])
            ->sortable();

        $grid->column('is_imported', __('Is imported'))
            ->editable('select', ['Yes' => 'Yes', 'No' => 'No'])
            ->label([
                'Yes' => 'success',
                'No' => 'default',
            ])
            ->sortable()
            ->hide();

        $grid->column('is_late', __('Is Late'))
            ->using(['No' => 'No', 'Yes' => 'Yes'])
            ->label([
                'No' => 'success',
                'Yes' => 'danger',
            ])
            ->sortable();

        $grid->column('notes', __('Notes'))->hide();
        $grid->column('has_error', __('Has error'))->hide();
        $grid->column('error_message', __('Error message'))->hide();
        $grid->column('import_record_id', __('Import record id'))->hide();

        $grid->column('updated_at', __('Updated'))
            ->display(fn($updatedAt) => Utils::my_date($updatedAt))
            ->sortable()
            ->hide();

        $grid->column('created_at', __('Created'))
            ->display(fn($createdAt) => Utils::my_date($createdAt))
            ->sortable()
            ->hide();

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(AttendanceRecord::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('user.name', __('User'));
        $show->field('attendance_date', __('Attendance date'));
        $show->field('check_in_time', __('Check in time'));
        $show->field('check_out_time', __('Check out time'));
        $show->field('status', __('Status'));
        $show->field('notes', __('Notes'));
        $show->field('day', __('Day'));
        $show->field('is_imported', __('Is imported'));
        $show->field('has_error', __('Has error'));
        $show->field('error_message', __('Error message'));
        $show->field('import_record_id', __('Import record id'));
        $show->field('is_late', __('Is late'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    protected function form()
    {
        $form = new Form(new AttendanceRecord());

        $form->select('user_id', __('User'))->options(
            User::all()->pluck('name', 'id')
        )->required();

        $form->date('attendance_date', __('Attendance date'))->default(date('Y-m-d'))->required();
        $form->time('check_in_time', __('Check in time'))->default(date('H:i'));
        $form->time('check_out_time', __('Check out time'))->default(date('H:i'));

        $form->select('status', __('Status'))->options([
            'Present' => 'Present',
            'Absent' => 'Absent',
            'Missing' => 'Missing',
        ])->default('Missing')->required();

        $form->textarea('notes', __('Notes'));
        $form->select('day', __('Day'))->options([
            'Monday' => 'Monday',
            'Tuesday' => 'Tuesday',
            'Wednesday' => 'Wednesday',
            'Thursday' => 'Thursday',
            'Friday' => 'Friday',
            'Saturday' => 'Saturday',
            'Sunday' => 'Sunday',
        ])->required();

        $form->select('is_imported', __('Is imported'))->options([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->default('No');

        $form->select('has_error', __('Has error'))->options([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->default('No');

        $form->textarea('error_message', __('Error message'));
        $form->number('import_record_id', __('Import record id'));
        $form->select('is_late', __('Is late'))->options([
            'Yes' => 'Yes',
            'No' => 'No',
        ])->default('No');

        return $form;
    }
}
