<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departmet extends Model
{
    use HasFactory;

    //belongs to hod head_of_department_id
    public function hod()
    {
        return $this->belongsTo(User::class, 'head_of_department_id');
    } 
}
