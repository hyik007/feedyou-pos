<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LlmClient;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiClient implements LlmClient
{
    public function __construct(private SettingsStore $settings) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->geminiKey());
    }

    public function complete(string $prompt, bool $json = false): ?string
    {
        $key = $this->settings->geminiKey();
        if (! $key) {
            return null;
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash');
        $body = ['contents' => [['parts' => [['text' => $prompt]]]]];
        if ($json) {
            $body['generationConfig'] = ['responseMimeType' => 'application/json', 'temperature' => 0];
        }

        try {
            $res = Http::timeout(config('services.gemini.timeout', 15))
                ->withHeaders(['x-goog-api-key' => $key])   // header, not query string – keeps the key out of logs
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);

            if ($res->failed()) {
                Log::warning('Gemini call failed', ['status' => $res->status()]);

                return null;
            }

            return $res->json('candidates.0.content.parts.0.text');
        } catch (Throwable $e) {
            Log::warning('Gemini call errored', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
