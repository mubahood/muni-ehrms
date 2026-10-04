<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step in a leave request's trail.
 */
class LeaveAction extends Model
{
    public const UPDATED_AT = null;

    public const SUBMITTED = 'submitted';
    public const RECOMMENDED = 'recommended';
    public const VERIFIED = 'verified';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const SKIPPED = 'skipped';
    public const WITHDRAWN = 'withdrawn';
    public const CANCELLED = 'cancelled';
    public const RECALLED = 'recalled';
    public const RECORDED = 'recorded';

    public const LABELS = [
        self::SUBMITTED => 'Submitted',
        self::RECOMMENDED => 'Recommended',
        self::VERIFIED => 'Verified',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Not approved',
        self::SKIPPED => 'Passed on',
        self::WITHDRAWN => 'Withdrawn',
        self::CANCELLED => 'Cancelled',
        self::RECALLED => 'Recalled',
        self::RECORDED => 'Recorded by HR',
    ];

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function leave()
    {
        return $this->belongsTo(Leave::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? ucfirst((string) $this->action);
    }
}
