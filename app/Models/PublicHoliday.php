<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A day nobody is expected at work. Adding, moving or removing one rebuilds the
 * attendance of the dates concerned, so past figures stay correct.
 */
class PublicHoliday extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date:Y-m-d'];

    protected static function booted()
    {
        $rebuild = function (PublicHoliday $holiday) {
            $dates = array_filter([
                optional($holiday->date)->toDateString(),
                $holiday->getOriginal('date') ? substr((string) $holiday->getOriginal('date'), 0, 10) : null,
            ]);
            foreach (array_unique($dates) as $date) {
                app(\App\Services\AttendanceEngine::class)->processDay($date);
            }
        };
        static::saved($rebuild);
        static::deleted($rebuild);
    }
}
