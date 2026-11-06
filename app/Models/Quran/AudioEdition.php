<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioEdition extends Model
{
    use HasFactory;

    protected $table = 'quran_audio_editions';

    protected $fillable = ['reciter_id', 'identifier', 'provider', 'bitrate', 'style', 'source_url', 'license_url', 'license', 'version', 'offline_allowed', 'manifest_sha256', 'catalog_checked_at'];

    protected function casts(): array
    {
        return ['offline_allowed' => 'boolean', 'catalog_checked_at' => 'datetime', 'bitrate' => 'integer'];
    }

    /** @return BelongsTo<Reciter, $this> */
    public function reciter(): BelongsTo
    {
        return $this->belongsTo(Reciter::class);
    }

    /** @return HasMany<AudioFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(AudioFile::class, 'edition_id');
    }
}
