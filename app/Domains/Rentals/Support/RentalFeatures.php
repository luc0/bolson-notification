<?php

namespace App\Domains\Rentals\Support;

/**
 * The AI writes "Caracteristicas" as free text ("💰 precio: $420.000\n🚿 baños: null\n🏠 0 m²").
 * Most lines repeat fields we already show (price, location, rooms, bathrooms) or have no value,
 * so only the extra, meaningful ones are kept.
 */
class RentalFeatures
{
    // Labels or emojis of lines already shown from their own columns.
    private const REDUNDANT_LABELS = ['ubicación', 'ubicacion', 'precio', 'ambientes', 'dormitorios', 'baños', 'banos'];
    private const REDUNDANT_EMOJIS = ['📍', '🌎', '🌟', '💰', '💸'];
    // Only redundant with a bare number ("🛏️ 3"): "🛋️ Amueblado" is a real feature.
    private const ROOM_EMOJIS = ['🛏', '🛋', '🚿', '🛁'];

    // "1 Dormitorio", "2 Baños", "3 ambientes": already shown from rooms/bathrooms.
    private const ROOM_COUNT_PATTERN = '/^\d+\s*(dormitorios?|ambientes?|baños?|banos?)$/iu';

    private const EMPTY_VALUE_PATTERN = '/^(null|n\/?a|-+|0+([.,]0+)?\s*(m²|m2|mts2?|ha)?|sin datos|no especifica(do)?)$/iu';

    /**
     * @return string[]
     */
    public static function extract(?string $description, ?string $title = null): array
    {
        if ($description === null) {
            return [];
        }

        // Old rows have literal "\n" instead of real line breaks.
        $lines = preg_split('/\r\n|\r|\n|\\\\n/', $description);

        return collect($lines)
            ->map(fn (string $line) => trim($line))
            ->reject(fn (string $line) => $line === '')
            ->reject(fn (string $line) => self::isRedundant($line))
            ->reject(fn (string $line) => self::hasEmptyValue($line))
            ->reject(fn (string $line) => self::isPartOfTitle($line, $title))
            ->values()
            ->all();
    }

    private static function isRedundant(string $line): bool
    {
        $label = mb_strtolower(trim(self::stripEmoji(strstr($line, ':', true) ?: '')));
        $hasRedundantLabel = in_array($label, self::REDUNDANT_LABELS, true);
        if ($hasRedundantLabel) {
            return true;
        }

        $isRoomCount = preg_match(self::ROOM_COUNT_PATTERN, self::stripEmoji($line)) === 1;
        if ($isRoomCount) {
            return true;
        }

        foreach (self::REDUNDANT_EMOJIS as $emoji) {
            if (str_starts_with($line, $emoji)) {
                return true;
            }
        }

        $isBareNumber = preg_match('/^\d+$/', self::stripEmoji($line)) === 1;
        foreach (self::ROOM_EMOJIS as $emoji) {
            if ($isBareNumber && str_starts_with($line, $emoji)) {
                return true;
            }
        }

        return false;
    }

    private static function hasEmptyValue(string $line): bool
    {
        $valueAfterLabel = str_contains($line, ':') ? substr($line, strpos($line, ':') + 1) : $line;
        $value = trim(self::stripEmoji($valueAfterLabel));

        return $value === '' || preg_match(self::EMPTY_VALUE_PATTERN, $value) === 1;
    }

    private static function isPartOfTitle(string $line, ?string $title): bool
    {
        $value = self::stripEmoji($line);
        if ($title === null || $value === '') {
            return false;
        }

        return str_contains(mb_strtolower($title), mb_strtolower($value));
    }

    private static function stripEmoji(string $text): string
    {
        return trim(preg_replace('/^[^\p{L}\p{N}$]+/u', '', $text));
    }
}
