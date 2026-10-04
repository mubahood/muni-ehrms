<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\GuardsWorld;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Services\AccessPolicy;
use App\Services\Scope;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Validation\Rule;

/**
 * Departments: academic ones belong to a faculty (their leave goes through the
 * Dean); administrative ones stand alone. The Head of Department set here
 * recommends leave for the department's staff and sees their attendance.
 */
class DepartmentController extends AdminController
{
    use GuardsWorld;

    protected function worldModel(): string
    {
        return Department::class;
    }

    protected $title = 'Departments';

    protected $description = [
        'index' => 'Academic departments sit in a faculty; administrative units stand alone',
        'show' => 'Details',
        'edit' => 'Edit',
        'create' => 'New',
    ];

    protected function grid(): Grid
    {
        $viewer = Admin::user();
        $grid = new Grid(new Department());
        $grid->model()->with(['faculty', 'hod'])->withCount('users');
        $ids = Scope::departmentIds($viewer);
        if ($ids !== null) {
            $grid->model()->whereIn('id', $ids ?: [0]);
        }
        $grid->model()->orderBy('type')->orderBy('name');

        $canManage = AccessPolicy::allows($viewer, 'organisation.manage');
        if (!$canManage) {
            $grid->disableCreateButton();
        }
        $grid->disableBatchActions();
        $grid->actions(function ($actions) use ($canManage) {
            $actions->disableView();
            $actions->disableDelete();
            if (!$canManage) {
                $actions->disableEdit();
            }
        });
        $grid->quickSearch('name', 'code')->placeholder('Search departments');
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->equal('type', 'Type')->select(Department::TYPES);
            $filter->equal('faculty_id', 'Faculty')->select(Faculty::where('is_demo', Admin::user()->isDemo())->orderBy('name')->pluck('name', 'id'));
            $filter->equal('is_active', 'Status')->select([1 => 'Active', 0 => 'Inactive']);
        });

        $grid->column('name', 'Department')->display(function ($name) {
            return e($name) . ($this->code ? '<span class="cell-sub">' . e($this->code) . '</span>' : '');
        })->sortable();
        $grid->column('type', 'Type')->display(fn ($t) => $t === Department::ACADEMIC ? 'Academic' : 'Administrative');
        $grid->column('faculty.name', 'Faculty')->display(fn ($n) => $n ?: '—');
        $grid->column('hod.name', 'Head of Department')->display(fn ($n) => $n ?: '<span class="st st-pending">Vacant</span>');
        $grid->column('users_count', 'Staff')->sortable();
        $grid->column('is_active', 'Status')->display(fn ($v) => $v ? '<span class="st st-present">Active</span>' : '<span class="st st-muted">Inactive</span>');

        return $grid;
    }

    protected function detail($id): Show
    {
        return new Show(Department::findOrFail($id));
    }

    protected function form(): Form
    {
        $form = new Form(new Department());
        $id = request()->route('department');

        $form->text('name', 'Department name')
            ->rules(['required', 'max:255', Rule::unique('departments', 'name')->where('is_demo', Admin::user()->isDemo())->ignore($id)]);
        $form->text('code', 'Short code')
            ->rules(['nullable', 'max:20', Rule::unique('departments', 'code')->ignore($id)])
            ->help('For example CS, FIN. Used in reports.');
        $form->radio('type', 'Type')->options([
            Department::ACADEMIC => 'Academic — belongs to a faculty; leave goes through the Dean',
            Department::ADMINISTRATIVE => 'Administrative — stands alone',
        ])->default(Department::ADMINISTRATIVE)->rules('required')
            ->when(Department::ACADEMIC, function (Form $form) {
                $form->select('faculty_id', 'Faculty')->options(Faculty::active()->where('is_demo', Admin::user()->isDemo())->orderBy('name')->pluck('name', 'id'));
            });
        $form->select('hod_id', 'Head of Department')
            ->options(User::where('status', 'Active')->where('is_demo', Admin::user()->isDemo())->whereHas('roles', fn ($q) => $q->where('slug', 'hod'))->orderBy('name')->pluck('name', 'id'))
            ->help('Only people with the Head of Department role are listed. Leave empty while the post is vacant: leave requests then pass to the next stage automatically.');
        $form->textarea('description', 'Description')->rows(3);
        $form->switch('is_active', 'Active')->default(1);

        $form->saving(function (Form $form) {
            if ($form->type !== Department::ACADEMIC) {
                $form->faculty_id = null;
            } elseif (!$form->faculty_id) {
                admin_toastr('An academic department must belong to a faculty.', 'error');

                return back()->withInput();
            }
            if (!$form->model()->exists) {
                $form->model()->created_by = Admin::user()->id;
            }
            self::stampWorld($form->model());
            // A faculty or Head from the other world can never be attached.
            $demo = Admin::user()->isDemo();
            if ($form->faculty_id && !Faculty::whereKey($form->faculty_id)->where('is_demo', $demo)->exists()) {
                abort(403);
            }
            if ($form->hod_id && !User::whereKey($form->hod_id)->where('is_demo', $demo)->exists()) {
                abort(403);
            }
        });
        $form->tools(fn ($tools) => $tools->disableDelete()->disableView());

        return $form;
    }
}
