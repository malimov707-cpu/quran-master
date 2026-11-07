<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->travelTo(Carbon::parse('2026-11-07 05:30:00', 'Asia/Tashkent'));
});

/** @return array<string, mixed> */
function localPrayerFixture(string $date = '2026-11-07'): array
{
    return ['region' => 'Toshkent', 'date' => $date, 'times' => ['tong_saharlik' => '05:40', 'quyosh' => '07:01', 'peshin' => '12:10', 'asr' => '15:31', 'shom_iftor' => '17:15', 'hufton' => '18:35']];
}

/** @return array<string, mixed> */
function calculatedPrayerFixture(string $date = '07-11-2026'): array
{
    return ['code' => 200, 'data' => ['date' => ['gregorian' => ['date' => $date]], 'meta' => ['latitude' => 41.300872, 'longitude' => 69.241813, 'timezone' => 'Asia/Tashkent', 'school' => 'HANAFI', 'method' => ['id' => 3]],
        'timings' => ['Fajr' => '05:27', 'Sunrise' => '07:01', 'Dhuhr' => '12:07', 'Asr' => '15:31', 'Maghrib' => '17:12', 'Isha' => '18:41']]];
}

test('prayer page is public and does not block page rendering on external APIs', function () {
    $this->get(route('prayer-times'))->assertOk()->assertSee('Namoz vaqtlari')->assertSee('data-prayer-widget', false)->assertSee('Nurafshon')->assertSee('Nukus');
    $this->get(route('login'))->assertOk()->assertSee(route('prayer-times'));
    Http::assertNothingSent();
});

test('all roles see prayer times on the dashboard and navigation', function (string $role) {
    $this->seed(DatabaseSeeder::class);
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->firstOrFail());
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('data-prayer-widget', false)->assertSee(route('prayer-times'));
    $this->get(route('prayer-times'))->assertOk();
    Http::assertNothingSent();
})->with(['CEO', 'SUPER_ADMIN', 'CENTER_ADMIN', 'HEAD_TEACHER', 'MENTOR', 'STUDENT', 'PARENT']);

test('public data returns local times caches the date and recalculates the next actual prayer', function () {
    Http::fake(['https://islomapi.uz/api/daily*' => Http::response(localPrayerFixture())]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', true)->assertJsonPath('date', '2026-11-07')->assertJsonCount(6, 'times')->assertJsonCount(18, 'cities')
        ->assertJsonPath('source.type', 'calendar')->assertJsonPath('next.name', 'Bomdod')->assertJsonPath('next.remaining_seconds', 600);
    $this->travel(15)->minutes();
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('next.name', 'Peshin')->assertJsonPath('next.time', '12:10');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['region'] === 'Toshkent' && $request['day'] === 7 && $request['month'] === 11);
});

