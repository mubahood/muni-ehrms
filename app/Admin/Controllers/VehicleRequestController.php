<?php

namespace App\Admin\Controllers;

use App\Models\Departmet;
use App\Models\Utils;
use App\Models\VehicleRequest;
use Carbon\Carbon;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class VehicleRequestController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = 'Vehicle Exit Requests';
    //set title for the page using function


    protected function title()
    {
        $segs = request()->segments();

        if (in_array('vehicle-requests', $segs)) {

            $title = 'Vehicle Exit Requests';
        } elseif (in_array('leave-requests', $segs)) {
            $title = 'Leave Requests';
        } elseif (in_array('materials-requests', $segs)) {
            $title = 'Materials Requests';
        } else if (in_array('all-requests', $segs)) {
            $title = 'All Requests';
        } else if (in_array('archived-requests', $segs)) {
            $title = 'Archived Requests';
        } else {
            throw new \Exception("Invalid route");
        }
        return   $title;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new VehicleRequest());
        $conds = [];
        $segs = request()->segments();

        $u = Admin::user();

        //if security, disable actions
        if ($u->isRole('security')) {
            $grid->disableActions();
            $grid->disableCreateButton();
            $grid->disableRowSelector();
            $grid->disableExport();
        }

        //is_closed archived-requests
        $grid->filter(function ($filter) use ($u, $segs) {
            $filter->disableIdFilter();

            $filter->equal('applicant_id', __('Requestor'))
                ->select(Utils::get_dropdown(\App\Models\User::class, ['name', 'id']));
            $filter->equal('vehicle_id', __('Vehicle'))
                ->select(Utils::get_dropdown(\App\Models\Vehicle::class, ['name',  'vehicle_type']));

            //html that says, if vehicle is not found, click here to add a new vehicle and refresh the page after adding


            $filter->equal('department_id', __('Department'))
                ->select(Utils::get_dropdown(\App\Models\Departmet::class, ['name', 'id']));
            $filter->between('created_at', __('Date'))->datetime();

            $filter->equal('hod_status', __('HOD Status'))->select([
                'Pending' => 'Pending',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
            ]);
            $filter->equal('gm_status', __('GM Status'))->select([
                'Pending' => 'Pending',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
            ]);
            $filter->equal('security_exit_status', __('Security Exit Status'))->select([
                'Pending' => 'Pending',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
            ]);
            $filter->equal('security_return_status', __('Security Return Status'))->select([
                'Pending' => 'Pending',
                'Approved' => 'Approved',
                'Rejected' => 'Rejected',
            ]);
            $filter->like('destination', __('Destination'));
            $filter->like('justification', __('Justification'));
            $filter->equal('is_closed', __('Is Closed'))->select([
                'Yes' => 'Yes',
                'No' => 'No',
            ]);
        });


        if ($u->isRole('hod')) {
            $conds['hod_status'] = 'Pending';
            $conds['department_id'] = $u->department_id;
        }
        if ($u->isRole('gm')) {
            // $conds['hod_status'] = 'Approved'; 
        }

        if ($u->isRole('security')) {
            $conds['gm_status'] = 'Approved';
        }



        if (in_array('vehicle-requests', $segs)) {
            $conds['type'] = 'Vehicle';
            if ($u->isRole('hod')) {
                $conds['hod_status'] = 'Pending';
            }
            if ($u->isRole('gm')) {
                $conds['hod_status'] = 'Approved';
                $conds['gm_status'] = 'Pending';
                $conds['department_id'] = $u->department_id;
            }

            if ($u->isRole('security')) {
                $conds['gm_status'] = 'Approved';
                $conds['security_exit_status'] = 'Pending';
                $conds['type'] = 'Vehicle';
            }
        } else if (in_array('all-requests', $segs)) {
            if ($u->isRole('employee')) {
                $conds['applicant_id'] = $u->id;
            }
        } else if (in_array('archived-requests', $segs)) {
            $conds['is_closed'] = 'Yes';
        } else if (in_array('materials-requests', $segs)) {
            $conds['type'] = 'Materials';
        } else if (in_array('leave-requests', $segs)) {
            $conds['type'] = 'Personnel';
        } else {
            throw new \Exception("Invalid route");
        }

        if (
            !in_array('vehicle-requests', $segs) &&
            !in_array('materials-requests', $segs) &&
            !in_array('leave-requests', $segs)
        ) {
            $grid->disableCreateButton();
        }

        if (!$u->isRole('gm')) {
            $grid->disableExport();
        }

        if ($u->isRole('employee')) {
            $conds['applicant_id'] = $u->id;
        }
        if ($u->isRole('security')) {
            $conds['gm_status'] = 'Approved';
        }
        if ($u->isRole('admin')) {
            $conds = [];
        }

        //is achived => archived-requests
        if (in_array('archived-requests', $segs)) {
            $conds['is_closed'] = 'Yes';
        } else {
            $conds['is_closed'] = 'No';
        }


        $grid->model()
            ->where($conds)
            ->orderBy('id', 'desc');


        $grid->disableBatchActions();
        $grid->column('created_at', __('Date'))->display(function ($created_at) {
            return date('d-m-Y', strtotime($created_at));
        })->sortable();
        $grid->column('applicant_id', __('Requestor'))
            ->display(function ($applicant_id) {
                if ($this->applicant) {
                    return $this->applicant->name;
                } else {
                    return 'N/A';
                }
            })->sortable();
        $grid->column('vehicle_id', __('Items requested for'))
            ->display(function ($vehicle_id) {
                return $this->getTitle();
            })->sortable();
        // type request type
        $grid->column('type', __('Type'))->sortable()
            ->dot([
                'Vehicle' => 'info',
                'Materials' => 'success',
                'Personnel' => 'warning',
            ])->filter([
                'Vehicle' => 'Vehicle',
                'Materials' => 'Materials',
                'Personnel' => 'Personnel',
            ]);

        //department_id
        $grid->column('department_id', __('Department'))
            ->display(function ($department_id) {
                if ($this->department) {
                    return $this->department->name;
                } else {
                    return 'N/A';
                }
            })->sortable();


        $grid->column('actual_return_time', __('Actual Check In'))
            ->display(function ($actual_return_time) {
                return date('d-m-Y H:i:s', strtotime($actual_return_time));
            })->sortable()
            ->hide();
        $grid->column('actual_departure_time', __('Actual Departure Time'))
            ->display(function ($actual_departure_time) {
                return date('d-m-Y H:i:s', strtotime($actual_departure_time));
            })->sortable()
            ->hide();
        $grid->column('destination', __('Destination'))
            ->display(function ($destination) {
                return $destination;
            })->sortable()->hide();
        $grid->column('justification', __('Justification'))
            ->display(function ($justification) {
                return $justification;
            })->sortable();
        /*   $grid->column('status', __('Status'))
            ->label([
                'Pending' => 'warning',
                'Approved' => 'success',
                'Rejected' => 'danger',
            ])->sortable(); */

        //requested_return_time expiry time 
        if (!in_array('materials-requests', $segs)) {
            $grid->column('requested_departure_time', __('Requested Departure Time'))
                ->display(function ($requested_departure_time) {
                    if ($requested_departure_time == null || $requested_departure_time == '') {
                        return 'Invalid Departure Time';
                    }
                    return Utils::my_date($requested_departure_time);
                })->sortable();
        }
        $grid->column('hod_status', __('HOD Status'))
            ->label([
                'Pending' => 'warning',
                'Approved' => 'success',
                'Rejected' => 'danger',
            ])->sortable();
        $grid->column('gm_status', __('GM Status'))
            ->label([
                'Pending' => 'warning',
                'Approved' => 'success',
                'Rejected' => 'danger',
            ])->sortable();


        $grid->column('exit_state', __('Exit state'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()->hide();
        $grid->column('return_state', __('Return state'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()
            ->hide();

        $grid->column('over_stayed', __('Over stayed'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()->hide();



        $grid->column('exit_comment', __('Exit comment'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()
            ->hide();

        $grid->column('return_comment', __('Return comment'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()->hide();

        $grid->column('hod_comment', __('Hod comment'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()->hide();

        $grid->column('gm_comment', __('Gm comment'))
            ->display(function ($value) {
                return $value;
            })
            ->sortable()->hide();

        //print
        $grid->column('print', __('Print'))->display(function () {
            //if gm not appoved, return N/A
            if ($this->gm_status != 'Approved') {

                return 'N/A';
            }
            $url = url('print-gatepass') . '?gatepass_id=' . $this->id;
            return '<a href="' . $url . '" target="_blank" class="btn btn-xs btn-primary">Print</a>';
        })->width(100);

        if ($u->isRole('security') || $u->isRole('gm') || $u->isRole('admin')) {

            $grid->column('exit_record_view', __('Exit Records (View)'))->display(function () {
                //if gm not appoved, return N/A
                if ($this->gm_status != 'Approved') {
                    return 'N/A';
                }
                $recs_count = $this->exitRecords()->count();
                $url_view_record = admin_url('exit-records?vehicle_request_id=' . $this->id);
                return '<a href="' . $url_view_record . '" class="btn btn-xs btn-primary">View Exit Records (' . $recs_count . ')</a>';
            });
        }

        if ($u->isRole('security')) {
            $grid->column('exit_record', __('Exit Records (Add)'))->display(function () {

                if ($this->requested_return_time == null || $this->requested_return_time == '') {
                    return 'Invalid Check In';
                }
                $exipiry = null;
                try {
                    $exipiry = Carbon::parse($this->requested_return_time);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                $now = Carbon::now();
                if ($exipiry == null) {
                    return 'Invalid Return Date';
                }

                //if has expired, return N/A
                $now = Carbon::now();
                if ($now->greaterThan($exipiry)) {
                    return 'Expired';
                }

                //if gm not appoved, return N/A
                if ($this->gm_status != 'Approved') {
                    return 'Not Approved';
                }
                $url_add_record = admin_url('exit-records/create?vehicle_request_id=' . $this->id);
                return '<a href="' . $url_add_record . '" class="btn btn-xs btn-primary">Add Exit Record</a>';
            });
        }
        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $vehicleRequest = VehicleRequest::findOrFail($id);
        $show = new Show($vehicleRequest);

        $id = $vehicleRequest->id;
        $record = VehicleRequest::find($id);
        if ($record == null) {
            throw new \Exception("Record not found");
        }

        $show->field('created_at', __('Created'))
            ->as(function ($created_at) {
                return Utils::my_date($created_at);
            });

        // Applicant and Vehicle Information
        $show->field('applicant_id', __('Requestor'))->as(function ($applicant_id) {
            return $this->applicant ? $this->applicant->name : 'N/A';
        });

        if ($record->type == 'Vehicle') {
            $show->field('vehicle_id', __('Vehicle'))->as(function ($vehicle_id) {
                if ($this->vehicle) {
                    return $this->vehicle->registration_number . ' - '  . $this->vehicle->vehicle_type;
                }
                return 'N/A';
            });
            // Request Details
            $show->field('destination', __('Destination'))->as(function ($destination) {
                return $destination ?: 'N/A';
            });
            $show->field('justification', __('Justification'))->as(function ($justification) {
                return $justification ?: 'N/A';
            });

            $show->field('is_somisy_vehicle', __('Is Somisy Vehicle'))->as(function ($value) { 
                return $value == 'Yes' ? 'Yes' : 'No';
            });
            $show->field('is_camp_resident', __('Is Camp Resident'))->as(function ($value) {
                return $value == 'Yes' ? 'Yes' : 'No';
            });
            $show->field('expatirate_type', __('Expatriate Type'))->as(function ($value) {
                return $value == 'Yes' ? 'Yes' : ($value == 'Escort' ? 'Escort' : 'No');
            });
            $show->field('licence_type', __('Licence Type'))->as(function ($value) {
                return $value ?: 'N/A';
            });
            $show->field('requested_departure_time', __('Requested Departure Time'))->as(function ($value) {
                return $value ? Utils::my_date($value) : 'N/A';
            });
            $show->field('requested_return_time', __('Requested Return Time'))->as(function ($value) {
                return $value ? Utils::my_date($value) : 'N/A';
            });
            $show->field('actual_departure_time', __('Actual Departure Time'))->as(function ($value) {
                return $value ? Utils::my_date($value) : 'N/A';
            });
            $show->field('actual_return_time', __('Actual Return Time'))->as(function ($value) {
                return $value ? Utils::my_date($value) : 'N/A';
            });
            $show->field('drivers', __('Drivers'))->as(function ($drivers) {
                if ($drivers && count($drivers)) {
                    $names = [];
                    foreach ($drivers as $driver) {
                        if ($driver->driver && $driver->driver->name) {
                            $names[] = $driver->driver->name;
                        }
                    }
                    return implode(', ', $names);
                }
                return 'N/A';
            });
            $show->field('materials_requested', __('Passenger information'))->as(function ($value) {
                return $value ?: 'N/A';
            });

        }
        if ($record->type == 'Materials') {
            $show->field('materialItems', __('Materials Requested'))->as(function ($materialItems) {
                if ($materialItems->isEmpty()) {
                    return 'No materials requested';
                }
                $html = '<table class="table table-bordered"><thead><tr>
                <th>Description</th>
                <th>Quantity</th>
                <th>Serial Number</th>
                <th>Photo</th>
                </tr></thead><tbody>';
                foreach ($materialItems as $item) {
                    $photo = $item->description ? "<img src='" . asset('storage/' . $item->description) . "' style='width:80px;height:80px;' />" : 'N/A';
                    $html .= "<tr>
                        <td>{$item->type}</td>
                        <td>{$item->quantity}</td>
                        <td>{$item->unit}</td>
                        <td>{$photo}</td>
                </tr>";
                }
                $html .= '</tbody></table>';
                return $html;
            })->unescape();
        }



        // Approval and Security Statuses
        $show->field('hod_status', __('HOD Status'))->as(function ($status) {
            return $status ?: 'N/A';
        });
        $show->field('gm_status', __('GM Status'))->as(function ($status) {
            return $status ?: 'N/A';
        });

        $show->field('hod_comment', __('HOD Comment'))->as(function ($comment) {
            return $comment ?: 'N/A';
        });
        $show->field('gm_comment', __('GM Comment'))->as(function ($comment) {
            return $comment ?: 'N/A';
        });

        //exitRecords table
        $show->field('exitRecords', __('Exit Records'))->as(function ($exitRecords) {
            if ($exitRecords->isEmpty()) {
                return 'No exit records found';
            }
            $html = '<table class="table table-bordered"><thead><tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Status</th>
                <th>Check Out</th>
                <th>Check In</th>
                <th>Remarks</th>
            </tr></thead><tbody>';
            foreach ($exitRecords as $rec) {
                $employee = $rec->employee ? $rec->employee->name : 'N/A';
                $status = $rec->status == 'exit'
                    ? '<span class="label label-danger">Exit</span>'
                    : '<span class="label label-success">Returned</span>';
                $exit_time = $rec->exit_time ? date('d-m-Y H:i:s', strtotime($rec->exit_time)) : 'N/A';
                $return_time = $rec->return_time ? date('d-m-Y H:i:s', strtotime($rec->return_time)) : 'N/A';
                $remarks = $rec->remarks ?: '';
                $html .= "<tr>
                    <td>{$rec->id}</td>
                    <td>{$employee}</td>
                    <td>{$status}</td>
                    <td>{$exit_time}</td>
                    <td>{$return_time}</td>
                    <td>{$remarks}</td>
                </tr>";
            }
            $html .= '</tbody></table>';
            return $html;
        })->unescape();


        return $show;
    }


    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new VehicleRequest());
        $segs = request()->segments();
        $name_of_range = "";
        $id = null;
        $record = null;

        if ($form->isEditing()) {
            $id = request()->segments()[1];
            $record = VehicleRequest::find($id);

            if ($record == null) {
                throw new \Exception("Record not found");
            }
        }

        $applicantCanEdit = false;
        if ($form->isCreating()) {
            $applicantCanEdit = true;
        }


        $u = Admin::user();

        if ($record != null && $u != null) {
            if ($u->id == $record->applicant_id) {
                $applicantCanEdit = true;
            }
            if ($record->gm_status != 'Pending') {
                $applicantCanEdit = false;
            }
        }

        if ($applicantCanEdit) {
            $u = Admin::user();

            $form->hidden('applicant_id', __('Requestor'))->default($u->id);
            $form->display('applicant_name', __('Requestor'))->default($u->name);

            $form->datetime('requested_departure_time', __('Departure Time'))->rules('required');

            if (in_array('vehicle-requests', $segs)) {
                $form->datetime('requested_return_time', __('Arrival Time'))->rules('required');
            }


            if (in_array('vehicle-requests', $segs)) {
                $name_of_range = "Departure and Check In";
                $form->hidden('type', __('Type'))->default('Vehicle');
                $form->select('vehicle_id', __('Vehicle'))->options(Utils::get_dropdown(\App\Models\Vehicle::class, ['registration_number',   'vehicle_type']))->rules('required');
                $form->html('if vehicle is not found, click here to add a new vehicle and refresh the page after adding<br>
            <a href="' . admin_url('vehicles/create') . '" class="btn btn-xs btn-primary" target="_blank">Add New Vehicle</a>');
                $users = \App\Models\User::where('id', '!=', $u->id)->get();

                $form->radio('is_somisy_vehicle', 'Somisy vehicle?')->options(['Yes' => 'Yes', 'No' => 'No'])->rules('required');
                $form->radio('is_camp_resident', 'Camp resident?')->options(['Yes' => 'Yes', 'No' => 'No'])->rules('required');
                $form->radio('expatirate_type', 'Expatriate type')->options(['Yes' => 'Yes', 'No' => 'No', 'Escort' => 'Escort'])->rules('required');
                $form->radio('licence_type', 'Licence type')->options(['Mali' => 'Mali', 'International' => 'International', 'Foreign DL' => 'Foreign DL'])->rules('required');


                $form->divider();

                $form->text('destination', __('Destination'))->rules('required');


                //has many drivers 
                $form->hasMany('drivers', 'Click on "Add New" to add Driver', function (Form\NestedForm $form) use ($users) {
                    $drivers = [];
                    foreach ($users as $user) {
                        $drivers[$user->id] = $user->name . ' - ' . $user->phone_number;
                    }

                    $form->select('driver_id', 'Driver')->options($drivers)->rules('required');
                });

                $form->divider();
                $form->textarea('materials_requested', 'Passenger information (name, contact) seperated by comma.');
            }

            if (in_array('materials-requests', $segs)) {
                $name_of_range  = "Requested time and Check In";
                $form->hidden('type', __('Type'))->default('Materials');
                if ($form->isCreating()) {

                    //many MaterialItem
                    $form->hasMany('materialItems', 'Click on "Add New" to add Material Item', function (Form\NestedForm $form) {
                        $form->text('type', 'Material Description')->rules('required');
                        $form->decimal('quantity', 'Quantity');
                        $form->text('unit', 'Serial Number');
                        $form->image('description', 'Photo');
                    });
                }
            } else if (in_array('leave-requests', $segs)) {
                $name_of_range  = "Leave start time and Leave end time";
                $form->hidden('type', __('Type'))->default('Personnel');
                $form->radio('materials_requested', 'Leave Type')->options(['Annual' => 'Annual', 'Sick' => 'Sick', 'Maternity' => 'Maternity', 'Paternity' => 'Paternity', 'Study' => 'Study', 'Compassionate' => 'Compassionate', 'Special' => 'Special'])->rules('required');
            }

            //if materials-requests, then hide requested_return_time
            if (!in_array('materials-requests', $segs)) {
                $form->textarea('justification', __('Justification'))->rules('required');
            }

            $form->hidden('status', __('Status'))->default('Pending');
            $form->hidden('hod_status', __('Status'))->default('Pending');
            $form->hidden('gm_status', __('Status'))->default('Pending');
            $form->hidden('security_exit_status', __('Status'))->default('Pending');
            $form->hidden('security_return_status', __('Status'))->default('Pending');
        }

        if ($record != null) {

            $form->display('applicant_name', __('Requestor name'))->default($record->applicant->name);

            //if type is vehicle
            if ($record->type == 'Vehicle') {
                $form->display('vehicle_name', __('Vehicle'))->default($record->vehicle->registration_number  . ' - ' . $record->vehicle->vehicle_type);
            } else if ($record->type == 'Materials') {

                if ($applicantCanEdit) {
                    $form->hasMany('materialItems', 'Click on "Add New" to add Material Item Requested For', function (Form\NestedForm $form) {
                        $form->text('type', 'Material Requested')->rules('required');
                        $form->decimal('quantity', 'Material Quantity')->rules('required');
                        $form->text('unit', 'Serial Number')->rules('required');
                        $form->image('description', 'Photo');
                    });
                }
            } else if ($record->type == 'Personnel') {
                $form->display('leave_type', __('Leave type'))->default($record->materials_requested);
            }

            $u = Admin::user();

            if (($u->isRole('gm') || $u->isRole('hod')) && $record != null) {

                $form->display('requested_departure_time', 'Departure Time')
                    ->default(Utils::my_date_1($record->requested_departure_time));

                if ($record->type == 'Vehicle') {
                    $form->display('requested_return_time', 'Arrival Time')
                        ->default(Utils::my_date_1($record->requested_return_time));

                    $form->display('destination', 'Destination')
                        ->default($record->destination ?: 'N/A');
                    //justification
                    $form->display('justification', 'Justification')
                        ->default($record->justification ?: 'N/A');
                }
                //if type
                if ($record->type == 'Vehicle') {
                    $form->display('vehicle_name', 'Vehicle')
                        ->default($record->vehicle->registration_number . ' - ' . $record->vehicle->brand . ' - ' . $record->vehicle->model . ' - ' . $record->vehicle->vehicle_type);
                } else if ($record->type == 'Materials') {
                    //display materialsa as html table
                    $materials = '<table class="table table-bordered"><thead><tr>
                        <th>Material Name</th>
                        <th>Quantity</th>
                        <th>Serial Number</th>
                        <th>Description</th> 
                    </tr></thead><tbody>';
                    foreach ($record->materialItems as $item) {
                        $materials .= "<tr>
                            <td>{$item->type}</td>
                            <td>{$item->quantity}</td>
                            <td>{$item->unit}</td>
                            <td><img src='" . asset('storage/' . $item->description) . "' alt='Material Image' style='width: 100px; height: 100px;'></td>
                        </tr>";
                    }
                    $materials .= '</tbody></table>';
                    $form->html($materials, 'Materials Requested');
                } else if ($record->type == 'Personnel') {
                    $form->display('leave_type', __('Leave type'))->default($record->materials_requested);
                }

                //company_id check if applicant is not null then display company name
                if ($record->applicant && $record->applicant->company) {
                    $form->display('company_id', 'Company')->default($record->applicant->company->name);

                    //department_id
                    if ($record->applicant->department) {
                        $form->display('department_id', 'Department')->default($record->applicant->department->name);
                    } else {
                        $dept = Departmet::find($record->applicant->department_id);
                        if ($dept != null) {
                            $form->display('dempt', 'Department')->default($dept->name);
                        }
                    }
                } else {
                    $form->display('company_id', 'Company')->default('N/A');
                }
                //co_drivers
                $co_drivers = '';
                if ($record->co_drivers) {
                    $co_drivers = json_decode($record->co_drivers, true);
                    if (is_array($co_drivers)) {
                        $co_drivers = implode(', ', $co_drivers);
                    } else {
                        $co_drivers = 'N/A';
                    }
                } else {
                    $co_drivers = 'N/A';
                }
                if ($record->type == 'Vehicle') {
                    //is_somisy_vehicle display Yes or No
                    $form->display('is_somisy_vehicle', 'Is Somisy Vehicle')->default($record->is_somisy_vehicle == 'Yes' ? 'Yes' : 'No');

                    //is_camp_resident
                    $form->display('is_camp_resident', 'Is Camp Resident')->default($record->is_camp_resident == 'Yes' ? 'Yes' : 'No');

                    //expatirate_type
                    $form->display('expatirate_type', 'Expatriate Type')->default($record->expatirate_type == 'Yes' ? 'Yes' : ($record->expatirate_type == 'Escort' ? 'Escort' : 'No'));

                    //licence_type
                    $form->display('licence_type', 'Licence Type')->default($record->licence_type);
                }
            }


            if ($u->isRole('hod')) {
                $form->divider('HOD Decision and Comments');
                $form->radio('hod_status', 'HOD Status')->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected'
                ])->rules('required');
                $form->text('hod_comment', 'HOD Remarks');
            }


            if ($u->isRole('gm') && $record->hod_status == 'Approved') {


                $form->divider('Decision and Comments');

                $form->radio('gm_status', 'GM Status')->options([
                    'Pending' => 'Pending',
                    'Approved' => 'Approved',
                    'Rejected' => 'Rejected'
                ])->rules('required');
                $form->text('gm_comment', 'GM Remarks');

                //close
                $form->radio('is_closed', 'Close Request')->options([
                    'Yes' => 'Yes',
                    'No' => 'No'
                ])->default('No')->rules('required');
            }
        }


        $form->disableCreatingCheck();
        $form->disableViewCheck();
        $form->disableReset();

        return $form;
    }
}
