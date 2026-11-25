<?php

namespace App\Domains\Harvesting\Support;

use App\Domains\Harvesting\Enums\PriceReview;
use App\Domains\Rentals\Data\ParsedPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether a listing's price really changed, so the AI misreading a price in one run doesn't rewrite it:
 * a new price is only taken when two reads in a row agree (the scraper re-reads the source while one is pending).
 * Implausible jumps are ignored as a safety net (see config/harvesting.php price_changes).
 */
class PriceChangeDetector
{
    /**
     * @param  Model  $listing  Rental, Land or House (same price and pending_price columns).
     */
    public function review(Model $listing, ParsedPrice $newPrice): PriceReview
    {
        $currentAmount = $listing->price_amount === null ? null : (float) $listing->price_amount;
        $isChange = $this->isRealChange($currentAmount, $listing->price_currency, $newPrice, $listing->source);
        if (! $isChange) {
            return PriceReview::Unchanged;
        }

        $confirmsPendingPrice = $listing->pending_price_amount !== null
            && (float) $listing->pending_price_amount === $newPrice->amount
            && $listing->pending_price_currency === $newPrice->currency;
        if (! $confirmsPendingPrice) {
            return PriceReview::Pending;
        }

        return PriceReview::Confirmed;
    }

    private function isRealChange(?float $oldAmount, ?string $oldCurrency, ParsedPrice $newPrice, string $link): bool
    {
        // "Consultar" or an unreadable price: keep the known one.
        if ($newPrice->amount === null) {
            return false;
        }

        $hadNoPrice = $oldAmount === null || $oldAmount <= 0;
        $isOtherCurrency = $oldCurrency !== $newPrice->currency;
        if ($hadNoPrice || $isOtherCurrency) {
            return true;
        }

        if ($newPrice->amount === $oldAmount) {
            return false;
        }

        $maxRatio = (float) config('harvesting.price_changes.max_ratio');
        $ratio = $newPrice->amount / $oldAmount;
        $isImplausibleJump = $ratio > $maxRatio || $ratio < 1 / $maxRatio;
        if ($isImplausibleJump) {
            Log::warning("⚠️ Price change ignored as implausible ({$oldAmount} → {$newPrice->amount} {$newPrice->currency}): {$link}");

            return false;
        }

        return true;
    }
}
