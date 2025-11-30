<?php

namespace Encore\Admin\Controllers;

use App\Models\Company;
use App\Models\Departmet;
use App\Models\User;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Hash;

class UserController extends AdminController
{
    /**
     * {@inheritdoc}
     */
    protected function title()
    {
        return 'Users';
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $userModel = config('admin.database.users_model');

        $grid = new Grid(new $userModel());

        $downloadUrl = url('download-user-form-data');

        $grid->tools(function (Grid\Tools $tools) use ($downloadUrl) {
            $tools->append('<a 
            target="_blank"
            href="' . $downloadUrl . '" class="btn btn-sm btn-success"><i class="fa fa-download"></i> Download User Data</a>');
        });


        $grid->quickSearch('name', 'username', 'phone_number')->placeholder('Search by name, username');

        $grid->disableBatchActions();
        $grid->column('id', 'ID')->sortable();
        $grid->column('avatar', __('Photo'))
            ->width(80)
            ->lightbox(['width' => 60, 'height' => 60])
            ->hide();

        $grid->model()->orderBy('id', 'desc');
        $grid->column('name', trans('admin.name'))->sortable();
        $grid->column('sex', 'Gender')->hide();



        //phone_number
        $grid->column('phone_number', 'Phone Number')
            ->sortable();

        $grid->column('status', 'Status')
            ->dot([
                'Active' => 'success',
                'Inactive' => 'danger',
            ])
            ->sortable();


        $grid->column('work_days', trans('admin.roles'))
            ->label();
        $grid->column('created_at', 'Registered')->sortable()
            ->hide();

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            if ($actions->getKey() == 1) {
                $actions->disableDelete();
            }
        });

        //title
        $grid->column('title', 'Late Starts')
            ->sortable();

        //total hours worked
        $grid->column('hours', 'Total Hours Worked')
            ->sortable();

        return $grid;
        $grid->tools(function (Grid\Tools $tools) {
            $tools->batch(function (Grid\Tools\BatchActions $actions) {
                //$actions->disableDelete();
            });
        });

        //action send message 
        $grid->column('send_message', 'Send Password Reset Mail')
            ->display(function () {
                $url = url('send-new-password?user_id=' . $this->id);
                return '<a href="' . $url . '" class="btn btn-xs btn-primary" target="_blank">Send Password Reset Mail</a>';
            });



        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $userModel = config('admin.database.users_model');

        $show = new Show($userModel::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('username', trans('admin.username'));
        $show->field('name', trans('admin.name'));
        $show->field('roles', trans('admin.roles'))->as(function ($roles) {
            return $roles->pluck('name');
        })->label();
        $show->field('permissions', trans('admin.permissions'))->as(function ($permission) {
            return $permission->pluck('name');
        })->label();
        $show->field('created_at', 'Registered');

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    public function form()
    {
        $userModel = config('admin.database.users_model');
        $permissionModel = config('admin.database.permissions_model');
        $roleModel = config('admin.database.roles_model');

        $form = new Form(new $userModel());

        $userTable = config('admin.database.users_table');
        $connection = config('admin.database.connection');

        $form->display('id', 'ID');

        //phone_number

        $form->text('name', trans('admin.name'))->rules('required');
        $form->radio('sex', 'Gender')
            ->options(['Male' => 'Male', 'Female' => 'Female']);

        $form->text('phone_number', 'Phone Number');




        $form->checkbox('roles', trans('admin.roles'))->options($roleModel::all()->pluck('name', 'id'));
        $form->radio('change_password', 'Change Password')
            ->options(['Yes' => 'Yes', 'No' => 'No'])
            ->default('No')
            ->rules('required')
            ->when('Yes', function (Form $form) {
                $form->password('password', trans('admin.password'))->rules('required|confirmed');
                $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
                    ->default(function ($form) {
                        return $form->model()->password;
                    });
            });

        /* 
            "work_days" => null
    "status" => "Active"
        */

        //find u 140
        // $u = User::find(140);
        // dd($u->work_days);


        //work_days multiple select
        $form->checkbox('work_days', 'Work Days')
            ->options([
                'Monday' => 'Monday',
                'Tuesday' => 'Tuesday',
                'Wednesday' => 'Wednesday',
                'Thursday' => 'Thursday',
                'Friday' => 'Friday',
                'Saturday' => 'Saturday',
                'Sunday' => 'Sunday'
            ])
            ->rules('required');

        //title as late time stats
        $form->time('title', 'Late Time Stats')
            ->default('08:00:00')
            ->rules('required')
            ->help('Set the time after which the user is considered late. For example, if you set it to 09:00:00, the user will be considered late if they clock in after 9 AM.');

        //start_working_date
        $form->date('start_working_date', 'Start Working Date')
            ->default(date('Y-m-d'))
            ->rules('required');


        //notify_account_created_by_email notify user by email when account is created

        $form->ignore(['password_confirmation']);


        /*         $form->multipleSelect('permissions', trans('admin.permissions'))->options($permissionModel::all()->pluck('name', 'id')); */

        /*         $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));
 */
        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = Hash::make($form->password);
            }

            if ($form->password == null || $form->password == '') {
                //set password default as 4321
                $form->password = Hash::make('4321');
            }
        });


        //status
        $form->radio('status', 'Status')
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
            ->default('Active')
            ->rules('required');

        return $form;
    }
}
