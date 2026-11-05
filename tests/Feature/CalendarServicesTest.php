<?php

use App\Services\HijriDateService;
use App\Services\PrayerTimesService;
use Illuminate\Support\Carbon;

test('arithmetic Hijri date has the expected year and uses Tashkent midnight', function (string $utc, string $expected) {
    config(['app.display_timezone' => 'Asia/Tashkent']);
    $this->travelTo(Carbon::parse($utc, 'UTC'));

    expect(HijriDateService::today())->toBe($expected);
})->with([
    ['1970-01-01 00:00:00', '22 Shavvol 1389 H'],
    ['1969-12-31 20:00:00', '22 Shavvol 1389 H'],
    ['1970-01-01 20:00:00', '23 Shavvol 1389 H'],
    ['2000-01-01 00:00:00', '24 Ramazon 1420 H'],
]);

test('calculated prayer times stay ordered within the Tashkent day', function (string $date) {
    $this->travelTo(Carbon::parse($date.' 07:00:00', 'UTC'));

    $times = PrayerTimesService::today();

    expect(array_column($times, 'name'))->toBe(['Bomdod', 'Quyosh', 'Peshin', 'Asr', 'Shom', 'Xufton']);
    foreach ($times as $index => $time) {
        expect($time['carbon']->timezoneName)->toBe('Asia/Tashkent');
        expect($time['carbon']->toDateString())->toBe($date);
        if ($index > 0) {
            expect($time['carbon']->gt($times[$index - 1]['carbon']))->toBeTrue();
        }
    }
})->with(['2026-03-21', '2026-06-21', '2026-11-05', '2026-12-21']);

test('next prayer changes at the local time instead of five hours later', function () {
    $this->travelTo(Carbon::parse('2026-11-05 00:00:00', 'Asia/Tashkent'));
    $fajr = PrayerTimesService::today()[0]['carbon']->copy();
    $this->travelTo($fajr->copy()->subMinute());
    expect(PrayerTimesService::nextPrayer()['name'])->toBe('Bomdod');

    $this->travelTo($fajr->copy()->addMinute());
    expect(PrayerTimesService::nextPrayer()['name'])->toBe('Quyosh');
});

test('late night next prayer uses tomorrows calculated dawn', function () {
    $this->travelTo(Carbon::parse('2026-11-05 23:30:00', 'Asia/Tashkent'));
    $next = PrayerTimesService::nextPrayer();
    $this->travelTo(Carbon::parse('2026-11-06 00:01:00', 'Asia/Tashkent'));
    $tomorrow = PrayerTimesService::today()[0]['carbon'];

    expect($next['name'])->toBe('Bomdod');
    expect($next['time'])->toBe($tomorrow->format('H:i'));
});
