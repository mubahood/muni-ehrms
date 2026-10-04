<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    public const ACADEMIC = 'academic';
    public const ADMINISTRATIVE = 'administrative';

    public const TYPES = [
        self::ACADEMIC => 'Academic (belongs to a faculty)',
        self::ADMINISTRATIVE => 'Administrative',
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'faculty_id',
        'hod_id',
        'description',
        'created_by',
        'is_active',
        'is_demo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * The Head of Department: recommends leave for its staff and sees their attendance.
     */
    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_id');
    }

    /**
     * Academic departments send leave through their faculty's Dean.
     */
    public function isAcademic(): bool
    {
        return $this->type === self::ACADEMIC && (bool) $this->faculty_id;
    }

    public function __toString()
    {
        return (string) $this->name;
    }

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
