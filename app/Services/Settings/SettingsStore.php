<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Key/value runtime settings (restaurant name, AI key, staff PIN…).
 * Secrets are encrypted at rest with APP_KEY.
 */
class SettingsStore
{
    /** Keys that must never be stored in plain text. */
    public const SECRET_KEYS = ['gemini_api_key'];

    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $row = Setting::find($key);
            $value = $row?->value;
            if ($row && $row->encrypted && $value !== null) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (Throwable) {
                    $value = null;
                }
            }
            $this->cache[$key] = $value;
        }

        return $this->cache[$key] ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $encrypt = in_array($key, self::SECRET_KEYS, true) && $value !== null && $value !== '';
        Setting::updateOrCreate(['key' => $key], [
            'value' => $encrypt ? Crypt::encryptString($value) : $value,
            'encrypted' => $encrypt,
        ]);
        $this->cache[$key] = $value;
    }

    public function has(string $key): bool
    {
        return filled($this->get($key));
    }

    public function restaurantName(): string
    {
        return $this->get('restaurant_name', config('app.name', 'My Cafe'));
    }

    public function geminiKey(): ?string
    {
        return $this->get('gemini_api_key') ?: config('services.gemini.key');
    }

    /** Hashed staff PIN; null = PIN not set (dev mode, staff screens open). */
    public function staffPinHash(): ?string
    {
        return $this->get('staff_pin_hash');
    }
}
