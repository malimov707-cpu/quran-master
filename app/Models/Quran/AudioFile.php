<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioFile extends Model
{
    use HasFactory;

    protected $table = 'quran_audio_files';

    protected $fillable = ['edition_id', 'surah_id', 'ayah_id', 'kind', 'number', 'url', 'status', 'size_bytes', 'duration_ms', 'etag', 'verified_at', 'http_status'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'size_bytes' => 'integer', 'number' => 'integer', 'duration_ms' => 'integer'];
    }

    /** @return BelongsTo<AudioEdition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(AudioEdition::class, 'edition_id');
    }

    /** @return BelongsTo<Ayah, $this> */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }

    /** @return BelongsTo<Surah, $this> */
    public function surah(): BelongsTo
    {
        return $this->belongsTo(Surah::class);
    }

    /** @return HasMany<AudioTimestamp, $this> */
    public function alignmentSegments(): HasMany
    {
        return $this->hasMany(AudioTimestamp::class, 'audio_file_id');
    }
}
