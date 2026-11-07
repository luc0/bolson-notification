<?php

namespace App\Console\Commands;

use App\Domains\Harvesting\Services\LandClassifier;
use App\Domains\Lands\Models\Land;
use App\Domains\Sources\Data\Source;
use Illuminate\Console\Command;

class ClassifyLands extends Command
{
    protected $signature = 'lands:classify';

    protected $description = 'Sets city and neighborhood of every land from its texts (no AI). Safe to run again after adding aliases or neighborhoods.';

    public function handle(LandClassifier $landClassifier): int
    {
        Land::query()->each(fn (Land $land) => $landClassifier->classify($land, Source::findLandByListingUrl((string) $land->site_path)));

        $rows = Land::query()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Land $row) => [$row->city?->label() ?? 'Sin especificar', $row->total])
            ->all();
        $this->table(['City', 'Lands'], $rows);

        return self::SUCCESS;
    }
}
