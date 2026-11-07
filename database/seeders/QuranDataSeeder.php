<?php

namespace Database\Seeders;

use App\Models\Quran\Ayah;
use App\Models\Quran\Surah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QuranDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metadataResponse = Http::acceptJson()->timeout(30)->get('https://api.alquran.cloud/v1/meta');
        $metadataResponse->throw();

        $references = $metadataResponse->json('data.surahs.references');
        if (! is_array($references) || count($references) !== 114) {
            throw new RuntimeException('Qur\'on suralari metadata\'si 114 surani o\'z ichiga olmadi.');
        }

        $importedHash = hash('sha256', '');

        DB::transaction(function () use ($references, &$importedHash): void {
            foreach ($references as $reference) {
                $number = (int) $reference['number'];
                $response = Http::acceptJson()->timeout(30)->get("https://api.alquran.cloud/v1/surah/{$number}/quran-uthmani");
                $response->throw();

                $data = $response->json('data');
                if (! is_array($data) || count($data['ayahs'] ?? []) !== (int) $reference['numberOfAyahs']) {
                    throw new RuntimeException("Sura {$number} uchun oyatlar to'liq yuklanmadi.");
                }

                $surah = Surah::updateOrCreate(
                    ['number' => $number],
                    [
                        'name_arabic' => $reference['name'],
                        'name_uz' => (string) $reference['englishName'],
                        'name_en' => (string) $reference['englishName'],
                        'ayah_count' => (int) $reference['numberOfAyahs'],
                        'revelation_type' => strtolower((string) $reference['revelationType']) === 'medinan' ? 'medinan' : 'meccan',
                    ],
                );

                $surah->ayahs()->delete();

                foreach ($data['ayahs'] as $ayah) {
                    $arabicText = trim(ltrim((string) $ayah['text'], "\xEF\xBB\xBF"));
                    $importedHash = hash('sha256', $importedHash.$arabicText);

                    Ayah::create([
                        'surah_id' => $surah->id,
                        'number_in_surah' => (int) $ayah['numberInSurah'],
                        'number_global' => (int) $ayah['number'],
                        'juz' => (int) $ayah['juz'],
                        'page' => (int) $ayah['page'],
                        'hizb' => null,
                        'text_arabic' => $arabicText,
                        'text_uz' => null,
                        'text_ru' => null,
                    ]);
                }
            }

            DB::table('quran_text_sources')->updateOrInsert(
                ['name' => 'Al Quran Cloud', 'version' => 'quran-uthmani'],
                [
                    'url' => 'https://alquran.cloud',
                    'checksum_sha256' => $importedHash,
                    'imported_at' => now(),
                    'ayah_count' => 6236,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });
    }
}
