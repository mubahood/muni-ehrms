<?php

namespace App\Admin\Controllers;

use App\Models\SystemConfiguration;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SystemConfigurationController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'SystemConfiguration';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new SystemConfiguration());

        $grid->column('id', __('Id'));
        $grid->column('created_at', __('Created at'));
        $grid->column('updated_at', __('Updated at'));
        $grid->column('company_name', __('Company name'));
        $grid->column('company_address', __('Company address'));
        $grid->column('company_phone', __('Company phone'));
        $grid->column('company_email', __('Company email'));
        $grid->column('company_logo', __('Company logo'));
        $grid->column('start_date', __('Start date'));

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
        $show = new Show(SystemConfiguration::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('company_name', __('Company name'));
        $show->field('company_address', __('Company address'));
        $show->field('company_phone', __('Company phone'));
        $show->field('company_email', __('Company email'));
        $show->field('company_logo', __('Company logo'));
        $show->field('start_date', __('Start date'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new SystemConfiguration());

        $form->text('company_name', __('Company name'))->rules('required');
        $form->text('company_address', __('Company address'))->rules('required');
        $form->text('company_phone', __('Company phone'))->rules('required');
        $form->text('company_email', __('Company email'))->rules('required');
        $form->image('company_logo', __('Company logo'))->rules('required');
        $form->date('start_date', __('Start date'))->default(date('Y-m-d'))->rules('required');
        //late_time starts at
        $form->time('late_time', __('Late time'))->rules('required');

        return $form;
    }
}
