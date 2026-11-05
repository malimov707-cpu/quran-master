<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'label', 'module'];
}
