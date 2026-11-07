<?php

namespace App\Domains\Rentals\Enums;

/**
 * Cities of the Comarca Andina we show rentals for (filter options).
 */
enum City: string
{
    case ElBolson = 'el_bolson';
    case LagoPuelo = 'lago_puelo';
    case ElHoyo = 'el_hoyo';
    case Epuyen = 'epuyen';
    case Cholila = 'cholila';

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
            self::ElBolson => 'El Bolsón',
            self::LagoPuelo => 'Lago Puelo',
            self::ElHoyo => 'El Hoyo',
            self::Epuyen => 'Epuyén',
            self::Cholila => 'Cholila',
        };
    }

    /**
     * Ways the agencies write the city, normalized (lowercase, no accents).
     *
     * @return string[]
     */
    public function aliases(): array
    {
        return match ($this) {
            self::ElBolson => ['el bolson', 'bolson'],
            self::LagoPuelo => ['lago puelo', 'puelo'],
            self::ElHoyo => ['el hoyo'],
            self::Epuyen => ['epuyen'],
            self::Cholila => ['cholila'],
        };
    }

    /**
     * Known neighborhoods (barrios / parajes) that only exist in this city: they identify the city
     * even when the listing doesn't name it. Only add names you're sure about.
     *
     * @return string[] canonical names
     */
    public function neighborhoods(): array
    {
        return match ($this) {
            self::ElBolson => ['Mallín Ahogado', 'Villa Turismo', 'Los Abedules', 'Las Quintas', 'Arrayanes', 'Barrio Venzano', 'Costanera Sur', 'Buena Vista'],
            self::LagoPuelo => ['Las Golondrinas', 'Entre Ríos'],
            self::ElHoyo, self::Epuyen, self::Cholila => [],
        };
    }
}
