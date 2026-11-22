<?php

namespace App\Domains\Market\Support;

use App\Domains\Market\Contracts\PriceSeries;

/**
 * The series listed in config/market.php, in that order (it's the order of the charts).
 */
class PriceSeriesRegistry
{
    /**
     * @return PriceSeries[]
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), config('market.series', []));
    }

    public function find(string $key): ?PriceSeries
    {
        return collect($this->all())->first(fn (PriceSeries $series) => $series->key() === $key);
    }
}
