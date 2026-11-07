<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UzbekPrayerTimesService
{
    /** @return list<array{id: string, name: string}> */
    public function cities(): array
    {
        $cities = [];
        foreach (config('prayer_times.cities') as $id => $city) {
            $cities[] = ['id' => $id, 'name' => $city['name']];
        }

        return $cities;
    }

    /** @return array<string, mixed> */
    public function overview(string $city): array
    {
        $now = now(config('prayer_times.timezone'));
        $day = $this->day($city, $now);
        $next = null;
        if ($day['available']) {
            foreach ($day['times'] as $time) {
                if ($time['key'] !== 'sunrise' && $now->lt($time['starts_at'])) {
                    $next = $time + ['tomorrow' => false, 'source_type' => $day['source']['type']];
                    break;
                }
            }
            if ($next === null) {
                $tomorrow = $this->day($city, $now->copy()->addDay());
                if ($tomorrow['available']) {
                    $next = $tomorrow['times'][0] + ['tomorrow' => true, 'source_type' => $tomorrow['source']['type']];
                }
            }
        }
        if ($next) {
            $next['remaining_seconds'] = max(0, (int) $now->diffInSeconds($next['starts_at']));
        }

        return $day + ['city' => ['id' => $city, 'name' => config("prayer_times.cities.{$city}.name")], 'cities' => $this->cities(), 'timezone' => config('prayer_times.timezone'), 'server_now' => $now->toIso8601String(), 'next' => $next];
    }

    /** @return array<string, mixed> */
    private function day(string $city, CarbonInterface $date): array
    {
        $key = 'prayer-times:v1:'.$city.':'.$date->toDateString();
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $location = config("prayer_times.cities.{$city}");
        $result = null;
        if ($location['region'] !== null && ! Cache::has('prayer-times:islom-unavailable')) {
            try {
                $response = Http::acceptJson()->connectTimeout(2)->timeout(3)->get('https://islomapi.uz/api/daily', ['region' => $location['region'], 'month' => $date->month, 'day' => $date->day]);
                $json = $response->successful() ? $response->json() : null;
                if (is_array($json) && ($json['date'] ?? null) === $date->toDateString() && ($json['region'] ?? null) === $location['region']) {
                    $values = array_map(fn (string $field): mixed => $json['times'][$field] ?? null, ['tong_saharlik', 'quyosh', 'peshin', 'asr', 'shom_iftor', 'hufton']);
                    $result = $this->normalize($date, $values, 'calendar', 'Islom.uz (IslomAPI)', 'https://islom.uz/taqvim');
                }
                if ($result === null) {
                    Cache::put('prayer-times:islom-unavailable', true, 60);
                }
            } catch (ConnectionException) {
                Cache::put('prayer-times:islom-unavailable', true, 60);
            }
        }
        if ($result === null) {
            try {
                $response = Http::acceptJson()->connectTimeout(2)->timeout(5)->get('https://api.aladhan.com/v1/timings/'.$date->format('d-m-Y'), [
                    'latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'method' => 3, 'school' => 1, 'timezonestring' => config('prayer_times.timezone'),
                ]);
                $json = $response->successful() ? $response->json('data') : null;
                if (
                    is_array($json)
                    && ($json['date']['gregorian']['date'] ?? null) === $date->format('d-m-Y')
                    && ($json['meta']['timezone'] ?? null) === config('prayer_times.timezone')
                    && ($json['meta']['school'] ?? null) === 'HANAFI'
                    && ($json['meta']['method']['id'] ?? null) === 3
                    && is_numeric($json['meta']['latitude'] ?? null)
                    && is_numeric($json['meta']['longitude'] ?? null)
                    && abs((float) $json['meta']['latitude'] - $location['latitude']) < 0.01
                    && abs((float) $json['meta']['longitude'] - $location['longitude']) < 0.01
                ) {
                    $values = array_map(fn (string $field): mixed => $json['timings'][$field] ?? null, ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha']);
                    $result = $this->normalize($date, $values, 'calculated', 'AlAdhan · MWL · Hanafiy', 'https://aladhan.com/prayer-times-api');
                }
            } catch (ConnectionException) {
                $result = null;
            }
        }
        $result ??= ['available' => false, 'date' => $date->toDateString(), 'times' => [], 'source' => null];
        Cache::put($key, $result, $result['available'] ? 3600 : 30);

        return $result;
    }

    /**
     * @param  list<mixed>  $values
     * @return array<string, mixed>|null
     */
    private function normalize(CarbonInterface $date, array $values, string $type, string $source, string $url): ?array
    {
        $times = [];
        $keys = ['fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha'];
        $names = ['Bomdod', 'Quyosh', 'Peshin', 'Asr', 'Shom', 'Xufton'];
        $previous = '';
        foreach ($values as $index => $time) {
            if (! is_string($time) || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D', $time) || $time <= $previous) {
                return null;
            }
            $previous = $time;
            $at = $date->copy()->startOfDay()->setTimeFromTimeString($time);
            $times[] = ['key' => $keys[$index], 'name' => $names[$index], 'time' => $time, 'starts_at' => $at->toIso8601String()];
        }

        return ['available' => true, 'date' => $date->toDateString(), 'times' => $times, 'source' => ['type' => $type, 'name' => $source, 'url' => $url]];
    }
}
