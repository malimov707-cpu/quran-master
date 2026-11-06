<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioTimestamp extends Model
{
    protected $table = 'quran_audio_timestamps';

    public $timestamps = false;

    protected $fillable = ['audio_file_id', 'ayah_id', 'start_ms', 'end_ms', 'source_url'];

    protected function casts(): array
    {
        return ['start_ms' => 'integer', 'end_ms' => 'integer'];
    }

    /** @return BelongsTo<AudioFile, $this> */
    public function audioFile(): BelongsTo
    {
        return $this->belongsTo(AudioFile::class, 'audio_file_id');
    }

    /** @return BelongsTo<Ayah, $this> */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
