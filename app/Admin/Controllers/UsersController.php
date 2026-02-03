<?php

namespace App\Admin\Controllers;

use App\Models\Department;
use App\Models\User;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class UsersController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Users';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new User());

        $grid->column('id', __('Id'));
        $grid->column('name', __('Name'));
        $grid->column('email', __('Email'));
        $grid->column('phone_number', __('Phone Number'));
        $grid->column('department.name', __('Department'));
        $grid->column('roles', __('Roles'))->display(function ($roles) {
            $roleNames = collect($roles)->pluck('name')->toArray();
            return implode(', ', $roleNames);
        });
        $grid->column('status', __('Status'))->display(function ($status) {
            return $status == 'Active' ? "<span class='label label-success'>Active</span>" : "<span class='label label-default'>Inactive</span>";
        });
        $grid->column('created_at', __('Created at'));

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
        $show = new Show(User::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('name', __('Name'));
        $show->field('email', __('Email'));
        $show->field('phone_number', __('Phone Number'));
        $show->field('department.name', __('Department'));
        $show->field('roles', __('Roles'))->as(function ($roles) {
            return collect($roles)->pluck('name')->implode(', ');
        });
        $show->field('status', __('Status'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new User());

        $form->text('name', __('Name'))->required();
        $form->email('email', __('Email'))->required();
        $form->mobile('phone_number', __('Phone Number'));
        
        $form->select('department_id', __('Department'))
            ->options(Department::where('is_active', true)->pluck('name', 'id'))
            ->help('Assign this user to a department');
        
        $form->checkbox('roles', __('Roles'))
            ->options(Role::all()->pluck('name', 'id'))
            ->help('Assign roles to this user (Admin, HR, HOD, Employee)');
        
        $form->multipleSelect('work_days', __('Work Days'))
            ->options([
                'Monday' => 'Monday',
                'Tuesday' => 'Tuesday',
                'Wednesday' => 'Wednesday',
                'Thursday' => 'Thursday',
                'Friday' => 'Friday',
                'Saturday' => 'Saturday',
                'Sunday' => 'Sunday',
            ])
            ->default(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
            ->help('Days this user is expected to work');
        
        $form->date('start_working_date', __('Start Working Date'))
            ->help('The date this user started working');
        
        $form->text('position', __('Position'))
            ->help('Job title or role');
        
        $form->radio('status', __('Status'))
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
            ->default('Active');

        return $form;
    }
}
