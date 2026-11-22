<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\HouseClassifier;
use App\Domains\Houses\Models\House;
use App\Domains\Sources\Data\Source;
use Illuminate\Console\Command;

class ClassifyHouses extends Command
{
    protected $signature = 'houses:classify';

    protected $description = 'Sets city and neighborhood of every house from its texts (no AI). Safe to run again after adding aliases or neighborhoods.';

    public function handle(HouseClassifier $houseClassifier): int
    {
        House::query()->each(fn (House $house) => $houseClassifier->classify($house, Source::findHouseByListingUrl((string) $house->site_path)));

        $rows = House::query()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get()
            ->map(fn (House $row) => [$row->city?->label() ?? 'Sin especificar', $row->total])
            ->all();
        $this->table(['City', 'Houses'], $rows);

        return self::SUCCESS;
    }
}
