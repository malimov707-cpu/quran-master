<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Surah extends Model
{
    protected $table = 'quran_surahs';

    /** @var list<string> */
    protected $fillable = ['number', 'name_arabic', 'name_uz', 'name_en', 'ayah_count', 'revelation_type'];

    /**
     * Surah oyatlari tartib bilan.
     *
     * @return HasMany<Ayah, $this>
     */
    public function ayahs(): HasMany
    {
        return $this->hasMany(Ayah::class)->orderBy('number_in_surah');
    }
}
