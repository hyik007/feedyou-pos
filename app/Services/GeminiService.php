<?php

namespace App\Services;

use App\Services\AI\Contracts\LlmClient;

/**
 * @deprecated Kept for backward compatibility. Use App\Services\AI\Contracts\LlmClient
 *             (or the higher-level OrderParser / InsightsService) instead.
 */
class GeminiService
{
    public function __construct(private LlmClient $llm) {}

    public function generate(string $prompt): string
    {
        return $this->llm->complete($prompt) ?? 'AI not configured or unavailable. Visit /setup to add a Gemini API key.';
    }
}
