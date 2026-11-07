<?php

namespace App\Domains\Lands\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A land plot for sale published by an agency.
 *
 * - source: URL of the listing (stored decoded), site_path: listing_url of the source it was scraped from.
 * - content: the agency's title; description: extra features, one per line.
 * - price (raw text) + price_amount / price_currency (parsed); area in m².
 */
class Land extends Model
{
    private const SQUARE_METERS_PER_HECTARE = 10000;

    protected $fillable = [
        'source',
        'content',
        'description',
        'price',
        'price_amount',
        'price_currency',
        'location',
        'area',
        'image_url',
        'site_path',
        'active',
    ];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'area' => 'float',
        'active' => 'boolean',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('active', true);
    }

    /**
     * Prices in dollars first (most lands are sold in U$S), then pesos, then "Consultar precio";
     * by amount within each currency.
     */
    public function scopeOrderByPrice($query, string $direction = 'asc')
    {
        return $query
            ->orderByRaw("case when price_currency = 'USD' and price_amount is not null then 0 when price_currency = 'ARS' and price_amount is not null then 1 else 2 end")
            ->orderBy('price_amount', $direction);
    }

    /**
     * Lands without a known area last.
     */
    public function scopeOrderByArea($query, string $direction = 'desc')
    {
        return $query
            ->orderByRaw('case when area is null then 1 else 0 end')
            ->orderBy('area', $direction);
    }

    /**
     * "U$S 35.000" / "$45.000.000" when parsed, the agency's raw text ("Consultar") otherwise.
     */
    public function formattedPrice(): ?string
    {
        if ($this->price_amount !== null) {
            return $this->currencySymbol().number_format((float) $this->price_amount, 0, ',', '.');
        }

        // Raw text is only worth showing when it's a message ("Consultar"), not a placeholder ("$1", "N/A").
        $hasMeaningfulRawPrice = $this->price !== null
            && preg_match('/\d/', $this->price) !== 1
            && preg_match('/^(n\/?a|null|-+)$/i', trim($this->price)) !== 1;
        if (! $hasMeaningfulRawPrice) {
            return null;
        }

        return $this->price;
    }

    /**
     * "1.200 m²", or hectares from 1 ha ("2,5 ha").
     */
    public function formattedArea(): ?string
    {
        if (! $this->area) {
            return null;
        }

        $isLarge = $this->area >= self::SQUARE_METERS_PER_HECTARE;
        if ($isLarge) {
            $hectares = $this->area / self::SQUARE_METERS_PER_HECTARE;

            return rtrim(rtrim(number_format($hectares, 2, ',', '.'), '0'), ',').' ha';
        }

        return number_format($this->area, 0, ',', '.').' m²';
    }

    /**
     * "U$S 29/m²", only when both the price and the area are known.
     */
    public function formattedPricePerSquareMeter(): ?string
    {
        $canCompute = $this->price_amount !== null && $this->area > 0;
        if (! $canCompute) {
            return null;
        }

        $pricePerSquareMeter = (float) $this->price_amount / $this->area;
        $decimals = $pricePerSquareMeter < 10 ? 2 : 0;

        return $this->currencySymbol().number_format($pricePerSquareMeter, $decimals, ',', '.').'/m²';
    }

    /**
     * @return string[] extra features written by the AI, one per line.
     */
    public function features(): array
    {
        $lines = preg_split('/\\\\n|\R/', (string) $this->description);

        return array_values(array_filter(array_map('trim', $lines)));
    }

    public function detectedForHumans(): string
    {
        return $this->created_at->locale('es')->diffForHumans();
    }

    private function currencySymbol(): string
    {
        return $this->price_currency === 'USD' ? 'U$S ' : '$';
    }
}
