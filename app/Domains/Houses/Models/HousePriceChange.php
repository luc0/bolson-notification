<?php

namespace App\Domains\Houses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A price a house was seen with: the first one when it was found, then one row per real change
 * (or when it was recognized by something other than its URL).
 *
 * - match_method / match_confidence: how sure the importer was that it is the same listing
 *   ("created", "url", "external_id"...; 1.0 = certain). Written by Harvesting.
 */
class HousePriceChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'price',
        'price_amount',
        'price_currency',
        'match_method',
        'match_confidence',
        'observed_at',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'match_confidence' => 'float',
        'observed_at' => 'datetime',
    ];

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /**
     * "U$S 120.000" / "$95.000.000".
     */
    public function formattedPrice(): ?string
    {
        if ($this->price_amount === null) {
            return null;
        }

        $symbol = $this->price_currency === 'USD' ? 'U$S ' : '$';

        return $symbol.number_format((float) $this->price_amount, 0, ',', '.');
    }
}
