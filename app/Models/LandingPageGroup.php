<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LandingPageGroup extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $group): void {
            $group->slug = Str::slug($group->slug ?: $group->name);
        });
    }

    public function landingPages(): HasMany
    {
        return $this->hasMany(LandingPage::class, 'group_id');
    }
}
