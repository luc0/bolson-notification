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
    ];

    protected $casts = [
        'location' => 'string',
        'price_amount' => 'decimal:2',
        'active' => 'boolean',
        'rented_at' => 'datetime',
    ];

    public const OLD_AFTER_MONTHS = 3;

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

    public function isOld(): bool
    {
        return $this->created_at <= now()->subMonths(self::OLD_AFTER_MONTHS);
    }

    public function detectedForHumans(): string
    {
        return $this->created_at->locale('es')->diffForHumans();
    }
}
