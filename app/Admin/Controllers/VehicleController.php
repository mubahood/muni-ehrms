<?php

namespace App\Admin\Controllers;

use App\Models\Vehicle;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class VehicleController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Vehicles';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Vehicle());

        $grid->model()->orderBy('id', 'desc');
        $grid->column('registration_number', __('Reg No.'))->sortable();
        $grid->column('vehicle_type', __('Vehicle Type'))->sortable();
        $grid->column('brand', __('Brand'))->sortable();
        $grid->column('model', __('Model'))->sortable();
        $grid->column('color', __('Color'))->sortable();
        $grid->column('year', __('Year'))->sortable();
        $grid->column('status', __('Status'))->sortable()
            ->display(function ($status) {
                if ($status == 'Active') {
                    return "<span class='label label-success'>$status</span>";
                } else {
                    return "<span class='label label-danger'>$status</span>";
                }
            })->filter(['Active' => 'Active', 'Inactive' => 'Inactive']);
        $grid->column('rent_status', __('Rent status'))
            ->display(function ($rent_status) {
                if ($rent_status == 'Available') {
                    return "<span class='label label-success'>$rent_status</span>";
                } else {
                    return "<span class='label label-danger'>$rent_status</span>";
                }
            })->filter(['Available' => 'Available', 'Unavailable' => 'Unavailable']);

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
        $show = new Show(Vehicle::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('registration_number', __('Registration number'));
        $show->field('vehicle_type', __('Vehicle type'));
        $show->field('brand', __('Brand'));
        $show->field('model', __('Model'));
        $show->field('color', __('Color'));
        $show->field('year', __('Year'));
        $show->field('status', __('Status'));
        $show->field('rent_status', __('Rent status'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Vehicle());

        $form->text('registration_number', __('Registration Number'))->rules('required');
        $form->radio('vehicle_type', __('Vehicle Type'))
            ->options(['LV' => 'LV', 'Bus' => 'Bus', 'Truck' => 'Truck', 'Van' => 'Van', 'SUV' => 'SUV', 'Saloon' => 'Saloon', 'Motorcycle' => 'Motorcycle', 'Bicycle' => 'Bicycle', 'Trailer' => 'Trailer', 'Other' => 'Other'])
            ->rules('required');
        $form->text('brand', __('Brand'));
        $form->text('model', __('Model'));
        $form->color('color', __('Color'));
        $form->text('year', __('Year'));
        $form->radio('status', __('Status'))->default('Active')->rules('required')
            ->options(['Active' => 'Active', 'Inactive' => 'Inactive']);

        return $form;
    }
}
