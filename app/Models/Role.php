<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'rbac_roles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'rbac_permission_role')->orderBy('group')->orderBy('name');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'rbac_role_id');
    }
}
