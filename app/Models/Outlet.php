<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $guarded = ['id'];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort');
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** The single active outlet (multi-outlet routing can replace this later). */
    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrFail();
    }
}