test('daily query uses the Tashkent date around UTC midnight', function () {
    $this->travelTo(Carbon::parse('2026-11-06 20:01:00', 'UTC'));
    Http::fake(['https://islomapi.uz/api/daily*' => Http::response(localPrayerFixture())]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('date', '2026-11-07')->assertJsonPath('timezone', 'Asia/Tashkent')->assertJsonPath('next.name', 'Bomdod');
    Http::assertSent(fn ($request) => $request['day'] === 7);
});

test('after Isha the next prayer comes from tomorrows own schedule', function () {
    $this->travelTo(Carbon::parse('2026-11-07 23:30:00', 'Asia/Tashkent'));
    Http::fake(['https://islomapi.uz/api/daily*' => fn ($request) => Http::response(localPrayerFixture($request['day'] === 8 ? '2026-11-08' : '2026-11-07'))]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('date', '2026-11-07')->assertJsonPath('next.name', 'Bomdod')->assertJsonPath('next.tomorrow', true)->assertJsonPath('next.starts_at', '2026-11-08T05:40:00+05:00');
    Http::assertSentCount(2);
});

test('invalid local calendars use a clearly labelled Hanafi calculated fallback', function (string $failure) {
    $local = localPrayerFixture();
    if ($failure === 'wrong region') {
        $local['region'] = 'Andijon';
    } elseif ($failure === 'stale date') {
        $local['date'] = '2025-11-07';
    } elseif ($failure === 'invalid time') {
        $local['times']['asr'] = '25:99';
    } elseif ($failure === 'unordered times') {
        $local['times']['asr'] = '01:00';
    } elseif ($failure === 'missing time') {
        unset($local['times']['quyosh']);
    }
    Http::fake(['https://islomapi.uz/api/daily*' => $failure === 'network' ? fn () => throw new ConnectionException('Unavailable') : Http::response($local, $failure === 'server error' ? 502 : 200),
        'https://api.aladhan.com/v1/timings/*' => Http::response(calculatedPrayerFixture())]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', true)->assertJsonPath('source.type', 'calculated')->assertJsonPath('source.name', 'AlAdhan · MWL · Hanafiy')->assertJsonPath('times.0.time', '05:27');
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.aladhan.com/') && $request['school'] === 1 && $request['method'] === 3 && $request['timezonestring'] === 'Asia/Tashkent');
})->with(['server error', 'network', 'stale date', 'invalid time', 'unordered times', 'missing time', 'wrong region']);

test('bad or unavailable fallback never becomes a fabricated current schedule', function (string $failure) {
    $fallback = calculatedPrayerFixture();
    if ($failure === 'wrong city') {
        $fallback['data']['meta']['latitude'] = 40;
    } elseif ($failure === 'wrong method') {
        $fallback['data']['meta']['method']['id'] = 2;
    } elseif ($failure === 'wrong date') {
        $fallback['data']['date']['gregorian']['date'] = '06-11-2026';
    } elseif ($failure === 'wrong timezone') {
        $fallback['data']['meta']['timezone'] = 'UTC';
    } elseif ($failure === 'wrong school') {
        $fallback['data']['meta']['school'] = 'STANDARD';
    }
    Http::fake(['https://islomapi.uz/api/daily*' => Http::response([], 502), 'https://api.aladhan.com/v1/timings/*' => Http::response($fallback, $failure === 'server error' ? 503 : 200)]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', false)->assertJsonPath('source', null)->assertJsonPath('next', null)->assertJsonCount(0, 'times');
    Http::assertSentCount(2);
})->with(['wrong date', 'wrong timezone', 'wrong school', 'server error', 'wrong city', 'wrong method']);

test('cities have independent caches and the chosen provider region is respected', function () {
    Http::fake(['https://islomapi.uz/api/daily*' => fn ($request) => Http::response(array_replace(localPrayerFixture(), ['region' => $request['region']]))]);
    foreach (['toshkent', 'andijon'] as $city) {
        $this->getJson(route('api.prayer-times', ['city' => $city]))->assertOk()->assertJsonPath('city.id', $city)->assertJsonPath('available', true);
    }
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['region'] === 'Andijon');
});

test('unknown cities are rejected without making arbitrary external requests', function () {
    $this->getJson(route('api.prayer-times', ['city' => 'https://example.com']))->assertUnprocessable()->assertJsonValidationErrors('city');
    Http::assertNothingSent();
});

test('midnight switches to the new dated cache instead of showing yesterday', function () {
    $this->travelTo(Carbon::parse('2026-11-07 23:59:00', 'Asia/Tashkent'));
    Http::fake(['https://islomapi.uz/api/daily*' => fn ($request) => Http::response(localPrayerFixture($request['day'] === 8 ? '2026-11-08' : '2026-11-07'))]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('date', '2026-11-07');
    $this->travel(2)->minutes();
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('date', '2026-11-08')->assertJsonPath('next.tomorrow', false);
    Http::assertSentCount(2);
});

test('a fallback connection failure returns an unavailable state and can be retried later', function () {
    $offline = true;
    Http::fake(['https://islomapi.uz/api/daily*' => function () use (&$offline) {
        return Http::response($offline ? [] : localPrayerFixture(), $offline ? 502 : 200);
    }, 'https://api.aladhan.com/v1/timings/*' => fn () => throw new ConnectionException('Offline')]);
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', false)->assertJsonCount(0, 'times');
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', false);
    Http::assertSentCount(1);
    $this->travel(61)->seconds();
    $offline = false;
    $this->getJson(route('api.prayer-times'))->assertOk()->assertJsonPath('available', true);
});
