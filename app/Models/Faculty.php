<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A faculty groups academic departments. Its Dean is a stage in the leave route
 * for academic staff and sees the attendance of every department in it.
 */
class Faculty extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'is_demo' => 'boolean'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function dean()
    {
        return $this->belongsTo(User::class, 'dean_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function __toString()
    {
        return (string) $this->name;
    }
}
