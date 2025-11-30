<?php

namespace App\Admin\Controllers;

use App\Models\Departmet;
use App\Models\User;
use App\Models\Utils;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class DepartmetController extends AdminController
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
    protected function grid()
    {
        $grid = new Grid(new Departmet());

        $grid->disableFilter();
        $grid->quickSearch('name', 'description')->placeholder('Search by name or description');

        $grid->model()->orderBy('id', 'desc');
        $grid->disableBatchActions();
        $grid->column('name', __('Name'))->sortable();
        $grid->column('code', __('Code'))->sortable();
        $grid->column('description', __('Description'));
        $grid->column('head_of_department_id', __('HOD'))->sortable()
            ->display(function ($head_of_department_id) {
                if ($this->hod) {
                    return $this->hod->name;
                } else {
                    return 'N/A';
                }
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
        $show = new Show(Departmet::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('name', __('Name'));
        $show->field('code', __('Code'));
        $show->field('description', __('Description'));
        $show->field('head_of_department_id', __('Head of department id'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Departmet());


        $form->text('name', __('Name'))->rules('required');
        $form->text('code', __('Code'));
        $form->select('head_of_department_id', __('HOD'))
            ->options(Utils::get_dropdown(User::class, 'name'))
            ->rules('required');
        $form->textarea('description', __('Department Description'));

        return $form;
    }
}
