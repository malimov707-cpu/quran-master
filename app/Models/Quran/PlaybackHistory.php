<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaybackHistory extends Model
{
    protected $table = 'quran_playback_histories';

    protected $fillable = ['user_id', 'audio_file_id', 'position_ms', 'played_at'];

    protected function casts(): array
    {
        return ['played_at' => 'datetime', 'position_ms' => 'integer'];
    }

    /** @return BelongsTo<AudioFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(AudioFile::class, 'audio_file_id');
    }
}
