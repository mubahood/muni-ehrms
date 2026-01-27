<?php

namespace App\Admin\Controllers;

use App\Models\Department;
use App\Models\User;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class DepartmentController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Departments';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid(): Grid
    {
        $grid = new Grid(new Department());

        $grid->column('id', __('ID'))->sortable();
        $grid->column('name', __('Name'))->sortable();
        $grid->column('description', __('Description'))->limit(50);
        $grid->column('users_count', __('Total Users'))->display(function() {
            return $this->users()->count();
        })->label('info');
        $grid->column('creator.name', __('Created By'));
        $grid->column('is_active', __('Status'))->display(function ($value) {
            return $value ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>';
        });
        $grid->column('created_at', __('Created'))->display(function ($value) {
            return \Carbon\Carbon::parse($value)->format('d M, Y');
        });

        // Filters
        $grid->filter(function($filter) {
            $filter->disableIdFilter();
            
            $filter->like('name', __('Name'));
            $filter->equal('is_active', __('Status'))->radio([
                '' => 'All',
                1 => 'Active',
                0 => 'Inactive'
            ]);
            $filter->between('created_at', __('Created At'))->datetime();
        });

        // Actions
        $grid->actions(function ($actions) {
            $actions->disableView();
        });

        $grid->batchActions(function ($batch) {
            $batch->disableDelete();
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id): Show
    {
        $show = new Show(Department::findOrFail($id));

        $show->field('id', __('ID'));
        $show->field('name', __('Name'));
        $show->field('description', __('Description'));
        $show->field('creator.name', __('Created By'));
        $show->field('is_active', __('Status'))->as(function ($value) {
            return $value ? 'Active' : 'Inactive';
        });
        $show->field('created_at', __('Created'));
        $show->field('updated_at', __('Last Updated'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form(): Form
    {
        $form = new Form(new Department());

        $form->text('name', __('Department Name'))
            ->rules('required|string|max:255|unique:departments,name,' . request()->route()->parameter('department'))
            ->help('Enter the department name');
            
        $form->textarea('description', __('Description'))
            ->rows(4)
            ->help('Brief description of the department');

        $form->switch('is_active', __('Active'))
            ->default(1)
            ->help('Activate or deactivate this department');

        // Set created_by automatically
        $form->saving(function (Form $form) {
            if ($form->isCreating()) {
                $form->created_by = auth()->id();
            }
        });

        return $form;
    }
}
