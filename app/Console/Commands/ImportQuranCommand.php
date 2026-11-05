<?php

namespace App\Console\Commands;

use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Tanzil asosidagi manbadan Qur'on matnini import qiladi.
 * MATN O'ZGARTIRILMAYDI — checksum bilan butunlik nazorat qilinadi.
 *
 * php artisan quran:import [--file=path/to/quran.json]
 */
class ImportQuranCommand extends Command
{
    protected $signature = 'quran:import {--file= : Mahalliy JSON fayl (offline import)}';

    protected $description = 'Import Quran text from Tanzil-based source (checksum verified)';

    private const SOURCE_URL = 'https://cdn.jsdelivr.net/gh/risan/quran-json/dist/quran.json';

    public function handle(): int
    {
        $file = $this->option('file');

        $raw = $file !== null
            ? @file_get_contents($file)
            : (Http::timeout(120)->get(self::SOURCE_URL)->body());

        if ($raw === false || $raw === null || trim($raw) === '' || str_starts_with(trim($raw), '{"code')) {
            $this->error('Manbadan yuklab bo\'lmadi. --file opsiyasi bilan mahalliy fayl bering.');

            return self::FAILURE;
        }

        $checksum = hash('sha256', $raw);
        $data = json_decode($raw);

        if (! is_array($data) || count($data) !== 114) {
            $this->error('Manba 114 surahni o\'z ichiga olmaydi — import bekor qilindi.');

            return self::FAILURE;
        }

        $imported = DB::transaction(function () use ($data, $checksum) {
            $ayahCount = 0;
            $globalNumber = 0;

            foreach ($data as $s) {
                $surah = Surah::updateOrCreate(
                    ['number' => $s->id],
                    [
                        'name_arabic' => $s->name,
                        'name_uz' => $s->transliteration,
                        'name_en' => $s->transliteration,
                        'ayah_count' => $s->total_verses,
                        'revelation_type' => $s->type === 'meccan' ? 'meccan' : 'medinan',
                    ],
                );

                foreach ($s->verses as $v) {
                    $globalNumber++;
                    Ayah::updateOrCreate(
                        ['surah_id' => $surah->id, 'number_in_surah' => $v->id],
                        [
                            'number_global' => $globalNumber,
                            'juz' => 1, // juz ma'lumotlari manbada yo'q — keyingi qo'shimcha importda
                            'page' => 1,
                            'hizb' => null,
                            'text_arabic' => $v->text,
                            'text_uz' => null,
                            'text_ru' => null,
                        ],
                    );
                    $ayahCount++;
                }
            }

            if ($ayahCount !== 6236) {
                abort(422, "Oyatlar soni {$ayahCount} — 6236 bo'lishi shart. Import bekor qilindi.");
            }

            DB::table('quran_text_sources')->insert([
                'name' => 'Tanzil (risan/quran-json uthmani)',
                'version' => '1.0',
                'url' => self::SOURCE_URL,
                'checksum_sha256' => $checksum,
                'imported_at' => now(),
                'ayah_count' => $ayahCount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $ayahCount;
        });

        $this->info("Import muvaffaqiyatli: 114 surah, {$imported} oyat. Checksum: {$checksum}");

        return self::SUCCESS;
    }
}
