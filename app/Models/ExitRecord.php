<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExitRecord extends Model
{
    use HasFactory;

    //boot 
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $vehicle_request = VehicleRequest::find($model->vehicle_request_id);
            if ($vehicle_request == null) {
                throw new \Exception("Vehicle Request not found #".$model->vehicle_request_id);
            }
            $applicant = User::find($vehicle_request->applicant_id);
            if ($applicant == null) {
                throw new \Exception("Applicant not found");
            } 
            $model->employee_id = $applicant->id;
            return $model;
        });

    }
}
