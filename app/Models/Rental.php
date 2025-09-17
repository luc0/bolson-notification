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
        'location',
        'rooms',
        'bathrooms',
        'source_path',
        'active',
        'site_path',
    ];

    protected $casts = [
        'location' => 'string',
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
}
