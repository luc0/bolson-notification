<?php

namespace App\Models;

use App\Location;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model
{
    protected $fillable = [
        'source',
        'content',
        'description',
        'price',
        'price_amount',
        'price_currency',
        'location',
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
        'location' => 'string',
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

    public const OLD_AFTER_MONTHS = 3;

    // Ordered by importance for a tenant (heating in the area depends on gas).
    public const SERVICE_LABELS = [
        'natural_gas' => 'Gas natural',
        'bottled_gas' => 'Gas envasado',
        'electricity' => 'Luz',
        'running_water' => 'Agua corriente',
        'sewer' => 'Cloacas',
        'internet' => 'Internet',
        'cable' => 'Cable',
        'phone' => 'Teléfono',
        'paving' => 'Pavimento',
    ];

    public function scopeByLocation($query, array $locations)
    {
        return $query->whereIn('location', $locations);
    }

    public function getLocationAttribute($value)
    {
        // Si el valor es uno de los valores del enum, retornarlo
        if (in_array($value, Location::values())) {
            return $value;
        }
        // Si no, retornar el string personalizado
        return $value;
    }

    public function setLocationAttribute($value)
    {
        // Permitir cualquier string, no solo los del enum
        $this->attributes['location'] = $value;
    }

    public function isValidLocation(): bool
    {
        return in_array($this->location, Location::values());
    }

    /**
     * created_at is when the scraper first found the rental, not the agency's publish date.
     */
    public function isRented(): bool
    {
        return $this->rented_at !== null;
    }

    public function isAvailable(): bool
    {
        return $this->active && !$this->isRented();
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
            return $symbol . number_format((float) $this->price_amount, 0, ',', '.');
        }

        // Raw text is only worth showing when it's a message ("Consultar"), not a placeholder ("$1", "N/A").
        $hasMeaningfulRawPrice = $this->price !== null
            && preg_match('/\d/', $this->price) !== 1
            && preg_match('/^(n\/?a|null|-+)$/i', trim($this->price)) !== 1;
        if (!$hasMeaningfulRawPrice) {
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
            $this->covered_area ? $this->formatArea($this->covered_area) . ' cubiertos' : null,
            $this->total_area ? $this->formatArea($this->total_area) . ' de terreno' : null,
        ]);
        $bathrooms = $this->countLabel($this->bathrooms, 'baño', 'baños');
        $parkingSpaces = $this->countLabel($this->parking_spaces, 'cochera', 'cocheras');

        return array_values(array_filter([
            $areas ? '📐 ' . implode(' · ', $areas) : null,
            $rooms ? '🛏️ ' . implode(' · ', $rooms) : null,
            $bathrooms ? '🚿 ' . $bathrooms : null,
            $parkingSpaces ? '🚗 ' . $parkingSpaces : null,
            $this->ageLabel(),
        ]));
    }

    /**
     * @return string[] labels of the services stated by the agency, most important first.
     */
    public function serviceLabels(): array
    {
        $services = $this->services ?? [];

        return array_values(array_filter(
            self::SERVICE_LABELS,
            fn (string $service) => in_array($service, $services, true),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    private function countLabel(?int $count, string $singular, string $plural): ?string
    {
        if (!$count) {
            return null;
        }

        return $count . ' ' . ($count === 1 ? $singular : $plural);
    }

    private function formatArea(float $squareMeters): string
    {
        return number_format($squareMeters, 0, ',', '.') . ' m²';
    }

    private function ageLabel(): ?string
    {
        if ($this->age_years === null) {
            return null;
        }

        if ($this->age_years === 0) {
            return '🏗️ A estrenar';
        }

        return '🏗️ ' . $this->countLabel($this->age_years, 'año', 'años') . ' de antigüedad';
    }

    public function isOld(): bool
    {
        return $this->created_at <= now()->subMonths(self::OLD_AFTER_MONTHS);
    }

    public function detectedForHumans(): string
    {
        return $this->created_at->locale('es')->diffForHumans();
    }
}
