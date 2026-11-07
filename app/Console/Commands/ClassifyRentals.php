<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\RentalClassifier;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Sources\Data\Source;
use Illuminate\Console\Command;

class ClassifyRentals extends Command
{
    protected $signature = 'rentals:classify';

    protected $description = 'Sets city, neighborhood and property use of every rental from its texts (no AI). Safe to run again after adding aliases, neighborhoods or keywords.';

    public function handle(RentalClassifier $rentalClassifier): int
    {
        Rental::query()->each(fn (Rental $rental) => $rentalClassifier->classify($rental, Source::findRentalByListingUrl((string) $rental->site_path)));

        $this->table(['City', 'Rentals'], $this->countBy('city', fn (Rental $row) => $row->city?->label()));
        $this->table(['Property use', 'Rentals'], $this->countBy('property_use', fn (Rental $row) => $row->property_use?->label()));

        return self::SUCCESS;
    }

    private function countBy(string $column, callable $label): array
    {
        return Rental::query()
            ->selectRaw("{$column}, count(*) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn (Rental $row) => [$label($row) ?? 'Sin especificar', $row->total])
            ->all();
    }
}
