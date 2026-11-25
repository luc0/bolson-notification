<?php

namespace App\Domains\Harvesting\Enums;

/**
 * How the importer knew a scraped item is a listing it already had (stored in the price history).
 * Fuzzy methods (fingerprint, photo, text) come later with a confidence below 1.
 */
enum MatchMethod: string
{
    // First time seen: the row that starts the history.
    case Created = 'created';
    case Url = 'url';
    case ExternalId = 'external_id';

    public function confidence(): float
    {
        return 1.0;
    }
}
