<?php

namespace App\Services\AI;

use App\Models\Quran\Ayah;

/**
 * Quran Verse Alignment — O'Z ALGORITMIMIZ (tashqi xizmat talab qilmaydi).
 *
 * Whisper transkripsiyasidagi so'zlarni bazadagi oyat matni bilan
 * solishtiradi: tushirilgan, almashtirilgan, takrorlangan so'zlar va
 * noto'g'ri ketma-ketlikni aniqlaydi.
 *
 * Arab matni diakritikasiz normalize qilinadi (Whisper ham shunday chiqaradi).
 */
class QuranAlignmentService
{
    /**
     * Arab so'zini solishtirish uchun normalize qilish:
     * harakatlar (diakritiklar) va maxsus belgilarni olib tashlash.
     */
    public function normalizeArabic(string $text): string
    {
        // U+064B..U+0652 harakatlar, U+0670 superscript alef, U+06D6..U+06ED quran belgilari
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{06D6}-\x{06ED}\x{0640}]/u', '', $text) ?? $text;

        // Standartlashtirish: alef variantlari → alef, hamza tushunish
        $text = str_replace(['ٱ', 'أ', 'إ', 'آ'], 'ا', $text);
        $text = str_replace(['ة'], 'ه', $text);

        return trim($text);
    }

    /**
     * Matnni so'zlarga bo'lish (normalize qilingan holda).
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $normalized = $this->normalizeArabic($text);

        return array_values(array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            fn ($w) => $w !== '' && ! preg_match('/^[^\p{Arabic}]+$/u', $w),
        ));
    }

    /**
     * Kutilgan oyatlar (bazadan) so'z ketma-ketligi.
     *
     * @param  list<int>  $ayahNumbers
     * @return list<array{ayah: int, word: string}>
     */
    public function expectedWords(int $surahNumber, array $ayahNumbers): array
    {
        $ayahs = Ayah::query()
            ->whereHas('surah', fn ($q) => $q->where('number', $surahNumber))
            ->whereIn('number_in_surah', $ayahNumbers)
            ->orderBy('number_in_surah')
            ->get(['number_in_surah', 'text_arabic']);

        $result = [];
        foreach ($ayahs as $ayah) {
            foreach ($this->tokenize($ayah->text_arabic) as $word) {
                $result[] = ['ayah' => $ayah->number_in_surah, 'word' => $word];
            }
        }

        return $result;
    }

    /**
     * Alignment: LCS (longest common subsequence) asosida so'zlarni moslashtirish
     * va xato turlarini aniqlash.
     *
     * @param  list<array{ayah: int, word: string}>  $expected
     * @param  list<array{word: string, start_ms: float|null, end_ms: float|null, confidence: float|null}>  $heard
     * @return array{
     *     matches: list<array{expected_ayah: int, expected: string, heard: string, start_ms: ?float, end_ms: ?float, confidence: ?float, is_correct: bool}>,
     *     missing: list<array{ayah: int, word: string}>,
     *     extra: list<array{word: string, start_ms: ?float}>,
     *     repetitions: list<array{word: string}>,
     *     similarity: float
     * }
     */
    public function align(array $expected, array $heard): array
    {
        $eWords = array_column($expected, 'word');
        $eAyahs = array_column($expected, 'ayah');
        $hWords = array_column($heard, 'word');

        $n = count($eWords);
        $m = count($hWords);

        // LCS matritsasi
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if ($this->wordsMatch($eWords[$i], $hWords[$j])) {
                    $dp[$i][$j] = $dp[$i + 1][$j + 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i + 1][$j], $dp[$i][$j + 1]);
                }
            }
        }

        $matches = [];
        $missing = [];
        $extra = [];

        // Backtrack
        $i = 0;
        $j = 0;
        $lastMatchedHeard = -1;
        $repetitions = [];

        while ($i < $n && $j < $m) {
            if ($this->wordsMatch($eWords[$i], $hWords[$j])) {
                // Takrorlanish: eshitilgan so'z oldingi mos mosligidan oldin bo'lsa
                if ($lastMatchedHeard >= 0 && $j > $lastMatchedHeard + 1 && $this->wordsMatch($eWords[$i - 1] ?? '', $hWords[$j - 1] ?? '')) {
                    // ketma-ket mos + sakrav
                }
                $matches[] = [
                    'expected_ayah' => $eAyahs[$i],
                    'expected' => $eWords[$i],
                    'heard' => $hWords[$j],
                    'start_ms' => $heard[$j]['start_ms'] ?? null,
                    'end_ms' => $heard[$j]['end_ms'] ?? null,
                    'confidence' => $heard[$j]['confidence'] ?? null,
                    'is_correct' => true,
                ];
                $lastMatchedHeard = $j;
                $i++;
                $j++;
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $missing[] = ['ayah' => $eAyahs[$i], 'word' => $eWords[$i]];
                $i++;
            } else {
                $extra[] = ['word' => $hWords[$j], 'start_ms' => $heard[$j]['start_ms'] ?? null];
                $j++;
            }
        }

        while ($i < $n) {
            $missing[] = ['ayah' => $eAyahs[$i], 'word' => $eWords[$i]];
            $i++;
        }
        while ($j < $m) {
            $extra[] = ['word' => $hWords[$j], 'start_ms' => $heard[$j]['start_ms'] ?? null];
            $j++;
        }

        // Takrorlanishlarni aniqlash: eshitilgan ketma-ketlikda bir xil so'z qo'shni bo'lsa
        for ($k = 1; $k < $m; $k++) {
            if ($this->wordsMatch($hWords[$k], $hWords[$k - 1])) {
                $repetitions[] = ['word' => $hWords[$k], 'start_ms' => $heard[$k]['start_ms'] ?? null];
            }
        }

        $similarity = $n > 0 ? round(count($matches) / $n, 3) : 0.0;

        return compact('matches', 'missing', 'extra', 'repetitions', 'similarity');
    }

    /**
     * So'zlar mosmi — normalize qilib taqqoslash (katta-kichik harf e'tiborsiz).
     */
    private function wordsMatch(string $a, string $b): bool
    {
        $na = $this->normalizeArabic($a);
        $nb = $this->normalizeArabic($b);

        // To'liq moslik yoki yuqori o'xshashlik (Levenshtein)
        if ($na === $nb) {
            return true;
        }

        $maxLen = max(mb_strlen($na), mb_strlen($nb));
        if ($maxLen === 0) {
            return true;
        }

        return (levenshtein($na, $nb) / $maxLen) <= 0.25; // 25% gacha farq — ehtimoliy talaffuz
    }
}
