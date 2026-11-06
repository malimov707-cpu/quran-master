<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;

class AudioPreference extends Model
{
    protected $table = 'quran_audio_preferences';

    protected $fillable = ['user_id', 'edition_id', 'speed', 'volume', 'repeat_count', 'gap_seconds'];

    protected function casts(): array
    {
        return ['speed' => 'float', 'volume' => 'float', 'repeat_count' => 'integer', 'gap_seconds' => 'integer'];
    }
}
