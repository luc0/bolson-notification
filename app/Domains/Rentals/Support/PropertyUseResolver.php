<?php

namespace App\Domains\Rentals\Support;

use App\Domains\Rentals\Enums\PropertyUse;
use Illuminate\Support\Str;

/**
 * Finds whether a rental is to live in or commercial from the property type named in its texts.
 */
class PropertyUseResolver
{
    /**
     * @param  array<int, string|null>  $texts  most reliable first (e.g. title, then description).
     */
    public function resolve(array $texts): ?PropertyUse
    {
        foreach ($texts as $text) {
            $use = $this->firstUseIn($this->normalize($text));
            if ($use !== null) {
                return $use;
            }
        }

        return null;
    }

    /**
     * The property type written first wins: "Casa con local" is to live in.
     */
    private function firstUseIn(string $text): ?PropertyUse
    {
        $firstUse = null;
        $firstPosition = PHP_INT_MAX;

        foreach (PropertyUse::cases() as $use) {
            foreach ($use->keywords() as $keyword) {
                $found = preg_match('/\b'.preg_quote($keyword, '/').'\b/', $text, $matches, PREG_OFFSET_CAPTURE) === 1;
                $isEarlier = $found && $matches[0][1] < $firstPosition;
                if ($isEarlier) {
                    $firstUse = $use;
                    $firstPosition = $matches[0][1];
                }
            }
        }

        return $firstUse;
    }

    private function normalize(?string $text): string
    {
        return Str::of((string) $text)->ascii()->lower()->squish()->toString();
    }
}
