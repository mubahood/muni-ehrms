<?php

namespace App\Admin\Controllers;

use App\Admin\Concerns\GuardsWorld;
use App\Models\Faculty;
use App\Models\User;
use App\Services\AccessPolicy;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Validation\Rule;

/**
 * Faculties and their Deans. A Dean recommends leave for the academic staff of
 * every department in the faculty and sees their attendance.
 */
class FacultyController extends AdminController
{
    use GuardsWorld;

    protected function worldModel(): string
    {
        return Faculty::class;
    }

    protected $title = 'Faculties';

    protected $description = [
        'index' => 'Faculties and their Deans',
        'show' => 'Details',
        'edit' => 'Edit',
        'create' => 'New',
    ];

    protected function grid(): Grid
    {
        $grid = new Grid(new Faculty());
        $grid->model()->with('dean')->withCount('departments')->where('is_demo', Admin::user()->isDemo())->orderBy('name');
        $canManage = AccessPolicy::allows(Admin::user(), 'organisation.manage');
        if (!$canManage) {
            $grid->disableCreateButton();
        }
        $grid->disableBatchActions();
        $grid->disableFilter();
        $grid->actions(function ($actions) use ($canManage) {
            $actions->disableView();
            $actions->disableDelete();
            if (!$canManage) {
                $actions->disableEdit();
            }
        });

        $grid->column('name', 'Faculty')->display(fn ($n) => e($n) . '<span class="cell-sub">' . e($this->code) . '</span>');
        $grid->column('dean.name', 'Dean')->display(fn ($n) => $n ?: '<span class="st st-pending">Vacant</span>');
        $grid->column('departments_count', 'Departments');
        $grid->column('staff', 'Academic staff')->display(function () {
            return User::whereIn('department_id', $this->departments()->pluck('id'))->where('status', 'Active')->count();
        });
        $grid->column('is_active', 'Status')->display(fn ($v) => $v ? '<span class="st st-present">Active</span>' : '<span class="st st-muted">Inactive</span>');

        return $grid;
    }

    protected function detail($id): Show
    {
        return new Show(Faculty::findOrFail($id));
    }

    protected function form(): Form
    {
        $form = new Form(new Faculty());
        $id = request()->route('faculty');
        $demo = Admin::user()->isDemo();
        $form->text('name', 'Faculty name')->rules(['required', 'max:255', Rule::unique('faculties', 'name')->where('is_demo', $demo)->ignore($id)]);
        $form->text('code', 'Short code')->rules(['required', 'max:20', Rule::unique('faculties', 'code')->ignore($id)])->help('For example FOS.');
        $form->select('dean_id', 'Dean')
            ->options(User::where('status', 'Active')->where('is_demo', $demo)->whereHas('roles', fn ($q) => $q->where('slug', 'dean'))->orderBy('name')->pluck('name', 'id'))
            ->help('Only people with the Faculty Dean role are listed. Leave empty while the post is vacant.');
        $form->switch('is_active', 'Active')->default(1);
        $form->tools(fn ($tools) => $tools->disableDelete()->disableView());
        $form->saving(fn (Form $form) => self::stampWorld($form->model()));

        return $form;
    }
}
