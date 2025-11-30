<?php

namespace App\Admin\Controllers;

use App\Models\ImportAttendanceRecord;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ImportAttendanceRecordController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'ImportAttendanceRecord';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {

        $grid = new Grid(new ImportAttendanceRecord());
        $grid->model()->orderBy('created_at', 'desc'); 

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('file_path', __('File path'))
            ->downloadable()
            ->sortable()
            ->help('Upload an Excel or CSV file containing attendance records.');
        //do import
        $grid->column('actions_1', __('Import Data'))->display(function () {
            $url = url('do-import-attendance-records?id=' . $this->id);
            return "<a href='{$url}' class='btn btn-xs btn-primary' target='_blank'>Import</a>";
        });
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(ImportAttendanceRecord::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('file_path', __('File path'));
        $show->field('status', __('Status'));
        $show->field('is_imported', __('Is imported'));
        $show->field('has_error', __('Has error'));
        $show->field('error_message', __('Error message'));
        $show->field('user_id', __('User id'));
        $show->field('due_date', __('Due date'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ImportAttendanceRecord());

        $form->file('file_path', __('File path'))->uniqueName()->rules('required|file|mimes:xlsx,xls,csv')->help('Upload an Excel or CSV file containing attendance records.');
        return $form;
        return $form;
        $form->text('status', __('Status'))->default('Pending');
        $form->text('is_imported', __('Is imported'))->default('No');
        $form->text('has_error', __('Has error'))->default('No');
        $form->textarea('error_message', __('Error message'));
        $form->number('user_id', __('User id'));

        return $form;
    }
}
