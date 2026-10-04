<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One person's attendance for one day. Built by App\Services\AttendanceEngine
 * from the clock-ins; Human Resource may correct it, after which the engine
 * leaves it alone.
 */
class AttendanceRecord extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'hours' => 'float',
        'late_minutes' => 'integer',
        'punch_count' => 'integer',
        'is_working_day' => 'boolean',
        'is_half_day' => 'boolean',
        'is_manual' => 'boolean',
        'corrected_at' => 'datetime',
    ];

    public const STATUSES = [
        'Present' => 'Present',
        'Absent' => 'Absent',
        'On Leave' => 'On leave',
    ];

    protected static function boot()
    {
        parent::boot();

        // users.hours keeps each person's total; refresh it when a record is
        // edited or removed outside the engine (the engine refreshes in bulk).
        $refresh = function (AttendanceRecord $record) {
            DB::table('users')->where('id', $record->user_id)->update([
                'hours' => DB::raw('(SELECT COALESCE(ROUND(SUM(hours)), 0) FROM attendance_records WHERE attendance_records.user_id = users.id)'),
            ]);
        };
        static::updated($refresh);
        static::deleted($refresh);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    public function isLate(): bool
    {
        return $this->is_late === 'Yes';
    }

    /** "On time", "Late", "Absent", "On leave" — what people call the day. */
    public function statusLabel(): string
    {
        if ($this->status === 'Present') {
            return $this->isLate() ? 'Late' : 'On time';
        }

        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    /** CSS modifier shared by the screens and the PDFs. */
    public function statusKey(): string
    {
        if ($this->status === 'Present') {
            return $this->isLate() ? 'late' : 'present';
        }

        return $this->status === 'On Leave' ? 'leave' : 'absent';
    }
}
