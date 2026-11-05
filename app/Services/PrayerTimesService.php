<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Namoz vaqtlari hisoblagich — Toshkent koordinatalari uchun
 * astronomik formulalar (Aloha/uslub usulidagi hisob, tashqi API kerak emas).
 */
class PrayerTimesService
{
    private const LATITUDE = 41.2995;   // Toshkent

    private const LONGITUDE = 69.2401;

    private const TIMEZONE_OFFSET = 5;  // UTC+5

    /**
     * Keyingi namoz vaqti va qolgan vaqti.
     *
     * @return array{name: string, time: string, remaining: string}
     */
    public static function nextPrayer(): array
    {
        $times = self::today();

        $now = now('Asia/Tashkent');
        foreach ($times as $prayer) {
            $at = $prayer['carbon'];
            if ($at->gt($now)) {
                return [
                    'name' => $prayer['name'],
                    'time' => $at->format('H:i'),
                    'remaining' => $now->diff($at)->format('%h soat %i daqiqa'),
                ];
            }
        }

        // Barchasi o'tgan — ertangi Bomdod
        $fajr = self::forDate($now->copy()->addDay())[0]['carbon'];

        return [
            'name' => $times[0]['name'],
            'time' => $fajr->format('H:i'),
            'remaining' => $now->diff($fajr)->format('%h soat %i daqiqa'),
        ];
    }

    /**
     * Bugungi namoz va quyosh chiqish vaqtlari (hisobiy).
     *
     * @return list<array{name: string, carbon: CarbonInterface}>
     */
    public static function today(): array
    {
        return self::forDate(now('Asia/Tashkent'));
    }

    /** @return list<array{name: string, carbon: CarbonInterface}> */
    private static function forDate(CarbonInterface $date): array
    {
        $dayOfYear = $date->dayOfYear;
        $declination = 23.45 * sin(deg2rad(360 * (284 + $dayOfYear) / 365));

        // Zenit masofa (Fajr 18°, Isha 17° burchaklari)
        $hourAngle = fn (float $angle): float => rad2deg(acos(
            (sin(deg2rad(-$angle)) - sin(deg2rad(self::LATITUDE)) * sin(deg2rad($declination)))
            / (cos(deg2rad(self::LATITUDE)) * cos(deg2rad($declination)))
        ));

        $dhuhr = 12 + (self::TIMEZONE_OFFSET * 15 - self::LONGITUDE) / 15; // haqiqiy peshin

        $asrShadow = 1 + tan(deg2rad(abs(self::LATITUDE - $declination)));
        $asrAngle = rad2deg(atan(1 / $asrShadow));

        $times = [
            ['name' => 'Bomdod', 'offset' => -$hourAngle(18) / 15],
            ['name' => 'Quyosh', 'offset' => -$hourAngle(0.833) / 15],
            ['name' => 'Peshin', 'offset' => 0],
            ['name' => 'Asr', 'offset' => $hourAngle(-$asrAngle) / 15],
            ['name' => 'Shom', 'offset' => $hourAngle(0.833) / 15],
            ['name' => 'Xufton', 'offset' => $hourAngle(17) / 15],
        ];

        return array_map(fn (array $t) => [
            'name' => $t['name'],
            'carbon' => $date->copy()->startOfDay()->addSeconds((int) round(($dhuhr + $t['offset']) * 3600)),
        ], $times);
    }
}
