<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    //boot of
    protected static function boot()
    {
        parent::boot();

        // Automatically set the user_id when creating a new record
        static::updated(function ($attendanceRecord) {
            $user_total_hours = AttendanceRecord::where('user_id', $attendanceRecord->user_id)
                ->sum('hours');
            $user = User::find($attendanceRecord->user_id);
            if ($user) {
                $user->hours = $user_total_hours;
                $user->save();
            }
        });


        //deleted
        static::deleted(function ($attendanceRecord) {
            $user_total_hours = AttendanceRecord::where('user_id', $attendanceRecord->user_id)
                ->sum('hours');
            $user = User::find($attendanceRecord->user_id);
            if ($user) {
                $user->hours = $user_total_hours;
                $user->save();
            }
        });
    }

    //belongs to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
