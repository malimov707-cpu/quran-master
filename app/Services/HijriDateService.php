<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Hijriy sana — arifmetik hijriy taqvim (umumiy islomiy taqvim formulasi).
 * Eslatma: arifmetik hisob real koordinatli hisobdan 1-2 kun farq qilishi mumkin;
 * rasmiy sanani yangilash kerak bo'lsa, manba qiymati jadvaldan olinadi.
 */
class HijriDateService
{
    /** @var array<int, string> */
    private const MONTHS_UZ = [
        1 => 'Muharram', 2 => 'Safar', 3 => 'Rabi’ul avval', 4 => 'Rabi’us soniy',
        5 => 'Jumodul avval', 6 => 'Jumodus soniy', 7 => 'Rajab', 8 => 'Sha’bon',
        9 => 'Ramazon', 10 => 'Shavvol', 11 => 'Zulqa’da', 12 => 'Zulhijja',
    ];

    public static function today(): string
    {
        $date = Carbon::now(config('app.display_timezone', 'Asia/Tashkent'));
        $midnight = Carbon::createFromFormat('!Y-m-d', $date->format('Y-m-d'), 'UTC');
        $jd = intdiv($midnight->getTimestamp(), 86400) + 2440588;
        $l = $jd - 1948440 + 10632;
        $n = intdiv($l - 1, 10631);
        $l = $l - 10631 * $n + 354;

        $j = intdiv(10985 - $l, 5316) * intdiv(50 * $l, 17719)
            + intdiv($l, 5670) * intdiv(43 * $l, 15238);

        $l = $l - intdiv(30 - $j, 15) * intdiv(17719 * $j, 50)
            - intdiv($j, 16) * intdiv(15238 * $j, 43) + 29;

        $month = intdiv(24 * $l, 709);
        $day = $l - intdiv(709 * $month, 24);
        $year = 30 * $n + $j - 30;

        return sprintf('%d %s %d H', $day, self::MONTHS_UZ[$month] ?? '', $year);
    }
}
