<?php

namespace App\Models\Quran;

use Illuminate\Database\Eloquent\Model;

class TajweedRule extends Model
{
    protected $table = 'tajweed_rules';

    /** @var list<string> */
    protected $fillable = ['code', 'name_uz', 'description'];
}
