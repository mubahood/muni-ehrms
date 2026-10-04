<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Annual leave allocated to one person for one leave year.
 */
class LeaveEntitlement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'leave_year' => 'integer',
        'days_due' => 'integer',
        'carried_forward' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function total(): int
    {
        return $this->days_due + $this->carried_forward;
    }
}
