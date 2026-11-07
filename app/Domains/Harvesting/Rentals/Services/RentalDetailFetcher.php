<?php

namespace App\Domains\Harvesting\Rentals\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Downloads a listing's detail page and turns it into plain text for the AI.
 * Plain HTTP is enough for all sites; Tokko sites fail intermittently (500), hence the retries.
 */
class RentalDetailFetcher
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const MAX_TEXT_LENGTH = 15000;

    public function fetchText(string $url): ?string
    {
        try {
            $response = Http::withUserAgent(self::USER_AGENT)
                ->timeout(30)
                ->retry(4, 3000, throw: false)
                ->get($this->encodeUrl($url));
        } catch (Throwable $e) {
            Log::error("❌ Detail page request failed for {$url}: ".$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::error("❌ Detail page returned {$response->status()} for {$url}");

            return null;
        }

        return mb_substr($this->htmlToText($response->body()), 0, self::MAX_TEXT_LENGTH);
    }

    /**
     * Sources are stored decoded ("Bolsón"): encode only non-ASCII characters and spaces.
     */
    private function encodeUrl(string $url): string
    {
        return preg_replace_callback('/[^\x21-\x7E]/u', fn ($match) => rawurlencode($match[0]), $url);
    }

    private function htmlToText(string $html): string
    {
        $withoutNoise = preg_replace('/<(script|style|noscript|svg|iframe|header|footer|nav)\b[^>]*>.*?<\/\1>/is', ' ', $html);
        $text = preg_replace('/<[^>]+>/', "\n", $withoutNoise);
        // Some sites escape the description HTML ("&lt;p&gt;"), so strip tags again after decoding.
        $text = strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);

        return trim(preg_replace('/\s*\n\s*/', "\n", $text));
    }
}
