<?php

namespace App\Console\Commands;

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\Ayah;
use App\Models\Quran\Reciter;
use App\Models\Quran\Surah;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportQuranAudio extends Command
{
    protected $signature = 'quran:audio:import {--ayah-manifest=} {--surah-manifest=} {--edition=} {--verify : Probe every catalogued file with a bounded range GET} {--limit=0 : Limit probes, zero means all}';

    protected $description = 'Import licensed provider audio inventory, mapping real files to canonical Quran IDs; optionally verify MP3 headers';

    public function handle(): int
    {
        if (Surah::count() !== 114 || Ayah::count() !== 6236) {
            $this->error('Import the validated 114-surah / 6236-ayah canonical text first.');

            return self::FAILURE;
        }
        try {
            $manifests = [];
            $hashes = [];
            foreach (['ayah', 'surah'] as $kind) {
                $path = $this->option("{$kind}-manifest");
                $raw = $path ? file_get_contents($path) : Http::connectTimeout(5)->timeout(90)->get("https://cdn.islamic.network/quran/info/by-{$kind}/info.json")->throw()->body();
                if (! is_string($raw)) {
                    throw new \RuntimeException('Cannot read audio manifest.');
                }
                $manifests[$kind] = $this->inventory($raw);
                $hashes[$kind] = hash('sha256', $raw);
                Storage::disk('local')->put("quran-audio/manifests/{$hashes[$kind]}.json", $raw);
            }
            $surahs = Surah::orderBy('number')->get()->keyBy('number');
            $ayahs = Ayah::orderBy('number_global')->get()->keyBy('number_global');
            $editions = config('quran_audio.editions');
            if ($this->option('edition')) {
                $editions = array_intersect_key($editions, [$this->option('edition') => true]);
                if ($editions === []) {
                    throw new \RuntimeException('Unknown configured audio edition.');
                }
            }
            foreach ($editions as $identifier => $definition) {
                $reciter = Reciter::firstOrCreate(['name' => $definition['name']], ['style' => $definition['style'], 'audio_base_url' => config('quran_audio.cdn'), 'licensed' => true]);
                $edition = AudioEdition::updateOrCreate(['identifier' => $identifier], [
                    'reciter_id' => $reciter->id, 'provider' => config('quran_audio.provider'), 'bitrate' => $definition['bitrate'], 'style' => $definition['style'],
                    'source_url' => config('quran_audio.source_url'), 'license_url' => config('quran_audio.terms_url'), 'license' => config('quran_audio.license'),
                    'version' => config('quran_audio.terms_version'), 'offline_allowed' => true,
                    'manifest_sha256' => hash('sha256', implode('', $hashes)), 'catalog_checked_at' => now(),
                ]);
                $existing = $edition->files()->get()->keyBy(fn (AudioFile $file): string => "{$file->kind}:{$file->number}");
                $rows = [];
                foreach (['ayah' => $ayahs, 'surah' => $surahs] as $kind => $references) {
                    $qualities = $manifests[$kind][$identifier] ?? [];
                    $bitrate = $kind === 'ayah' ? $definition['bitrate'] : (isset($qualities[128]) ? 128 : array_key_first($qualities));
                    $files = $bitrate ? ($qualities[$bitrate] ?? []) : [];
                    foreach ($references as $number => $reference) {
                        $exists = isset($files[$number]);
                        $url = $exists ? config('quran_audio.cdn').($kind === 'ayah' ? '/audio/' : '/audio-surah/')."{$bitrate}/{$identifier}/{$number}.mp3" : '';
                        $old = $existing->get("{$kind}:{$number}");
                        $unchanged = $old && $old->url === $url && $exists;
                        $rows[] = ['edition_id' => $edition->id, 'surah_id' => $kind === 'ayah' ? $reference->surah_id : $reference->id,
                            'ayah_id' => $kind === 'ayah' ? $reference->id : null, 'kind' => $kind, 'number' => $number, 'url' => $url,
                            'status' => $unchanged ? $old->status : ($exists ? 'listed' : 'missing'), 'size_bytes' => $unchanged ? $old->size_bytes : null,
                            'etag' => $unchanged ? $old->etag : null, 'http_status' => $unchanged ? $old->http_status : null, 'verified_at' => $unchanged ? $old->verified_at : null,
                            'created_at' => now(), 'updated_at' => now()];
                    }
                }
                foreach (array_chunk($rows, 250) as $chunk) {
                    AudioFile::upsert($chunk, ['edition_id', 'kind', 'number'], ['surah_id', 'ayah_id', 'url', 'status', 'size_bytes', 'etag', 'http_status', 'verified_at', 'updated_at']);
                }
                $this->info("{$identifier}: ayah inventory ".$edition->files()->where('kind', 'ayah')->where('status', '!=', 'missing')->count().'/6236');
                if ($this->option('verify')) {
                    $this->verify($edition);
                }
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<string, array<int, array<int, bool>>> */
    private function inventory(string $manifest): array
    {
        if (! json_validate($manifest)) {
            throw new \RuntimeException('Malformed audio inventory JSON.');
        }
        $inventory = [];
        $offset = 0;
        $quality = null;
        $edition = null;
        $configured = config('quran_audio.editions');
        $pattern = '/\{"type"\s*:\s*"(directory|file)"\s*,\s*"name"\s*:\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';
        while (preg_match($pattern, $manifest, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $offset = $match[0][1] + strlen($match[0][0]);
            $type = $match[1][0];
            $name = $match[2][0];
            if ($type === 'directory') {
                if (ctype_digit($name)) {
                    $quality = (int) $name;
                    $edition = null;
                } else {
                    $edition = isset($configured[$name]) ? $name : null;
                }
            } elseif ($edition !== null && $quality !== null && preg_match('/^([0-9]+)\.mp3$/', $name, $number)) {
                $inventory[$edition][$quality][(int) $number[1]] = true;
            }
        }
        if ($inventory === []) {
            throw new \RuntimeException('Empty or malformed audio inventory.');
        }

        return $inventory;
    }

    private function verify(AudioEdition $edition): void
    {
        $limit = max(0, (int) $this->option('limit'));
        $checked = 0;
        $query = $edition->files()->where('url', '!=', '')->where(fn ($query) => $query->whereNull('verified_at')->orWhere('verified_at', '<', now()->subDays(7))->orWhere('status', '!=', 'verified'));
        $query->chunkById(96, function ($files) use (&$checked, $limit): bool {
            if ($limit && $checked >= $limit) {
                return false;
            }
            if ($limit) {
                $files = $files->take($limit - $checked);
            }
            $responses = Http::pool(function (Pool $pool) use ($files): array {
                return $files->map(fn (AudioFile $file) => $pool->as((string) $file->id)->connectTimeout(5)->timeout(15)->withHeaders(['Range' => 'bytes=0-1023'])->withOptions(['allow_redirects' => false])->get($file->url))->all();
            }, concurrency: 24);
            foreach ($files as $file) {
                $response = $responses[(string) $file->id];
                $valid = false;
                $size = null;
                if (! $response instanceof Throwable) {
                    $body = $response->body();
                    $hasMp3Header = str_starts_with($body, 'ID3') || (strlen($body) >= 2 && ord($body[0]) === 255 && (ord($body[1]) & 224) === 224);
                    if (preg_match('/\/(\d+)$/', $response->header('Content-Range'), $match)) {
                        $size = (int) $match[1];
                    } else {
                        $size = (int) $response->header('Content-Length');
                    }
                    $valid = in_array($response->status(), [200, 206], true) && str_contains($response->header('Content-Type'), 'audio/') && $size > 1024 && $hasMp3Header;
                }
                $file->update(['status' => $valid ? 'verified' : 'unavailable', 'size_bytes' => $size, 'http_status' => $response instanceof Throwable ? null : $response->status(),
                    'etag' => $response instanceof Throwable ? null : $response->header('ETag'), 'verified_at' => now()]);
                $checked++;
            }
            $this->line("{$checked} files probed");

            return true;
        });
    }
}
