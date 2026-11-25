<?php

namespace App\Domains\Harvesting\Enums;

use App\Domains\Rentals\Data\ParsedPrice;

/**
 * What to do with the price read for a listing that was already stored (PriceChangeDetector).
 */
enum PriceReview
{
    // Same price, or nothing readable: keep the current one and drop any pending price.
    case Unchanged;

    // A new price read once: wait for a second read before taking it.
    case Pending;

    // The second read gave the same new price: take it.
    case Confirmed;

    /**
     * Columns to update on the listing (rentals, lands and houses share them).
     */
    public function attributes(ParsedPrice $price): array
    {
        $noPendingPrice = ['pending_price' => null, 'pending_price_amount' => null, 'pending_price_currency' => null];

        return match ($this) {
            self::Unchanged => $noPendingPrice,
            self::Pending => ['pending_price' => $price->raw, 'pending_price_amount' => $price->amount, 'pending_price_currency' => $price->currency],
            self::Confirmed => ['price' => $price->raw, 'price_amount' => $price->amount, 'price_currency' => $price->currency, ...$noPendingPrice],
        };
    }
}
