<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A leave application, laid out like the University's leave form:
 * Section I is the request, Section II Human Resource's computation, and
 * Section III the University Secretary's decision. The approval trail is in
 * leave_actions; the rules and the route live in App\Services\Leave.
 */
class Leave extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'return_date' => 'date:Y-m-d',
        'recall_date' => 'date:Y-m-d',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'recalled_at' => 'datetime',
        'days' => 'integer',
        'leave_year' => 'integer',
        'days_restored' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Types — the nine on the University's form, then two HR may record
    |--------------------------------------------------------------------------
    */

    public const ANNUAL = 'annual';

    public const TYPES = [
        'annual' => 'Annual leave',
        'sick' => 'Sick leave',
        'study' => 'Study leave',
        'maternity' => 'Maternity leave',
        'paternity' => 'Paternity leave',
        'compassionate' => 'Compassionate leave',
        'unpaid' => 'Unpaid leave',
        'sabbatical' => 'Sabbatical leave',
        'special' => 'Special leave of absence',
        'official' => 'Official duty / travel',
        'other' => 'Other',
    ];

    /** Types an employee can apply for; the rest are recorded by HR. */
    public const FORM_TYPES = [
        'annual', 'sick', 'study', 'maternity', 'paternity', 'compassionate', 'unpaid', 'sabbatical', 'special',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status and stage
    |--------------------------------------------------------------------------
    */

    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const WITHDRAWN = 'withdrawn';
    public const CANCELLED = 'cancelled';
    public const RECALLED = 'recalled';

    public const STATUSES = [
        self::PENDING => 'Awaiting approval',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Not approved',
        self::WITHDRAWN => 'Withdrawn',
        self::CANCELLED => 'Cancelled',
        self::RECALLED => 'Recalled',
    ];

    public const STAGE_HOD = 'hod';
    public const STAGE_DEAN = 'dean';
    public const STAGE_HR = 'hr';
    public const STAGE_US = 'us';

    public const STAGES = [
        self::STAGE_HOD => 'Head of Department',
        self::STAGE_DEAN => 'Faculty Dean',
        self::STAGE_HR => 'Human Resource Office',
        self::STAGE_US => 'University Secretary',
    ];

    public const SOURCE_APPLICATION = 'application';
    public const SOURCE_HR = 'hr_entry';

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actingUser()
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function recalledBy()
    {
        return $this->belongsTo(User::class, 'recalled_by');
    }

    public function actions()
    {
        return $this->hasMany(LeaveAction::class)->orderBy('created_at')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Leave that keeps the person away from work on $date: approved leave
     * covering it, or recalled leave up to the day before they resumed.
     * Requests that are pending, declined, withdrawn or cancelled never count.
     */
    public function scopeInForceOn(Builder $query, $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->where('status', self::APPROVED)
                    ->orWhere(function (Builder $r) use ($date) {
                        $r->where('status', self::RECALLED)->where('recall_date', '>', $date);
                    });
            });
    }

    /** Leave that blocks new requests for the same dates. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::APPROVED, self::RECALLED]);
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function typeLabel(): string
    {
        return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function stageLabel(): ?string
    {
        return $this->stage ? (self::STAGES[$this->stage] ?? $this->stage) : null;
    }

    /** @return string[] the stages of this request's approval route, in order */
    public function routeList(): array
    {
        return array_values(array_filter(explode(',', (string) $this->route)));
    }

    public function isAnnual(): bool
    {
        return $this->leave_type === self::ANNUAL;
    }

    public function hasStarted(): bool
    {
        return $this->start_date !== null && $this->start_date->lte(today());
    }

    /** Working days actually used: all of them, unless recalled part-way. */
    public function daysTaken(): int
    {
        return max(0, (int) $this->days - (int) $this->days_restored);
    }
}
