<?php

namespace App\Domains\Rentals\Models;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Enums\Utility;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    public const OLD_AFTER_MONTHS = 3;

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
        'rooms',
        'bathrooms',
        'source_path',
        'active',
        'site_path',
        'image_url',
        'image_checked_url',
        'rented_at',
        'covered_area',
        'total_area',
        'bedrooms',
        'parking_spaces',
        'age_years',
        'services',
        'details_fetched_at',
        'details_attempts',
    ];

    protected $casts = [
        'city' => City::class,
        'price_amount' => 'decimal:2',
        'active' => 'boolean',
        'rented_at' => 'datetime',
        'rooms' => 'integer',
        'bathrooms' => 'integer',
        'bedrooms' => 'integer',
        'parking_spaces' => 'integer',
        'age_years' => 'integer',
        'covered_area' => 'float',
        'total_area' => 'float',
        'services' => 'array',
        'details_fetched_at' => 'datetime',
    ];

    /**
     * @param  array<int, City|null>  $cities  null = rentals without a known city.
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

    public function isRented(): bool
    {
        return $this->rented_at !== null;
    }

    public function isAvailable(): bool
    {
        return $this->active && ! $this->isRented();
    }

    public function scopeAvailable($query)
    {
        return $query->where('active', true)->whereNull('rented_at');
    }

    /**
     * "$420.000" / "U$S 550" when parsed, the agency's raw text ("Consultar") otherwise.
     */
    public function formattedPrice(): ?string
    {
        if ($this->price_amount !== null) {
            $symbol = $this->price_currency === 'USD' ? 'U$S ' : '$';

            return $symbol.number_format((float) $this->price_amount, 0, ',', '.');
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
     * @return string[] e.g. ["📐 45 m² cubiertos", "🛏️ 2 ambientes · 1 dormitorio", "🚿 1 baño", "🚗 1 cochera", "🏗️ 6 años"]
     */
    public function specs(): array
    {
        $rooms = array_filter([
            $this->countLabel($this->rooms, 'ambiente', 'ambientes'),
            $this->countLabel($this->bedrooms, 'dormitorio', 'dormitorios'),
        ]);
        $areas = array_filter([
            $this->covered_area ? $this->formatArea($this->covered_area).' cubiertos' : null,
            $this->total_area ? $this->formatArea($this->total_area).' de terreno' : null,
        ]);
        $bathrooms = $this->countLabel($this->bathrooms, 'baño', 'baños');
        $parkingSpaces = $this->countLabel($this->parking_spaces, 'cochera', 'cocheras');

        return array_values(array_filter([
            $areas ? '📐 '.implode(' · ', $areas) : null,
            $rooms ? '🛏️ '.implode(' · ', $rooms) : null,
            $bathrooms ? '🚿 '.$bathrooms : null,
            $parkingSpaces ? '🚗 '.$parkingSpaces : null,
            $this->ageLabel(),
        ]));
    }

    /**
     * @return string[] labels of the services stated by the agency, most important first.
     */
    public function serviceLabels(): array
    {
        $services = $this->services ?? [];

        return collect(Utility::cases())
            ->filter(fn (Utility $utility) => in_array($utility->value, $services, true))
            ->map(fn (Utility $utility) => $utility->label())
            ->values()
            ->all();
    }

    private function countLabel(?int $count, string $singular, string $plural): ?string
    {
        if (! $count) {
            return null;
        }

        return $count.' '.($count === 1 ? $singular : $plural);
    }

    private function formatArea(float $squareMeters): string
    {
        return number_format($squareMeters, 0, ',', '.').' m²';
    }

    private function ageLabel(): ?string
    {
        if ($this->age_years === null) {
            return null;
        }

        if ($this->age_years === 0) {
            return '🏗️ A estrenar';
        }

        return '🏗️ '.$this->countLabel($this->age_years, 'año', 'años').' de antigüedad';
    }

    /**
     * created_at is when the scraper first found the rental, not the agency's publish date.
     */
    public function isOld(): bool
    {
        return $this->created_at <= now()->subMonths(self::OLD_AFTER_MONTHS);
    }

    public function detectedForHumans(): string
    {
        return $this->created_at->locale('es')->diffForHumans();
    }
}
