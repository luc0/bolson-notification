<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\RentalLocator;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Sources\Data\Source;
use Illuminate\Console\Command;

class ResolveRentalCities extends Command
{
    protected $signature = 'rentals:resolve-cities';

    protected $description = 'Sets city and neighborhood of every rental from its texts (no AI). Safe to run again after adding aliases or neighborhoods.';

    public function handle(RentalLocator $rentalLocator): int
    {
        Rental::query()->each(fn (Rental $rental) => $rentalLocator->locate($rental, Source::findByListingUrl((string) $rental->site_path)));

        $this->table(['City', 'Rentals'], Rental::query()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Rental $row) => [$row->city?->label() ?? 'Sin especificar', $row->total]));

        return self::SUCCESS;
    }
}
