<?php

namespace App\Services\AI\Contracts;

/** Provider-agnostic LLM port. Swap Gemini for OpenAI/Claude/local by binding another implementation. */
interface LlmClient
{
    public function isConfigured(): bool;

    /**
     * @param  bool  $json  ask the model to answer with a JSON document only
     * @return string|null null when the provider is not configured or the call failed
     */
    public function complete(string $prompt, bool $json = false): ?string;

    public function name(): string;
}
