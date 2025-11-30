<?php

namespace App\Admin\Controllers;

use App\Models\Departmet;
use App\Models\ImportUserData;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class ImportUserDataController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'User Data Import';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ImportUserData());
        $templatePath = url('assets/user-data-import.csv');
        //display the template link
        // $grid->html('<a href="' . $templatePath . '" target="_blank">Download User Data Import Template</a>', 'Template');

        $grid->column('id', __('Id'))->sortable();
        $grid->model()->orderBy('id', 'desc');
        $grid->column('status', __('title'));
        $grid->column('title', __('File'))
            ->display(function ($title) {
                return '<a href="' . url('storage/' . $title) . '" target="_blank">' . $title . '</a>';
            })->sortable();
        $grid->column('department_id', __('Department'))
            ->display(function ($department_id) {
                if ($this->department) {
                    return $this->department->name;
                } else {
                    return 'N/A';
                }
            })->sortable();

        $grid->column('created_at', __('Created'))
            ->display(function ($created_at) {
                return $created_at ? date('Y-m-d H:i:s', strtotime($created_at)) : '';
            })->sortable();
        $grid->column('download', __('Import'))
            ->display(function () {
                $url = url('import-user-data?id=' . $this->id);
                return '<a href="' . $url . '" class="btn btn-xs btn-primary" target="_blank">Import Data</a>';
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
        $show = new Show(ImportUserData::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('title', __('Title'));
        $show->field('department_id', __('Department id'));
        $show->field('status', __('Status'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ImportUserData());
        $templatePath = url('assets/user-data-import.csv');

        //display the template link
        $form->html('<a href="' . $templatePath . '" target="_blank">Download User Data Import Template</a>', 'Template');

        $form->text('status', __('Title'))->rules('required|max:255')->required();
        $form->file('title', __('File'))->rules('required')->required()->uniqueName();
        $form->select('department_id', __('Department'))
            ->options(function ($id) {
                return Departmet::all()->pluck('name', 'id');
            })->rules('required');

        return $form;
    }
}
