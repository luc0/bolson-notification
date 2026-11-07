<?php

namespace App\Domains\Rentals\Enums;

/**
 * Monthly price filter. Ranges apply to prices in pesos; rentals in dollars have their own option.
 */
enum PriceRange: string
{
    case UpTo400k = 'up_to_400k';
    case From400kTo600k = '400k_600k';
    case From600kTo900k = '600k_900k';
    case Over900k = 'over_900k';
    case Dollars = 'usd';

    public function label(): string
    {
        return match ($this) {
            self::UpTo400k => 'Hasta $400k',
            self::From400kTo600k => '$400k–600k',
            self::From600kTo900k => '$600k–900k',
            self::Over900k => '+$900k',
            self::Dollars => 'En dólares',
        };
    }

    /**
     * @return array{0: string, 1: int|null, 2: int|null} [currency, min (inclusive), max (exclusive)]
     */
    public function bounds(): array
    {
        return match ($this) {
            self::UpTo400k => ['ARS', null, 400_000],
            self::From400kTo600k => ['ARS', 400_000, 600_000],
            self::From600kTo900k => ['ARS', 600_000, 900_000],
            self::Over900k => ['ARS', 900_000, null],
            self::Dollars => ['USD', null, null],
        };
    }
}
