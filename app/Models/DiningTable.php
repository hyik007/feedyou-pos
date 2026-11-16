<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(fn (self $t) => $t->qr_token ??= Str::random(24));
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function openOrders(): HasMany
    {
        return $this->orders()->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]);
    }

    public function qrUrl(): string
    {
        return url('/t/'.$this->qr_token);
    }
}
