<?php

namespace App\Console\Commands;

use App\Models\Quran\AudioEdition;
use App\Models\Quran\AudioFile;
use App\Models\Quran\AudioTimestamp;
use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class ImportQuranAudioAlignment extends Command
{
    protected $signature = 'quran:audio:alignment-import {file : Path to a verified alignment JSON manifest} {--edition= : Audio edition identifier} {--dry-run : Validate without writing database rows}';

    protected $description = 'Import verified millisecond ayah alignment segments for full-surah audio';

    /** @return array{edition:string, surah:number, audio_file_id:number, source_url:string, segments:list<array{ayah:number,start_ms:number,end_ms:number}>} */
    private function loadManifest(string $path): array
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($json)) {
            throw new RuntimeException("Alignment manifest is not readable: {$path}");
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Malformed alignment JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($manifest)) {
            throw new RuntimeException('Alignment manifest must be a JSON object.');
        }

        return $manifest;
    }

    public function handle(): int
    {
        try {
            /** @var array{edition?:string,surah?:int,audio_file_id?:int,source_url?:string,segments?:array<int,array{ayah?:int,start_ms?:int,end_ms?:int}>} $manifest */
            $manifest = $this->loadManifest((string) $this->argument('file'));
            foreach (['edition', 'surah', 'audio_file_id', 'source_url', 'segments'] as $required) {
                if (! array_key_exists($required, $manifest)) {
                    throw new RuntimeException("Alignment manifest is missing '{$required}'.");
                }
            }

            $editionIdentifier = (string) $manifest['edition'];
            if ($this->option('edition') && (string) $this->option('edition') !== $editionIdentifier) {
                throw new RuntimeException('Manifest edition does not match the --edition option.');
            }
            $edition = AudioEdition::where('identifier', $editionIdentifier)->firstOrFail();
            $surah = Surah::where('number', (int) $manifest['surah'])->firstOrFail();
            $audioFile = AudioFile::whereKey((int) $manifest['audio_file_id'])
                ->where('edition_id', $edition->id)->where('kind', 'surah')->where('surah_id', $surah->id)
                ->whereIn('status', ['listed', 'verified'])->firstOrFail();

            $sourceUrl = filter_var((string) $manifest['source_url'], FILTER_VALIDATE_URL);
            if ($sourceUrl === false) {
                throw new RuntimeException('source_url must be an absolute URL.');
            }

            $segments = $manifest['segments'];
            if ($segments === []) {
                throw new RuntimeException('At least one alignment segment is required.');
            }

            $previousEnd = -1;
            $previousAyah = 0;
            $rows = [];
            foreach ($segments as $index => $segment) {
                if (! is_array($segment) || ! isset($segment['ayah'], $segment['start_ms'], $segment['end_ms']) ||
                    filter_var($segment['ayah'], FILTER_VALIDATE_INT) === false ||
                    filter_var($segment['start_ms'], FILTER_VALIDATE_INT) === false ||
                    filter_var($segment['end_ms'], FILTER_VALIDATE_INT) === false) {
                    throw new RuntimeException('Segment '.($index + 1).' must contain integer ayah, start_ms and end_ms values.');
                }

                $ayahNumber = (int) $segment['ayah'];
                $startMs = (int) $segment['start_ms'];
                $endMs = (int) $segment['end_ms'];
                $ayah = Ayah::where('surah_id', $surah->id)->where('number_in_surah', $ayahNumber)->first();
                if (! $ayah) {
                    throw new RuntimeException("Surah {$surah->number} does not contain ayah {$ayahNumber}.");
                }
                if ($startMs < 0 || $endMs <= $startMs) {
                    throw new RuntimeException("Segment {$index}: end_ms must be greater than start_ms and both must be nonnegative.");
                }
                if ($startMs < $previousEnd) {
                    throw new RuntimeException('Segments must be in non-overlapping start_ms order.');
                }
                if ($ayahNumber <= $previousAyah) {
                    throw new RuntimeException("Segment {$index}: ayah numbers must be unique and in ascending order.");
                }

                $rows[] = ['audio_file_id' => $audioFile->id, 'ayah_id' => $ayah->id, 'start_ms' => $startMs, 'end_ms' => $endMs, 'source_url' => $sourceUrl];
                $previousEnd = $endMs;
                $previousAyah = $ayahNumber;
            }

            if ($audioFile->duration_ms !== null && $previousEnd > $audioFile->duration_ms) {
                throw new RuntimeException('Alignment ends after the recorded audio duration.');
            }

            if (! $this->option('dry-run')) {
                DB::transaction(function () use ($audioFile, $rows): void {
                    AudioTimestamp::where('audio_file_id', $audioFile->id)->delete();
                    AudioTimestamp::insert($rows);
                });
                $audioFile->update(['status' => 'verified']);
            }

            $this->info(count($rows).' verified alignment segments '.($this->option('dry-run') ? 'validated' : 'imported')." for {$editionIdentifier}, surah {$surah->number}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
