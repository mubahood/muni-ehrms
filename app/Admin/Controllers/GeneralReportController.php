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

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('start_date', __('Start date'));
        $grid->column('end_date', __('End date'));
        $grid->column('file_path', __('File path'));
        $grid->column('is_generated', __('Is generated'));

        // Print PDF or Generate Report button
        $grid->column('print_pdf', __('Print PDF'))->display(function () {
            $url = url('print-general-reports?id=' . $this->id);
            if ($this->is_generated === 'Yes' && !empty($this->file_path)) {
                $url = url('print-general-reports/print?id=' . $this->id);
                return "<a target='_blank' href='{$url}' class='btn btn-xs btn-primary'>Print PDF</a>";
            }
            return "<a target='_blank' href='{$url}' class='btn btn-xs btn-warning'>Generate Report</a>";
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

        $form->date('start_date', __('Start date'))->default(date('Y-m-d'));
        $form->date('end_date', __('End date'))->default(date('Y-m-d'));
        $form->hidden('is_generated', __('Is generated'))->default('No');

        return $form;
    }
}
