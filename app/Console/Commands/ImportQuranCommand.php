<?php

namespace App\Console\Commands;

use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ImportQuranCommand extends Command
{
    protected $signature = 'quran:import {--file= : Local Uthmani JSON} {--metadata= : Local Tanzil XML metadata} {--checksum= : Expected SHA-256 of the JSON file}';

    protected $description = 'Import complete Tanzil Uthmani text and authentic juz/page metadata';

    private const SOURCE_URL = 'https://quran-json.risanb.com/text/uthmani/quran.json';

    private const METADATA_URL = 'https://tanzil.net/res/text/metadata/quran-data.xml';

    public function handle(): int
    {
        try {
            $raw = $this->readSource($this->option('file'), self::SOURCE_URL);
            $metadataRaw = $this->readSource($this->option('metadata'), self::METADATA_URL);
            $checksum = hash('sha256', $raw);
            if ($this->option('checksum') !== null && ! hash_equals($this->option('checksum'), $checksum)) {
                throw new RuntimeException('SHA-256 mos kelmadi. Import bekor qilindi.');
            }
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            $xml = @simplexml_load_string($metadataRaw, SimpleXMLElement::class, LIBXML_NONET);
            if (! is_array($data) || count($data) !== 114 || $xml === false || count($xml->suras->sura) !== 114) {
                throw new RuntimeException('114 sura va Tanzil metama’lumotlari talab qilinadi.');
            }
            $chapters = [];
            $total = 0;
            foreach ($xml->suras->sura as $index => $chapter) {
                $number = (int) $chapter['index'];
                $text = $data[$number - 1] ?? null;
                $count = (int) $chapter['ayas'];
                if ($number !== count($chapters) + 1 || ($text['id'] ?? null) !== $number || ! isset($text['verses']) || count($text['verses']) !== $count) {
                    throw new RuntimeException('Sura yoki oyatlar soni mos kelmadi.');
                }
                foreach ($text['verses'] as $position => $verse) {
                    if (($verse['id'] ?? null) !== $position + 1 || ! is_string($verse['text'] ?? null) || trim($verse['text']) === '') {
                        throw new RuntimeException('Oyat tartibi yoki matni noto‘g‘ri.');
                    }
                }
                $chapters[$number] = ['meta' => $chapter, 'verses' => $text['verses'], 'offset' => $total];
                $total += $count;
            }
            if ($total !== 6236) {
                throw new RuntimeException('6236 oyat talab qilinadi.');
            }
            $juzStarts = $this->partitions($xml->juzs->juz, $chapters, 30);
            $pageStarts = $this->partitions($xml->pages->page, $chapters, 604);
            $quarterStarts = $this->partitions($xml->hizbs->quarter, $chapters, 240);
            $snapshots = ['quran-sources/'.$checksum.'.json' => $raw, 'quran-sources/'.hash('sha256', $metadataRaw).'.xml' => $metadataRaw];
            foreach ($snapshots as $path => $contents) {
                if (! Storage::disk('local')->put($path, $contents)) {
                    throw new RuntimeException('Manba nusxasini saqlab bo‘lmadi.');
                }
            }
            DB::transaction(function () use ($chapters, $juzStarts, $pageStarts, $quarterStarts, $checksum): void {
                $global = 0;
                $juz = $page = $quarter = 1;
                foreach ($chapters as $number => $chapter) {
                    $meta = $chapter['meta'];
                    $surah = Surah::updateOrCreate(['number' => $number], [
                        'name_arabic' => (string) $meta['name'], 'name_uz' => (string) $meta['tname'],
                        'name_en' => (string) $meta['ename'], 'ayah_count' => count($chapter['verses']),
                        'revelation_type' => strtolower((string) $meta['type']),
                    ]);
                    foreach ($chapter['verses'] as $verse) {
                        $global++;
                        while (isset($juzStarts[$juz + 1]) && $juzStarts[$juz + 1] <= $global) {
                            $juz++;
                        }
                        while (isset($pageStarts[$page + 1]) && $pageStarts[$page + 1] <= $global) {
                            $page++;
                        }
                        while (isset($quarterStarts[$quarter + 1]) && $quarterStarts[$quarter + 1] <= $global) {
                            $quarter++;
                        }
                        Ayah::updateOrCreate(['surah_id' => $surah->id, 'number_in_surah' => $verse['id']], [
                            'number_global' => $global, 'juz' => $juz, 'page' => $page, 'hizb' => (int) ceil($quarter / 4),
                            'text_arabic' => $verse['text'],
                        ]);
                    }
                }
                foreach ($juzStarts as $number => $first) {
                    DB::table('quran_juz')->updateOrInsert(['number' => $number], [
                        'first_ayah_global' => $first, 'last_ayah_global' => ($juzStarts[$number + 1] ?? 6237) - 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('quran_text_sources')->updateOrInsert(['checksum_sha256' => $checksum], [
                    'name' => 'Tanzil Uthmani via risan/quran-json', 'version' => 'uthmani', 'url' => self::SOURCE_URL,
                    'imported_at' => now(), 'ayah_count' => 6236, 'created_at' => now(), 'updated_at' => now(),
                ]);
            });
            $this->info("114 sura, 6236 oyat, 30 juz, 604 sahifa import qilindi. SHA-256: {$checksum}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function readSource(?string $file, string $url): string
    {
        if ($file === null) {
            return Http::connectTimeout(10)->timeout(120)->get($url)->throw()->body();
        }
        $contents = @file_get_contents($file);
        if ($contents === false || trim($contents) === '') {
            throw new RuntimeException('Manba faylini o‘qib bo‘lmadi.');
        }

        return $contents;
    }

    /** @param array<int, array{meta: SimpleXMLElement, verses: array, offset: int}> $chapters
     * @return array<int, int>
     */
    private function partitions(SimpleXMLElement $nodes, array $chapters, int $expected): array
    {
        $starts = [];
        foreach ($nodes as $node) {
            $index = (int) $node['index'];
            $chapter = $chapters[(int) $node['sura']] ?? null;
            $ayah = (int) $node['aya'];
            if ($index !== count($starts) + 1 || $chapter === null || $ayah < 1 || $ayah > count($chapter['verses'])) {
                throw new RuntimeException('Juz/sahifa metama’lumotlari noto‘g‘ri.');
            }
            $starts[$index] = $chapter['offset'] + $ayah;
        }
        if (count($starts) !== $expected || $starts[1] !== 1 || array_values($starts) !== array_values(array_unique($starts)) || array_values($starts) !== collect($starts)->sort()->values()->all()) {
            throw new RuntimeException('Juz/sahifa bo‘linmalari to‘liq emas.');
        }

        return $starts;
    }
}
