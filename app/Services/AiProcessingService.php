<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiProcessingService
{
    private const SYSTEM_PROMPT = <<<EOT
        You are a data extraction assistant for a public real-estate listings aggregator.
        The user gives you the HTML of a public listings page from a real-estate agency in Argentina.
        Your only job is to extract the rental listings that appear on that page into structured JSON.
        This is public, non-personal information; always complete the extraction.
        EOT;

    private const MAX_ATTEMPTS = 2;

    /**
     * @return array|null null when the AI call fails or returns no usable rentals.
     */
    public function process(array $site, string $html): ?array
    {
        $response = $this->requestRentals($site, $html);

        // The model occasionally generates output that doesn't match the schema: worth one retry.
        for ($attempt = 2; $attempt <= self::MAX_ATTEMPTS && $this->failedSchemaValidation($response); $attempt++) {
            Log::warning("⚠️ AI output didn't match the schema, retrying ({$attempt}/" . self::MAX_ATTEMPTS . '): ' . $site['url']);
            $response = $this->requestRentals($site, $html);
        }

        return $this->parseRentals($response, $site);
    }

    private function failedSchemaValidation(Response $response): bool
    {
        return $response->status() === 400 && $response->json('error.code') === 'json_validate_failed';
    }

    private function requestRentals(array $site, string $html): Response
    {
        return Http::withToken(config('services.groq.api_key'))
            ->timeout(120)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => $this->buildUserPrompt($site)],
                    ['role' => 'user', 'content' => $html],
                ],
                'temperature' => 0.3,
                // gpt-oss is a reasoning model: reasoning tokens also count towards max_tokens.
                'reasoning_effort' => config('services.groq.reasoning_effort'),
                'max_tokens' => 32768,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'rentals', 'strict' => true, 'schema' => $this->responseSchema()],
                ],
            ]);
    }

    private function parseRentals(Response $response, array $site): ?array
    {
        if (!$response->successful()) {
            Log::error('❌ AI request failed for ' . $site['url'], ['status' => $response->status()]);
            Log::info($response->body());
            return null;
        }

        $wasTruncated = ($response['choices'][0]['finish_reason'] ?? null) === 'length';
        if ($wasTruncated) {
            Log::error('❌ AI response truncated (max_tokens reached) for ' . $site['url']);
            return null;
        }

        $jsonRaw = trim($response['choices'][0]['message']['content'] ?? '');
        Log::info($jsonRaw);

        $decoded = json_decode($jsonRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('❌ Malformed JSON for ' . $site['url'] . ': ' . json_last_error_msg());
            return null;
        }

        $rentals = $decoded['rentals'] ?? null;
        if (!is_array($rentals)) {
            Log::error('❌ AI response has no "rentals" array for ' . $site['url']);
            return null;
        }

        // An empty list almost always means the model failed, not that the site has no rentals.
        if (empty($rentals)) {
            Log::error('❌ AI returned no rentals for ' . $site['url']);
            return null;
        }

        return $rentals;
    }

    private function buildUserPrompt(array $site): string
    {
        return <<<EOT
            Extraé todos los avisos de alquiler del siguiente HTML.

            Para cada aviso:
            - "Content": título o descripción del aviso (texto completo).
            - "Link": URL completa al aviso. Si es relativa, completala con el baseUrl: {$site['postUrl']}
            - "Caracteristicas": ubicación, precio, cantidad de ambientes, baños, etc. Una por línea (separadas por \\n), cada una empezando con un emoji.
            - "Price": precio de alquiler con la moneda, tal como aparece (ej: "$650.000", "U\$S 550", "Consultar"). null si no figura.
            - "Location": ubicación del alquiler.
            - "Rooms": cantidad de ambientes. null si no se especifica.
            - "Bathrooms": cantidad de baños. null si no se especifica.
            - "Image": el src de la foto principal del aviso, tal cual aparece (ej: "#img-12"). No íconos ni logos. null si no hay.
            EOT;
    }

    private function responseSchema(): array
    {
        $nullableString = ['type' => ['string', 'null']];
        $nullableInteger = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'rentals' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'Content' => ['type' => 'string'],
                            'Link' => ['type' => 'string'],
                            'Caracteristicas' => ['type' => 'string'],
                            'Price' => $nullableString,
                            'Location' => $nullableString,
                            'Rooms' => $nullableInteger,
                            'Bathrooms' => $nullableInteger,
                            'Image' => $nullableString,
                        ],
                        'required' => ['Content', 'Link', 'Caracteristicas', 'Price', 'Location', 'Rooms', 'Bathrooms', 'Image'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['rentals'],
            'additionalProperties' => false,
        ];
    }
}
