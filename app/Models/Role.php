<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'label', 'description'];

    /**
     * Role'ga tegishli permissionlar.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles');
    }

    /**
     * Rol nomi bo'yicha solishtirish.
     */
    public function hasName(string $name): bool
    {
        return $this->name === $name;
    }
}
