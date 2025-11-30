<?php

namespace App\Models;

use Dflydev\DotAccessData\Util;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VehicleRequest extends Model
{
    use HasFactory;

    //belongs to applicant
    public static function send_mails($model)
    {
        $applicant = User::find($model->applicant_id);
        if ($applicant == null) {
            throw new \Exception("Applicant not found");
        }
        $department = Departmet::find($applicant->department_id);
        if ($department == null) {
            $applicant->department_id = 1;
            $applicant->save();
            $department = Departmet::find($applicant->department_id);
            if ($department == null) {
                throw new \Exception("Default Department not found");
            }
        }
        $head_of_department = User::find($department->head_of_department_id);
        if ($head_of_department == null) {
            throw new \Exception("Head of Department not found");
        }

        //mail to head of department
        if ($model->hod_status == 'Pending' && $model->mail_sent_to_hod != 'Yes') {
            $review_url = admin_url('vehicle-requests/' . $model->id . '/edit');
            $mail_body = <<<HTML
            <p>Dear {$head_of_department->name},</p>
            <p>A new {$model->type} request #{$model->id} has been submitted by {$applicant->name}.</p>
            <p>To review the request, please click the link below:</p>
            <p><a href="{$review_url}">Review Request #{$model->id}</a></p>
            <p>Thank you.</p>
            HTML;
            $title = $model->type . ' Request #' . $model->id . ' - ' . $applicant->name . ' HOD Review.';
            try {
                $day = date('Y-m-d');
                $data['body'] = $mail_body;
                $data['data'] = $mail_body;
                $data['name'] = $head_of_department->name;
                $data['email'] = $head_of_department->email;
                $data['subject'] = 'HOD Review - ' . env('APP_NAME') . ' - ' . $day . ".";
                $data['title'] = $title;
                Utils::mail_sender($data);
                $sql = "UPDATE vehicle_requests SET mail_sent_to_hod = 'Yes' WHERE id = {$model->id}";
                DB::update($sql);
            } catch (\Throwable $th) {
                // throw $th;
            }
        }

        //mail to general manager
        if ($model->hod_status == 'Approved' && $model->gm_status == 'Pending' && $model->mail_sent_to_gm != 'Yes') {
            $gm = Utils::get_general_managers();
            $gm_mail = null;
            if ($gm != null) {
                $gm_mail = $gm->email;
            }
            $test_mails = Utils::get_test_mails();
            if (in_array($gm_mail, $test_mails)) {
                $gm_mail = 'mubahood360@gmail.com';
            }
            if ($gm_mail == null) {
                throw new \Exception("General Manager not found");
            }
            $review_url = admin_url('vehicle-requests/' . $model->id . '/edit');

            $mail_body = <<<HTML
            <p>Dear General Manager,</p>
            <p>The {$model->type} request #{$model->id} by {$applicant->name} has been approved by the Head of Department.</p>
            <p>Click the link below to review the request as the General Manager:</p>
            <p><a href="{$review_url}">Review Request #{$model->id}</a></p>
            <p>Thank you.</p>
            HTML;
            $title = $model->type . ' Request #' . $model->id . ' - ' . $applicant->name . ' GM Review.';
            try {
                $day = date('Y-m-d');
                $data['body'] = $mail_body;
                $data['data'] = $mail_body;
                $data['name'] = 'General Manager';
                $data['email'] = $gm_mail;
                $data['subject'] = 'GM Review - ' . env('APP_NAME') . ' - ' . $day . ".";
                $data['title'] = $title;
                Utils::mail_sender($data);
                $sql = "UPDATE vehicle_requests SET mail_sent_to_gm = 'Yes' WHERE id = {$model->id}";
                DB::update($sql);
            } catch (\Throwable $th) {
                // throw $th;
            }
        }

        //mail to applicant on GM approval mail_sent_to_applicant_on_hod_approval 
        if ($model->gm_status == 'Approved' && $model->mail_sent_to_applicant_on_gm_approval != 'Yes') {
            $applicant_mail = $applicant->email;
            $test_mails = Utils::get_test_mails();
            if (in_array($applicant_mail, $test_mails)) {
                $applicant_mail = 'mubahood360@gmail.com';
            }
            if ($applicant_mail == null) {
                throw new \Exception("Applicant email not found");
            }

            $dowload_url = url('print-gatepass?gatepass_id=' . $model->id);
            //mail to applicant mail_sent_to_applicant_on_hod_approval
            $mail_body = <<<HTML
            <p>Dear {$applicant->name},</p>
            <p>Your {$model->type} request #{$model->id} has been approved by the General Manager.</p>
            <p>You can download the request document using the link below:</p>
            <p><a href="{$dowload_url}">Download Request #{$model->id}</a></p>
            <p>Thank you.</p>
            HTML;
            $title = $model->type . ' Request #' . $model->id . ' - ' . $applicant->name . ' GM Approval.';
            try {
                $day = date('Y-m-d');
                $data['body'] = $mail_body;
                $data['data'] = $mail_body;
                $data['name'] = $applicant->name;
                $data['email'] = $applicant_mail;
                $data['subject'] = 'GM Approval - ' . env('APP_NAME') . ' - ' . $day . ".";
                $data['title'] = $title;
                Utils::mail_sender($data);
                $sql = "UPDATE vehicle_requests SET mail_sent_to_applicant_on_gm_approval = 'Yes' WHERE id = {$model->id}";
                DB::update($sql);
            } catch (\Throwable $th) {
                // throw $th;
            }
        }


        //mail sent to applicant on hod rejection => mail_sent_to_applicant_on_security_exit_approval
        if ($model->hod_status == 'Rejected' && $model->mail_sent_to_applicant_on_hod_approval != 'Yes') {
            $applicant_mail = $applicant->email;
            $test_mails = Utils::get_test_mails();
            if (in_array($applicant_mail, $test_mails)) {
                $applicant_mail = 'mubahood360@gmail.com';
            }
            if ($applicant_mail == null) {
                throw new \Exception("Applicant email not found");
            }
            $detils_url = admin_url('vehicle-requests/' . $model->id);
            $mail_body = <<<HTML
            <p>Dear {$applicant->name},</p>
            <p>Your {$model->type} request #{$model->id} has been rejected by the Head of Department.</p>
            <p>To view the details of the rejection, please click the link below:</p>
            <p><a href="{$detils_url}">View Request #{$model->id}</a></p> 
            <p>Reason: {$model->hod_comment}</p>
            <p>Thank you.</p>
            HTML;
            $title = $model->type . ' Request #' . $model->id . ' - ' . $applicant->name . ' HOD Rejection.';
            try {
                $day = date('Y-m-d');
                $data['body'] = $mail_body;
                $data['data'] = $mail_body;
                $data['name'] = $applicant->name;
                $data['email'] = $applicant_mail;
                $data['subject'] = 'HOD Rejection - ' . env('APP_NAME') . ' - ' . $day . ".";
                $data['title'] = $title;
                Utils::mail_sender($data);
                $sql = "UPDATE vehicle_requests SET mail_sent_to_applicant_on_hod_approval = 'Yes' WHERE id = {$model->id}";
                DB::update($sql);
            } catch (\Throwable $th) {
                // throw $th;
            }
        }

        //mail sent to applicant on gm rejection => mail_sent_to_applicant_on_security_exit_approval
        if ($model->gm_status == 'Rejected' && $model->mail_sent_to_applicant_on_security_exit_approval != 'Yes') {
            $applicant_mail = $applicant->email;
            $test_mails = Utils::get_test_mails();
            if (in_array($applicant_mail, $test_mails)) {
                $applicant_mail = 'mubahood360@gmail.com';
            }
            if ($applicant_mail == null) {
                throw new \Exception("Applicant email not found");
            }
            $detils_url = admin_url('vehicle-requests/' . $model->id);
            $mail_body = <<<HTML
            <p>Dear {$applicant->name},</p>
            <p>Your {$model->type} request #{$model->id} has been rejected by the General Manager.</p>
            <p>To view the details of the rejection, please click the link below:</p>
            <p><a href="{$detils_url}">View Request #{$model->id}</a></p>
            <p>Reason: {$model->gm_comment}</p>
            <p>Thank you.</p>
            HTML;
            $title = $model->type . ' Request #' . $model->id . ' - ' . $applicant->name . ' GM Rejection.';
            try {
                $day = date('Y-m-d');
                $data['body'] = $mail_body;
                $data['data'] = $mail_body;
                $data['name'] = $applicant->name;
                $data['email'] = $applicant_mail;
                $data['subject'] = 'GM Rejection - ' . env('APP_NAME') . ' - ' . $day . ".";
                $data['title'] = $title;
                Utils::mail_sender($data);
                $sql = "UPDATE vehicle_requests SET mail_sent_to_applicant_on_security_exit_approval = 'Yes' WHERE id = {$model->id}";
                DB::update($sql);
            } catch (\Throwable $th) {
                // throw $th;
            }
        }
    }


    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    //belongs to vehicle
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    //has many exitRecords
    public function exitRecords()
    {
        return $this->hasMany(ExitRecord::class, 'vehicle_request_id');
    }

    //boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $applicant = User::find($model->applicant_id);
            if ($applicant == null) {
                throw new \Exception("Applicant not found");
            }
            $department = Departmet::find($applicant->department_id);
            if ($department == null) {
                throw new \Exception("Department not found");
            }

            $model = self::do_prepare($model);
            $model->department_id = $department->id;
            return $model;
        });

        static::updating(function ($model) {
            $model = self::do_prepare($model);

            $applicant = User::find($model->applicant_id);
            if ($applicant == null) {
                throw new \Exception("Applicant not found");
            }
            $department = Departmet::find($applicant->department_id);
            if ($department == null) {
                throw new \Exception("Department not found");
            }
            $model->department_id = $department->id;

            return $model;
        });

        //created
        static::created(function ($model) {
            self::send_mails($model);
        });

        //updated
        static::updated(function ($model) {
            self::send_mails($model);
        });
    }

    public static function do_prepare($model)
    {
        //IF type is Vehicle Request
        if ($model->type == 'Vehicle') {
            $vehicle = Vehicle::find($model->vehicle_id);
            if ($vehicle == null) {
                throw new \Exception("Vehicle not found");
            }
        }
        $applicant = User::find($model->applicant_id);
        if ($applicant == null) {
            throw new \Exception("Applicant not found");
        }

        if ($model->hod_status != 'Approved') {
            $model->gm_status = 'Pending';
            $model->security_exit_status = 'Pending';
            $model->security_return_status = 'Pending';
        }

        if ($model->gm_status != 'Approved') {
            $model->security_exit_status = 'Pending';
            $model->security_return_status = 'Pending';
        }

        if ($model->security_exit_status != 'Approved') {
            $model->security_return_status = 'Pending';
        }

        //status

        //actual_return_time NOT PENDDING
        if ($model->actual_return_time != null) {
            $model->status = 'Completed';
        }



        return $model;
    }

    //has many material items
    public function materialItems()
    {
        return $this->hasMany(MaterialItem::class);
    }

    //get title
    public function getTitle()
    {
        if ($this->type == 'Vehicle') {
            if ($this->vehicle) {
                return $this->vehicle->registration_number .   ' - ' . $this->vehicle->vehicle_type;
            } else {
                return 'N/A';
            }
        } else if ($this->type == 'Materials') {
            $materials = "";
            foreach ($this->materialItems as $index => $materialItem) {
                $materials .= $materialItem->type . ' - ' . $materialItem->quantity . ' ' . $materialItem->unit;
                if ($index < $this->materialItems->count() - 1) {
                    $materials .= ', ';
                }
            }
            return $materials;
        } else if ($this->type == 'Fuel') {
            return 'Fuel Request';
        } else {
            return 'N/A';
        }
    }

    //has many RequestHasDriver 
    public function drivers()
    {
        return $this->hasMany(RequestHasDriver::class, 'vehicle_request_id');
    }

    //belongs to department
    public function department()
    {
        return $this->belongsTo(Departmet::class, 'department_id');
    }

    public function get_gm() {
        $gm = Utils::get_general_managers();
        return $gm;
    }
}
