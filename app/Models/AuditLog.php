<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'auth.login' => 'Signed in',
        'auth.failed' => 'Failed sign-in',
        'auth.logout' => 'Signed out',
        'attendance.corrected' => 'Attendance corrected',
        'attendance.restored' => 'Attendance restored',
        'attendance.rebuilt' => 'Attendance rebuilt',
        'leave.submitted' => 'Leave applied for',
        'leave.recommended' => 'Leave recommended',
        'leave.verified' => 'Leave verified',
        'leave.approved' => 'Leave approved',
        'leave.rejected' => 'Leave not approved',
        'leave.withdrawn' => 'Leave withdrawn',
        'leave.cancelled' => 'Leave cancelled',
        'leave.recalled' => 'Recalled from leave',
        'leave.recorded' => 'Leave recorded',
        'leave.planning' => 'Leave allocation changed',
        'report.downloaded' => 'Report downloaded',
        'settings.updated' => 'Settings changed',
        'holiday.changed' => 'Public holiday changed',
    ];

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
