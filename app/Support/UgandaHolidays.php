<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Uganda's statutory public holidays that fall on known dates.
 *
 * Good Friday and Easter Monday are computed from Easter. Eid al-Fitr and
 * Eid al-Adha follow the moon and are gazetted each year, so Human Resource
 * adds them under Public holidays once announced.
 */
class UgandaHolidays
{
    private const FIXED = [
        '01-01' => "New Year's Day",
        '01-26' => 'NRM Liberation Day',
        '02-16' => 'Archbishop Janani Luwum Day',
        '03-08' => "International Women's Day",
        '05-01' => 'Labour Day',
        '06-03' => "Martyrs' Day",
        '06-09' => "National Heroes' Day",
        '10-09' => 'Independence Day',
        '12-25' => 'Christmas Day',
        '12-26' => 'Boxing Day',
    ];

    /**
     * @return array<string, string> date (Y-m-d) => name, in date order
     */
    public static function forYear(int $year): array
    {
        $days = [];
        foreach (self::FIXED as $monthDay => $name) {
            $days["{$year}-{$monthDay}"] = $name;
        }

        $easter = self::easterSunday($year);
        $days[$easter->copy()->subDays(2)->toDateString()] = 'Good Friday';
        $days[$easter->copy()->addDay()->toDateString()] = 'Easter Monday';

        ksort($days);

        return $days;
    }

    /**
     * Western (Gregorian) Easter Sunday — the anonymous Gregorian algorithm, so
     * it does not depend on PHP's optional calendar extension.
     */
    public static function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
