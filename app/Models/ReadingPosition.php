<?php

namespace App\Models;

use App\Models\Quran\Surah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPosition extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'surah_id', 'ayah_number'];

    /**
     * Sura.
     *
     * @return BelongsTo<Surah, $this>
     */
    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }
}
