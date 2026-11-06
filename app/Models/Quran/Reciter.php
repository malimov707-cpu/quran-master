<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reciter extends Model
{
    use HasFactory;

    protected $table = 'quran_reciters';

    protected $fillable = ['name', 'style', 'audio_base_url', 'licensed'];

    protected function casts(): array
    {
        return ['licensed' => 'boolean'];
    }

    /** @return HasMany<AudioEdition, $this> */
    public function editions(): HasMany
    {
        return $this->hasMany(AudioEdition::class);
    }
}
