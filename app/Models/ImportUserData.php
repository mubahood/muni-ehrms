<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportUserData extends Model
{
    use HasFactory;

    //table import_user_data
    protected $table = 'import_user_data';

    //belongs to department
    public function department()
    {
        return $this->belongsTo(Departmet::class, 'department_id');
    } 
}
