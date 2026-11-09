<?php

namespace App\Models;

use App\Models\Quran\Surah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackHistory extends Model
{
    protected $table = 'playback_history';

    /** @var list<string> */
    protected $fillable = ['user_id', 'surah_id', 'ayah_from', 'ayah_to', 'seconds_listened', 'reciter'];

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
