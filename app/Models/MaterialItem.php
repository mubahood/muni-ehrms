<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialItem extends Model
{

    //table material_items
    protected $table = 'material_items';
    use HasFactory;

    //belongs to vehicle request
    public function vehicleRequest()
    {
        return $this->belongsTo(VehicleRequest::class);
    } 

    protected $fillable = [
        'type',
        'description',
        'unit',
        'quantity',
        'vehicle_request_id',
        'id',
        'created_at',
        'updated_at',
    ];
}
