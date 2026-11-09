<?php

namespace App\Models;

use App\Models\Quran\Ayah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuranBookmark extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'ayah_id', 'note'];

    /**
     * Oyat.
     *
     * @return BelongsTo<Ayah, $this>
     */
    public function ayah(): BelongsTo
    {
        return $this->belongsTo(Ayah::class);
    }
}
