<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\GuardsWorld;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\Scope;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Validation\Rule;

/**
 * Staff records. Heads and Deans see their own people; Human Resource and the
 * System Administrator see and maintain everyone.
 *
 * Two fields are the System Administrator's alone, as in the reference
 * system: roles (so nobody can raise their own access) and a personal late
 * arrival time. Staff are deactivated rather than deleted, so their history
 * stays intact.
 */
class UsersController extends AdminController
{
    use GuardsWorld;

    protected function worldModel(): string
    {
        return User::class;
    }

    protected $title = 'Employees';

    protected $description = [
        'index' => 'Staff records, Terminal IDs and sign-in details',
        'show' => 'Details',
        'edit' => 'Edit',
        'create' => 'New',
    ];

    private const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected function grid()
    {
        $viewer = Admin::user();
        $grid = new Grid(new User());
        $grid->model()->with(['department', 'roles']);
        Scope::apply($grid->model(), $viewer);
        $grid->model()->orderBy('status')->orderBy('name');

        $canManage = AccessPolicy::allows($viewer, 'employees.manage');
        if (!$canManage) {
            $grid->disableCreateButton();
        }
        $grid->disableBatchActions();
        $grid->actions(function (Grid\Displayers\Actions $actions) use ($canManage) {
            $actions->disableDelete();
            if (!$canManage) {
                $actions->disableEdit();
            }
        });

        $grid->quickSearch('name', 'username', 'employee_no', 'email')->placeholder('Search name, staff no. or e-mail');
        $grid->filter(function (Grid\Filter $filter) use ($viewer) {
            $filter->disableIdFilter();
            $filter->equal('department_id', 'Department')->select(Scope::departments($viewer)->pluck('name', 'id'));
            $filter->where(function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('slug', $this->input));
            }, 'Role')->select(User::ROLE_LABELS);
            $filter->equal('status', 'Status')->select(['Active' => 'Active', 'Inactive' => 'Inactive']);
        });

        $grid->column('employee_no', 'Staff no.')->display(fn ($no) => $no ?: '<span class="cell-sub">not set</span>')->sortable();
        $grid->column('name', 'Name')->display(function ($name) {
            return e($name) . ($this->position ? '<div class="cell-sub">' . e($this->position) . '</div>' : '');
        })->sortable();
        $grid->column('department.name', 'Department');
        $grid->column('roles', 'Role')->display(fn () => e($this->roleLabel()));
        $grid->column('custom_late_time', 'Late after')->display(function ($time) {
            return $time ? substr($time, 0, 5) : '<span class="cell-sub">default</span>';
        });
        $grid->column('status', 'Status')->display(function ($status) {
            return $status === 'Active'
                ? '<span class="st st-present">Active</span>'
                : '<span class="st st-muted">Inactive</span>';
        });
        $grid->column('dashboard', ' ')->display(function () {
            return '<a href="' . admin_url('staff/' . $this->id) . '" class="link-quiet">Attendance</a>';
        });

        return $grid;
    }

    protected function detail($id)
    {
        $user = User::with(['department.faculty', 'roles'])->findOrFail($id);
        abort_unless(Scope::canSee(Admin::user(), (int) $user->id), 403);

        $show = new Show($user);
        $show->panel()->title($user->displayName());
        $show->field('employee_no', 'Staff no. / Terminal ID');
        $show->field('name', 'Name');
        $show->field('position', 'Job title');
        $show->field('department.name', 'Department');
        $show->field('faculty', 'Faculty')->as(fn () => optional(optional($user->department)->faculty)->name ?: '—');
        $show->field('roles', 'Role')->as(fn () => $user->roleLabel());
        $show->field('email', 'E-mail');
        $show->field('phone_number', 'Telephone');
        $show->field('start_working_date', 'Started');
        $show->field('work_days', 'Work days')->as(fn ($days) => implode(', ', (array) $days) ?: 'Institution working days');
        $show->field('custom_late_time', 'Late after')->as(fn ($t) => $t ? substr($t, 0, 5) : 'Default (' . substr(\App\Models\SystemConfiguration::current()->defaultLateTime(), 0, 5) . ')');
        $show->field('status', 'Status');
        $show->panel()->tools(function ($tools) {
            $tools->disableDelete();
            if (!AccessPolicy::allows(Admin::user(), 'employees.manage')) {
                $tools->disableEdit();
            }
        });

        return $show;
    }

    protected function form()
    {
        $viewer = Admin::user();
        $isAdmin = $viewer->hasAnyRole('admin');
        $form = new Form(new User());
        $id = request()->route('user');

        $form->tab('Staff record', function (Form $form) use ($viewer, $isAdmin, $id) {
            $form->text('employee_no', 'Staff no. / Terminal ID')
                ->creationRules(['required', 'max:45', 'unique:users,employee_no'])
                ->updateRules(['required', 'max:45', Rule::unique('users', 'employee_no')->ignore($id)])
                ->help('Must match the Employee No. this person is enrolled under on the face-recognition terminals.');
            // The full name is built from these two on every save (laravel-admin's Administrator model).
            $form->text('first_name', 'First name')->rules('required|max:45');
            $form->text('last_name', 'Surname')->rules('required|max:45');
            $form->text('position', 'Job title');
            $form->select('department_id', 'Department')
                ->options(Scope::departments($viewer)->pluck('name', 'id'))
                ->rules('required');
            $form->email('email', 'E-mail');
            $form->text('phone_number', 'Telephone');
            $form->date('start_working_date', 'Started work on')->default(date('Y-m-d'))->rules('required|date');
            $form->multipleSelect('work_days', 'Work days')
                ->options(array_combine(self::WEEKDAYS, self::WEEKDAYS))
                ->default(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
                ->help('Only change this for staff who are expected on different days from the rest of the University.');
            if ($isAdmin) {
                $form->time('custom_late_time', 'Late after')->format('HH:mm:ss')
                    ->help('Leave empty to use the University default. Only the System Administrator can set this.');
            }
            $form->radio('status', 'Status')->options(['Active' => 'Active', 'Inactive' => 'Inactive'])->default('Active')
                ->help('Inactive staff are no longer expected at work; their history is kept.');
        });

        $form->tab('Sign-in', function (Form $form) use ($isAdmin, $id) {
            $form->text('username', 'Username')
                ->creationRules(['required', 'max:45', 'unique:users,username'])
                ->updateRules(['required', 'max:45', Rule::unique('users', 'username')->ignore($id)]);
            $form->password('password', 'Password')
                ->creationRules(['required', 'min:8'])
                ->updateRules(['nullable', 'min:8'])
                ->help('On an existing record, leave empty to keep the current password.');
            if ($isAdmin) {
                $form->checkbox('roles', 'Roles')
                    ->options(Role::orderBy('id')->pluck('name', 'id'))
                    ->help('Only the System Administrator can change roles. Heads and Deans are linked to what they lead on the department or faculty.');
            }
        });

        $form->tools(fn ($tools) => $tools->disableDelete());

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password !== $form->password) {
                $form->password = bcrypt($form->password);
            }
            if (!$form->password) {
                $form->ignore(['password']);
            }
            self::stampWorld($form->model());
            if (Admin::user()->isDemo()) {
                // Accounts made in the sandbox never e-mail anyone.
                $form->model()->notify_account_created_by_email = 'No';
            }
            // A Head or HR may only place staff in departments they can see.
            if ($form->department_id && !Scope::departments(Admin::user())->pluck('id')->contains((int) $form->department_id)) {
                abort(403, 'You cannot place staff in that department.');
            }
        });

        $form->saved(function (Form $form) {
            $user = $form->model();
            // Everyone holds the Employee role.
            $employee = Role::where('slug', 'employee')->value('id');
            if ($employee && !$user->roles()->where('admin_roles.id', $employee)->exists()) {
                $user->roles()->attach($employee);
            }
        });

        return $form;
    }
}
