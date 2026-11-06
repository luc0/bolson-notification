<?php

namespace App\Integrations\Groq;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GroqClient
{
    private const CHAT_COMPLETIONS_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function chat(array $body, int $timeoutSeconds = 120): Response
    {
        return Http::withToken(config('services.groq.api_key'))
            ->timeout($timeoutSeconds)
            ->post(self::CHAT_COMPLETIONS_URL, $body);
    }
}
