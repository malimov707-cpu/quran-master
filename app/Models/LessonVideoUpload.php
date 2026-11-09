<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LessonVideoUpload extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'user_id', 'lesson_id', 'lesson_material_id', 'title', 'size_bytes', 'duration_seconds', 'offline_allowed', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'offline_allowed' => 'boolean', 'size_bytes' => 'integer', 'duration_seconds' => 'integer'];
    }
}
