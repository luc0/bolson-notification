<?php

namespace App\Domains\Rentals\Enums;

/**
 * Whether a rental is to live in or for a business (filter options).
 */
enum PropertyUse: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Residential => 'Para vivir',
            self::Commercial => 'Local / comercial',
        };
    }

    /**
     * Property types that identify the use, normalized (lowercase, no accents), singular and plural.
     *
     * @return string[]
     */
    public function keywords(): array
    {
        return match ($this) {
            self::Residential => [
                'casa', 'casas', 'departamento', 'departamentos', 'depto', 'deptos', 'dpto', 'dptos',
                'monoambiente', 'monoambientes', 'cabana', 'cabanas', 'duplex', 'triplex', 'ph',
                'vivienda', 'viviendas', 'chalet', 'loft', 'habitacion', 'habitaciones',
            ],
            self::Commercial => [
                'local', 'locales', 'oficina', 'oficinas', 'salon comercial', 'fondo de comercio',
                'galpon', 'galpones', 'deposito', 'depositos', 'consultorio', 'consultorios',
            ],
        };
    }
}
