<?php

namespace App\Admin\Controllers;

use App\Models\Leave;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class LeaveController extends AdminController
{
    protected $title = 'Leave';

    protected function grid()
    {
        $grid = new Grid(new Leave());

        $grid->column('id', 'ID');
        $grid->column('user.name', 'User');
        $grid->column('leave_type', 'Type');
        $grid->column('start_date', 'Start');
        $grid->column('end_date', 'End');
        $grid->column('reason', 'Reason')->limit(30);
        $grid->column('file_path', 'Attachment')->downloadable();
        $grid->column('created_at', 'Created');
        $grid->column('updated_at', 'Updated');

        return $grid;
    }

    protected function detail($id)
    {
        $show = new Show(Leave::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('user.name', 'User');
        $show->field('leave_type', 'Type');
        $show->field('start_date', 'Start');
        $show->field('end_date', 'End');
        $show->field('reason', 'Reason');
        $show->field('file_path', 'Attachment')->file();
        $show->field('created_at', 'Created');
        $show->field('updated_at', 'Updated');

        return $show;
    }

    protected function form()
    {
        $form = new Form(new Leave());

        $form->select('user_id', 'User')
            ->options(User::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
            ->rules('required');

        $form->date('start_date', 'Start Date')
            ->default(date('Y-m-d'))
            ->rules('required|date');

        $form->date('end_date', 'End Date')
            ->default(date('Y-m-d'))
            ->rules('required|date|after_or_equal:start_date');

        $form->radio('leave_type', 'Type')
            ->options([
                'sick' => 'Sick',
                'casual' => 'Casual',
                'annual' => 'Annual',
                'maternity' => 'Maternity',
                'paternity' => 'Paternity',
                'other' => 'Other'
            ])
            ->default('sick')
            ->rules('required');

        $form->textarea('reason', 'Reason')
            ->rules('required|max:255');

        $form->file('file_path', 'Attachment')
            ->uniqueName()
            ->help('Upload supporting documents (optional).');

        return $form;
    }
}
