<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ayah extends Model
{
    protected $table = 'quran_ayahs';

    /** @var list<string> */
    protected $fillable = ['surah_id', 'number_in_surah', 'number_global', 'juz', 'page', 'hizb', 'text_arabic', 'text_uz', 'text_ru'];

    /**
     * Surah.
     *
     * @return BelongsTo<Surah, $this>
     */
    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }
}
