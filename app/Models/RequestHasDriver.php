<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestHasDriver extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_request_id',
        'driver_id',
    ];

    //belongs to vehicle request
    public function vehicle_request()
    {
        return $this->belongsTo(VehicleRequest::class, 'vehicle_request_id');
    } 

    //belongs to driver
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
