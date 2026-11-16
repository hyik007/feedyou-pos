<?php

namespace App\Providers;

use App\Services\AI\Contracts\LlmClient;
use App\Services\AI\GeminiClient;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SettingsStore::class);
        // Swap the AI provider here (e.g. an OpenAI or Claude client) without touching callers.
        $this->app->bind(LlmClient::class, GeminiClient::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
