<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The single row of system-wide settings: who the institution is (for the
 * letterhead) and the rules attendance and leave are worked out by.
 */
class SystemConfiguration extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'full_day_hours' => 'float',
        'repeat_capture_minutes' => 'integer',
        'leave_year_start_month' => 'integer',
        'annual_leave_days' => 'integer',
        'demo_logins' => 'boolean',
    ];

    private static ?self $cached = null;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (self::query()->exists()) {
                throw new \Exception('Only one system configuration can exist at a time.');
            }
            if (empty($model->start_date)) {
                $model->start_date = now()->toDateString();
            }
        });

        static::saved(function () {
            self::$cached = null;
        });
    }

    /**
     * The configuration row, created with the defaults if it does not exist yet.
     * Cached for the request; saving clears the cache.
     */
    public static function current(): self
    {
        if (self::$cached === null) {
            self::$cached = self::query()->first() ?: self::query()->create([
                'company_name' => 'Muni University',
                'company_address' => 'P.O. Box 725 Arua, Uganda',
                'company_phone' => '+256 476 420312/3/4',
                'company_email' => 'info@muni.ac.ug',
                'company_logo' => '',
                'late_time' => '08:30:00',
            ]);
        }

        return self::$cached;
    }

    public static function forgetCurrent(): void
    {
        self::$cached = null;
    }

    /**
     * ISO-8601 weekday numbers on which staff are expected (Monday = 1 … Sunday = 7).
     *
     * @return int[]
     */
    public function workingWeekdays(): array
    {
        $days = array_filter(array_map('intval', explode(',', (string) $this->working_days)));

        return $days ?: [1, 2, 3, 4, 5];
    }

    public function defaultLateTime(): string
    {
        return $this->late_time ?: '08:30:00';
    }

    /**
     * Leave years are named by the calendar year they start in. With the default
     * July start, 1 Jul 2026 – 30 Jun 2027 is leave year 2026 ("2026/27").
     */
    public function leaveYearOf($date): int
    {
        $date = Carbon::parse($date);
        $start = $this->leave_year_start_month ?: 1;

        return $date->month >= $start ? $date->year : $date->year - 1;
    }

    /**
     * @return Carbon[] [first day, last day]
     */
    public function leaveYearBounds(int $year): array
    {
        $start = Carbon::create($year, $this->leave_year_start_month ?: 1, 1)->startOfDay();

        return [$start, $start->copy()->addYear()->subDay()];
    }

    public function leaveYearLabel(int $year): string
    {
        if (($this->leave_year_start_month ?: 1) === 1) {
            return (string) $year;
        }

        return $year . '/' . substr((string) ($year + 1), -2);
    }

    /**
     * One-line contact details for letterheads.
     */
    public function contactLine(): string
    {
        $parts = [];
        if ($this->company_phone) {
            $parts[] = 'Tel: ' . $this->company_phone . ($this->company_fax ? '; Fax: ' . $this->company_fax : '');
        }
        if ($this->company_email) {
            $parts[] = 'Email: ' . $this->company_email;
        }
        if ($this->company_website) {
            $parts[] = $this->company_website;
        }

        return implode(' · ', $parts);
    }
}
