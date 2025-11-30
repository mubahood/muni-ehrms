<?php

namespace App\Admin\Controllers;

use App\Models\ExitRecord;
use App\Models\VehicleRequest;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use PDO;

class ExitRecordController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Exit Records';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new ExitRecord());
        $grid->model()->orderBy('id', 'desc');
        $grid->disableBatchActions();
        $grid->disableCreateButton();
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $exit_recs = [];
            foreach (ExitRecord::all() as $rec) {
                $vr = VehicleRequest::find($rec->vehicle_request_id);
                if ($vr != null) {
                    $exit_recs[$rec->id] = 'Request #' . $vr->id;
                    if ($vr->vehicle != null) {
                        $exit_recs[$rec->id] = 'Request #' . $vr->id . ' - ' . $vr->vehicle->registration_number;
                    }
                }
            }
            $filter->equal('vehicle_request_id', __('Vehicle Request'))
                ->select($exit_recs);
            $users = [];
            foreach (\App\Models\User::all() as $user) {
                $users[$user->id] = $user->name;
            }
            $filter->equal('employee_id', __('Employee'))
                ->select($users);
            //exit_time range
            $filter->between('exit_time', __('Check Out'))
                ->datetime();
            //return_time range
            $filter->between('return_time', __('Check In'))
                ->datetime(); 
        });

        $grid->column('id', __('Id'))->hide();
        $grid->column('created_at', __('Date'))
            ->display(function ($created_at) {
                return date('d-m-Y H:i:s', strtotime($created_at));
            })->sortable()->hide();
        $grid->column('employee_id', __('Employee'))
            ->display(function ($employee_id) {
                $emp = \App\Models\User::find($employee_id);
                if ($emp != null) {
                    return $emp->name;
                } else {
                    return 'N/A';
                }
            })->sortable();
        $grid->column('vehicle_request_id', __('Vehicle Rquest'))
            ->display(function ($vehicle_request_id) {
                $vr = \App\Models\VehicleRequest::find($vehicle_request_id);
                if ($vr != null) {
                    $dp_text = 'Request #' . $vr->id;
                    if ($vr->vehicle != null) {
                        $dp_text = 'request #' . $vr->id . ' - ' . $vr->vehicle->registration_number;
                    }
                    return $dp_text;
                } else {
                    return 'N/A';
                }
            })->sortable();
        $grid->column('created_by_id', __('Created by'))
            ->display(function ($created_by_id) {
                $user = \App\Models\User::find($created_by_id);
                if ($user != null) {
                    return $user->name;
                } else {
                    return 'N/A';
                }
            })->sortable();

        $grid->column('remarks', __('Remarks'));

        $grid->column('status', __('Status'))
            ->display(function ($status) {
                if ($status == 'exit') {
                    return '<span class="label label-danger">Exit</span>';
                } else {
                    return '<span class="label label-success">Returned</span>';
                }
            })->sortable()
            ->filter([
                'exit' => 'Exit',
                'return' => 'Returned',
            ]);
        $grid->column('exit_time', __('Check Out'))
            ->display(function ($exit_time) {
                return date('d-m-Y H:i:s', strtotime($exit_time));
            })->sortable();
        $grid->column('return_time', __('Check In'))
            ->display(function ($return_time) {
                if ($return_time == null || $return_time == '') {
                    return 'N/A';
                }
                return date('d-m-Y H:i:s', strtotime($return_time));
            })->sortable();

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
        $show = new Show(ExitRecord::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));
        $show->field('employee_id', __('Employee id'));
        $show->field('vehicle_request_id', __('Vehicle request id'));
        $show->field('created_by_id', __('Created by id'));
        $show->field('status', __('Status'));
        $show->field('remarks', __('Remarks'));
        $show->field('exit_time', __('Check Out'));
        $show->field('return_time', __('Check In'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new ExitRecord());

        $vehicle_request = null;
        $is_creating = false;

        if ($form->isCreating()) {
            //check if methord is get
            if (request()->isMethod('get')) {
                $vehicle_request_id = request()->get('vehicle_request_id');
                $vehicle_request = \App\Models\VehicleRequest::find($vehicle_request_id);
                $is_creating = true;
            }
        } else {
            $segs = request()->segments();
            $record_id = null;
            if (isset($segs[1])) {
                $record_id = $segs[1];
            }
            $rec = ExitRecord::find($record_id);
            if ($rec != null) {
                $vehicle_request = VehicleRequest::find($rec->vehicle_request_id);
            }
        }


        // $form->number('employee_id', __('Employee id')); 
        if ($is_creating) {
            if ($vehicle_request == null) {
                admin_error('Vehicle Request Not Found');
                $form->disableSubmit();
                $form->disableReset();
                $form->disableViewCheck();
                $form->disableEditingCheck();
                $form->disableCreatingCheck();
                return $form;
            }
        }

        if ($vehicle_request != null) {
            $form->hidden('vehicle_request_id', __('Vehicle request id'))
                ->default($vehicle_request->id . "");
            //display vehicle request details
            $dp_text = 'Request #' . $vehicle_request->id;
            if ($vehicle_request->vehicle != null) {
                $dp_text = 'request #' . $vehicle_request->id . ' - ' . $vehicle_request->vehicle->registration_number;
            }
            $form->display('vehicle_request.registration_number', __('Vehicle Registration Number'))
                ->default($dp_text);
        } else {
            $form->text('vehicle_request_id', __('Vehicle request id'))
                ->default(0);
        }

        $u = Admin::user();

        if (!$form->isEditing()) {
            //created_by_id
            $form->hidden('created_by_id', __('Created by id'))
                ->default($u->id);
        }

        $form->radio('status', __('Status'))
            ->options([
                'exit' => 'Exit',
                'return' => 'Returned',
            ])->default('exit')
            ->when('exit', function (Form $form) {
                $form->datetime('exit_time', __('Check Out'))->default(date('Y-m-d H:i:s'));
            })->when('return', function (Form $form) {
                $form->datetime('return_time', __('Check In'))->default(date('Y-m-d H:i:s'));
            });
        $form->text('remarks', __('Remarks'));

        return $form;
    }
}
