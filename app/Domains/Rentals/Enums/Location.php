<?php

namespace App\Domains\Rentals\Enums;

enum Location: string
{
    case EL_BOLSON = 'El Bolsón';
    case LAGO_PUELO = 'Lago Puelo';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return [
            self::EL_BOLSON->value => self::EL_BOLSON->value,
            self::LAGO_PUELO->value => self::LAGO_PUELO->value,
        ];
    }
}
