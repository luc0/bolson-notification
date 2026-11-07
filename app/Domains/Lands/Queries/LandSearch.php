<?php

namespace App\Domains\Lands\Queries;

use App\Domains\Lands\Enums\LandSort;
use App\Domains\Lands\Models\Land;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lands listing: available ones by default, sorted by date, price or area.
 */
final readonly class LandSearch
{
    public function __construct(
        public bool $includeInactive = false,
        public string $sort = LandSort::Newest->value,
    ) {}

    public function sort(): LandSort
    {
        return LandSort::tryFrom($this->sort) ?? LandSort::Newest;
    }

    /**
     * @return Collection<int, Land>
     */
    public function lands(): Collection
    {
        $query = Land::query()->when(! $this->includeInactive, fn ($query) => $query->available());

        $sortedQuery = match ($this->sort()) {
            LandSort::PriceAsc => $query->orderByPrice('asc'),
            LandSort::PriceDesc => $query->orderByPrice('desc'),
            LandSort::AreaDesc => $query->orderByArea('desc'),
            LandSort::Newest => $query->latest()->orderByDesc('id'),
        };

        return $sortedQuery->get();
    }
}
