<?php

namespace App\Domains\Harvesting\Services;

use App\Domains\Sources\Data\Source;
use App\Integrations\Groq\GroqClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Extracts the listings of a listing page capture with the AI into an array of items
 * following a strict JSON schema. Subclasses define what an item is (rental, land...).
 */
abstract class AiListingExtractor
{
    private const MAX_ATTEMPTS = 2;

    public function __construct(private readonly GroqClient $groq) {}

    /**
     * Key of the items array in the AI response (e.g. "rentals").
     */
    abstract protected function itemsKey(): string;

    abstract protected function systemPrompt(): string;

    abstract protected function userPrompt(Source $source): string;

    /**
     * JSON schema of one item.
     */
    abstract protected function itemSchema(): array;

    protected function reasoningEffort(): string
    {
        return config('services.groq.reasoning_effort');
    }

    /**
     * @return array|null null when the AI call fails or returns no usable items.
     */
    public function extract(Source $source, string $html): ?array
    {
        $response = $this->requestItems($source, $html);

        // The model occasionally generates output that doesn't match the schema: worth one retry.
        for ($attempt = 2; $attempt <= self::MAX_ATTEMPTS && $this->failedSchemaValidation($response); $attempt++) {
            Log::warning("⚠️ AI output didn't match the schema, retrying ({$attempt}/".self::MAX_ATTEMPTS.'): '.$source->key);
            $response = $this->requestItems($source, $html);
        }

        return $this->parseItems($response, $source);
    }

    private function failedSchemaValidation(Response $response): bool
    {
        return $response->status() === 400 && $response->json('error.code') === 'json_validate_failed';
    }

    private function requestItems(Source $source, string $html): Response
    {
        return $this->groq->chat([
            'model' => config('services.groq.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($source)],
                ['role' => 'user', 'content' => $html],
            ],
            'temperature' => 0.3,
            // gpt-oss is a reasoning model: reasoning tokens also count towards max_tokens.
            'reasoning_effort' => $this->reasoningEffort(),
            'max_tokens' => 32768,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => $this->itemsKey(), 'strict' => true, 'schema' => $this->responseSchema()],
            ],
        ]);
    }

    private function parseItems(Response $response, Source $source): ?array
    {
        if (! $response->successful()) {
            Log::error('❌ AI request failed for '.$source->key, ['status' => $response->status()]);
            Log::info($response->body());

            return null;
        }

        $wasTruncated = ($response['choices'][0]['finish_reason'] ?? null) === 'length';
        if ($wasTruncated) {
            Log::error('❌ AI response truncated (max_tokens reached) for '.$source->key);

            return null;
        }

        $jsonRaw = trim($response['choices'][0]['message']['content'] ?? '');
        Log::info($jsonRaw);

        $decoded = json_decode($jsonRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('❌ Malformed JSON for '.$source->key.': '.json_last_error_msg());

            return null;
        }

        $itemsKey = $this->itemsKey();
        $items = $decoded[$itemsKey] ?? null;
        if (! is_array($items)) {
            Log::error("❌ AI response has no \"{$itemsKey}\" array for ".$source->key);

            return null;
        }

        // An empty list almost always means the model failed, not that the site has no listings.
        if (empty($items)) {
            Log::error("❌ AI returned no {$itemsKey} for ".$source->key);

            return null;
        }

        return $items;
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                $this->itemsKey() => ['type' => 'array', 'items' => $this->itemSchema()],
            ],
            'required' => [$this->itemsKey()],
            'additionalProperties' => false,
        ];
    }
}
