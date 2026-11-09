<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoProgress extends Model
{
    use HasFactory;

    protected $table = 'video_progress';

    protected $fillable = ['user_id', 'lesson_material_id', 'position_seconds', 'ranges', 'watched_seconds', 'completed', 'observed_at'];

    protected function casts(): array
    {
        return ['ranges' => 'array', 'completed' => 'boolean', 'observed_at' => 'datetime', 'position_seconds' => 'integer', 'watched_seconds' => 'integer'];
    }
}
