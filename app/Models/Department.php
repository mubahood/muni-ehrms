<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the users in this department.
     */
    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    /**
     * Get the user who created this department.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the general reports for this department.
     */
    public function generalReports()
    {
        return $this->hasMany(GeneralReport::class, 'target_department_id');
    }

    /**
     * Scope: Active departments only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the total number of users in this department.
     */
    public function getUsersCountAttribute()
    {
        return $this->users()->count();
    }
}
