<?php

declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Models\GeneralReport;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class GeneralReportController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'GeneralReport';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(): Grid
    {
        $grid = new Grid(new GeneralReport());
        $grid->model()->orderBy('id', 'desc');
        
        // Add filters
        $grid->filter(function($filter){
            $filter->disableIdFilter();
            
            $filter->between('start_date', __('Start Date'))->date();
            $filter->between('end_date', __('End Date'))->date();

            $filter->equal('report_type', __('Report Type'))->select([
                'general' => 'General',
                'department' => 'Department',
                'user' => 'User'
            ]);
            
            $filter->equal('is_generated', __('Status'))->select([
                'Yes' => 'Generated',
                'No' => 'Pending'
            ]);
            
            $filter->between('created_at', __('Created'))->datetime();
        });

        $grid->column('id', __('Id'))->sortable();
        
        $grid->column('start_date', __('Start Date'))->display(function ($date) {
            return $date ? \Carbon\Carbon::parse($date)->format('d M, Y') : '-';
        })->sortable();
        
        $grid->column('end_date', __('End Date'))->display(function ($date) {
            return $date ? \Carbon\Carbon::parse($date)->format('d M, Y') : '-';
        })->sortable();
        
        $grid->column('date_range', __('Period'))->display(function () {
            if ($this->start_date && $this->end_date) {
                $start = \Carbon\Carbon::parse($this->start_date)->format('d M');
                $end = \Carbon\Carbon::parse($this->end_date)->format('d M, Y');
                return "$start - $end";
            }
            return '-';
        });

        $grid->column('report_type', __('Type'))->display(function ($type) {
            $labels = [
                'general' => "<span class='label label-primary'>General</span>",
                'department' => "<span class='label label-info'>Department</span>",
                'user' => "<span class='label label-warning'>User</span>",
            ];
            return $labels[$type] ?? "<span class='label label-default'>Unknown</span>";
        });

        $grid->column('target', __('Target'))->display(function () {
            if ($this->report_type === 'user' && $this->targetUser) {
                return $this->targetUser->name;
            } elseif ($this->report_type === 'department' && $this->targetDepartment) {
                return $this->targetDepartment->name;
            }
            return '-';
        });
        
        $grid->column('is_generated', __('Status'))->display(function ($value) {
            if ($value === 'Yes') {
                return "<span class='label label-success'>Generated</span>";
            }
            return "<span class='label label-warning'>Pending</span>";
        });
        
        $grid->column('created_at', __('Created'))->display(function ($date) {
            return \Carbon\Carbon::parse($date)->format('d M, Y H:i');
        })->sortable();
        
        $grid->column('file_path', __('File'))->display(function ($path) {
            if ($path && file_exists(public_path($path))) {
                $size = filesize(public_path($path));
                $units = ['B', 'KB', 'MB', 'GB'];
                for ($i = 0; $size > 1024; $i++) {
                    $size /= 1024;
                }
                return "<span class='label label-info'>" . round($size, 1) . " " . $units[$i] . "</span>";
            }
            return "<span class='label label-default'>No file</span>";
        });

        // Action buttons
        $grid->column('actions', __('Actions'))->display(function () {
            $buttons = '';
            
            // Generate/Regenerate button
            $generateUrl = url('print-general-reports?id=' . $this->id);
            if ($this->is_generated === 'Yes' && !empty($this->file_path)) {
                $buttons .= "<a target='_blank' href='{$generateUrl}' class='btn btn-xs btn-warning' style='margin-right: 5px;'>
                    <i class='fa fa-refresh'></i> Regenerate
                </a>";
            } else {
                $buttons .= "<a target='_blank' href='{$generateUrl}' class='btn btn-xs btn-primary' style='margin-right: 5px;'>
                    <i class='fa fa-cog'></i> Generate
                </a>";
            }
            
            // View PDF button (only if generated)
            if ($this->is_generated === 'Yes' && !empty($this->file_path) && file_exists(public_path($this->file_path))) {
                $viewUrl = url($this->file_path);
                $buttons .= "<a target='_blank' href='{$viewUrl}' class='btn btn-xs btn-success'>
                    <i class='fa fa-file-pdf-o'></i> View PDF
                </a>";
            }
            
            return $buttons;
        });
        
        // Disable created_at and updated_at from showing in list
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            $actions->disableView();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param int $id
     * @return Show
     */
    protected function detail($id): Show
    {
        $show = new Show(GeneralReport::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('start_date', __('Start date'));
        $show->field('end_date', __('End date'));
        $show->field('file_path', __('File path'));
        $show->field('is_generated', __('Is generated'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form(): Form
    {
        $form = new Form(new GeneralReport());

        $form->date('start_date', __('Start Date'))
            ->default(date('Y-m-d'))
            ->rules('required|date|before_or_equal:end_date')
            ->help('Select the start date for the report period');
            
        $form->date('end_date', __('End Date'))
            ->default(date('Y-m-d'))
            ->rules('required|date|after_or_equal:start_date')
            ->help('Select the end date for the report period');

        $form->radio('report_type', __('Report Type'))
            ->options([
                'general' => 'General (All Users)',
                'department' => 'Department-Specific',
                'user' => 'User-Specific'
            ])
            ->default('general')
            ->when('department', function (Form $form) {
                $form->select('target_department_id', __('Select Department'))
                    ->options(\App\Models\Department::active()->pluck('name', 'id'))
                    ->rules('required_if:report_type,department')
                    ->help('Select the department for this report');
            })
            ->when('user', function (Form $form) {
                $form->select('target_user_id', __('Select User'))
                    ->options(\App\Models\User::where('status', 'Active')->pluck('name', 'id'))
                    ->rules('required_if:report_type,user')
                    ->help('Select the user for this report');
            });
            
        $form->textarea('description', __('Description'))
            ->rows(3)
            ->placeholder('Optional: Add notes or description for this report');
            
        $form->hidden('is_generated', __('Is Generated'))->default('No');
        
        $form->display('created_at', __('Created At'));
        $form->display('updated_at', __('Updated At'));
        
        $form->saving(function (Form $form) {
            // Set current user as creator
            if ($form->isCreating()) {
                $form->user_id = auth()->id();
            }
            
            // Reset generation status when editing dates
            if ($form->isEditing() && ($form->start_date != $form->model()->start_date || $form->end_date != $form->model()->end_date)) {
                $form->is_generated = 'No';
                $form->file_path = null;
            }

            // Clear target fields based on report type
            if ($form->report_type === 'general') {
                $form->target_user_id = null;
                $form->target_department_id = null;
            } elseif ($form->report_type === 'user') {
                $form->target_department_id = null;
            } elseif ($form->report_type === 'department') {
                $form->target_user_id = null;
            }
        });

        return $form;
    }
}