<?php

namespace App\Domains\Lands\Queries;

use App\Domains\Lands\Enums\LandSort;
use App\Domains\Lands\Models\Land;
use App\Domains\Locations\Enums\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lands listing: available ones by default, filtered by city and sorted by date, price or area.
 * City values are the City enum values, plus UNKNOWN for lands whose city couldn't be resolved.
 */
final readonly class LandSearch
{
    public const UNKNOWN = 'unknown';

    /**
     * @param  string[]  $cities
     */
    public function __construct(
        public array $cities = [],
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
        $query = Land::query()
            ->when(! $this->includeInactive, fn (Builder $query) => $query->available())
            ->when(! empty($this->cities), fn (Builder $query) => $query->byCity($this->selectedCities()));

        $sortedQuery = match ($this->sort()) {
            LandSort::PriceAsc => $query->orderByPrice('asc'),
            LandSort::PriceDesc => $query->orderByPrice('desc'),
            LandSort::AreaDesc => $query->orderByArea('desc'),
            LandSort::Newest => $query->latest()->orderByDesc('id'),
        };

        return $sortedQuery->get();
    }

    /**
     * Cities with available lands, in the enum order, e.g. ['el_bolson' => 'El Bolsón (45)'].
     */
    public static function cityOptions(): array
    {
        $counts = Land::available()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->pluck('total', 'city');

        $options = collect(City::cases())
            ->filter(fn (City $city) => $counts->has($city->value))
            ->mapWithKeys(fn (City $city) => [$city->value => "{$city->label()} ({$counts[$city->value]})"]);

        // pluck() turns the null value into an empty string key.
        $unknownCount = $counts->get('', 0);
        if ($unknownCount > 0) {
            $options->put(self::UNKNOWN, "Sin especificar ({$unknownCount})");
        }

        return $options->all();
    }

    /**
     * @return array<int, City|null> null = unknown city.
     */
    private function selectedCities(): array
    {
        $cities = array_values(array_filter(array_map(fn (string $value) => City::tryFrom($value), $this->cities)));
        $includesUnknown = in_array(self::UNKNOWN, $this->cities, true);
        if ($includesUnknown) {
            $cities[] = null;
        }

        return $cities;
    }
}
