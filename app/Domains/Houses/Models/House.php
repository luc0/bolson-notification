<?php

namespace App\Domains\Houses\Models;

use App\Domains\Locations\Enums\City;
use Illuminate\Database\Eloquent\Model;

/**
 * A house for sale published by an agency.
 *
 * - source: URL of the listing (stored decoded), site_path: listing_url of the source it was scraped from.
 * - content: the agency's title; description: extra features, one per line.
 * - price (raw text) + price_amount / price_currency (parsed).
 * - covered_area: built m²; total_area: m² of the plot.
 * - location: raw text from the agency; city / neighborhood resolved from it (HouseClassifier).
 */
class House extends Model
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
        'city',
        'neighborhood',
        'bedrooms',
        'bathrooms',
        'covered_area',
        'total_area',
        'image_url',
        'site_path',
        'active',
    ];

    protected $casts = [
        'city' => City::class,
        'price_amount' => 'decimal:2',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'covered_area' => 'float',
        'total_area' => 'float',
        'active' => 'boolean',
    ];

    /**
     * @param  array<int, City|null>  $cities  null = houses without a known city.
     */
    public function scopeByCity($query, array $cities)
    {
        $knownCities = array_values(array_filter($cities));
        $includesUnknown = in_array(null, $cities, true);

        return $query->where(function ($query) use ($knownCities, $includesUnknown) {
            $query->whereIn('city', $knownCities)
                ->when($includesUnknown, fn ($query) => $query->orWhereNull('city'));
        });
    }

    public function scopeAvailable($query)
    {
        return $query->where('active', true);
    }

    /**
     * Prices in dollars first (most houses are sold in U$S), then pesos, then "Consultar precio";
     * by amount within each currency.
     */
    public function scopeOrderByPrice($query, string $direction = 'asc')
    {
        return $query
            ->orderByRaw("case when price_currency = 'USD' and price_amount is not null then 0 when price_currency = 'ARS' and price_amount is not null then 1 else 2 end")
            ->orderBy('price_amount', $direction);
    }

    /**
     * By built m²; houses without it last.
     */
    public function scopeOrderByArea($query, string $direction = 'desc')
    {
        return $query
            ->orderByRaw('case when covered_area is null then 1 else 0 end')
            ->orderBy('covered_area', $direction);
    }

    /**
     * "Mallín Ahogado, El Bolsón"; the raw location when the city is unknown.
     */
    public function locationLabel(): ?string
    {
        if ($this->city === null) {
            return $this->location;
        }

        return collect([$this->neighborhood, $this->city->label()])->filter()->implode(', ');
    }

    /**
     * "U$S 120.000" / "$95.000.000" when parsed, the agency's raw text ("Consultar") otherwise.
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
     * Short facts for the card: "🛏️ 3 dormitorios", "🛁 2 baños", "🏠 120 m² cubiertos", "🌳 Terreno de 1.000 m²".
     *
     * @return string[]
     */
    public function specs(): array
    {
        return array_values(array_filter([
            $this->bedrooms ? '🛏️ '.$this->bedrooms.' '.($this->bedrooms === 1 ? 'dormitorio' : 'dormitorios') : null,
            $this->bathrooms ? '🛁 '.$this->bathrooms.' '.($this->bathrooms === 1 ? 'baño' : 'baños') : null,
            $this->covered_area ? '🏠 '.number_format($this->covered_area, 0, ',', '.').' m² cubiertos' : null,
            $this->total_area ? '🌳 Terreno de '.$this->formattedArea($this->total_area) : null,
        ]));
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

    /**
     * "1.200 m²", or hectares from 1 ha ("2,5 ha").
     */
    private function formattedArea(float $squareMeters): string
    {
        $isLarge = $squareMeters >= self::SQUARE_METERS_PER_HECTARE;
        if ($isLarge) {
            $hectares = $squareMeters / self::SQUARE_METERS_PER_HECTARE;

            return rtrim(rtrim(number_format($hectares, 2, ',', '.'), '0'), ',').' ha';
        }

        return number_format($squareMeters, 0, ',', '.').' m²';
    }

    private function currencySymbol(): string
    {
        return $this->price_currency === 'USD' ? 'U$S ' : '$';
    }
}
