<?php

namespace App\Domains\Rentals\Enums;

/**
 * Utilities a rental has (luz, gas, agua...), stored in `rentals.services`.
 * Cases are ordered by importance for a tenant: heating in the area depends on gas.
 */
enum Utility: string
{
    case NaturalGas = 'natural_gas';
    case BottledGas = 'bottled_gas';
    case Electricity = 'electricity';
    case RunningWater = 'running_water';
    case Sewer = 'sewer';
    case Internet = 'internet';
    case Cable = 'cable';
    case Phone = 'phone';
    case Paving = 'paving';

    public function label(): string
    {
        return match ($this) {
            self::NaturalGas => 'Gas natural',
            self::BottledGas => 'Gas envasado',
            self::Electricity => 'Luz',
            self::RunningWater => 'Agua corriente',
            self::Sewer => 'Cloacas',
            self::Internet => 'Internet',
            self::Cable => 'Cable',
            self::Phone => 'Teléfono',
            self::Paving => 'Pavimento',
        };
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
