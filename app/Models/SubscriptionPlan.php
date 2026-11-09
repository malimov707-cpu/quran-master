<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'price_tiyin', 'months', 'benefits_uz', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price_tiyin' => 'integer', 'months' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * Narx so'mda (ko'rsatish uchun).
     */
    protected function priceSom(): Attribute
    {
        return Attribute::get(fn (): int => (int) round($this->price_tiyin / 100));
    }
}
