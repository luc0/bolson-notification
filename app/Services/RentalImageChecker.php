<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Some agencies don't unpublish rented properties: they stamp "ALQUILADO" on the photo instead.
 */
class RentalImageChecker
{
    private const PROMPT = <<<EOT
        Does this real-estate photo have a stamp, banner or overlay text saying the property is already
        rented or reserved (e.g. 'ALQUILADO', 'ALQUILADA', 'RESERVADO', 'RESERVADA'), in any orientation
        (horizontal or diagonal)? Ignore "SE ALQUILA" / "EN ALQUILER" signs, those mean it's available.
        Answer only JSON: {"rented": true|false}
        EOT;

    /**
     * @return bool|null null when the check couldn't be done.
     */
    public function isRented(string $imageUrl): ?bool
    {
        try {
            $response = Http::withToken(config('services.groq.api_key'))
                ->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.vision_model'),
                    'temperature' => 0,
                    'max_tokens' => 50,
                    'reasoning_effort' => 'none',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => self::PROMPT],
                            ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                        ],
                    ]],
                ]);
        } catch (Throwable $e) {
            Log::error("❌ Image check request failed for {$imageUrl}: " . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            Log::error("❌ Image check failed for {$imageUrl}", ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $answer = json_decode($response['choices'][0]['message']['content'] ?? '', true);
        $rented = $answer['rented'] ?? null;

        if (!is_bool($rented)) {
            Log::error("❌ Image check returned an invalid answer for {$imageUrl}");
            return null;
        }

        return $rented;
    }
}
