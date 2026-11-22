<?php

namespace App\Domains\Houses\Queries;

use App\Domains\Houses\Enums\HouseSort;
use App\Domains\Houses\Models\House;
use App\Domains\Locations\Enums\City;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Houses for sale listing: available ones by default, filtered by city and sorted by date, price or area.
 * City values are the City enum values, plus UNKNOWN for houses whose city couldn't be resolved.
 */
final readonly class HouseSearch
{
    public const UNKNOWN = 'unknown';

    /**
     * @param  string[]  $cities
     */
    public function __construct(
        public array $cities = [],
        public bool $includeInactive = false,
        public string $sort = HouseSort::Newest->value,
    ) {}

    public function sort(): HouseSort
    {
        return HouseSort::tryFrom($this->sort) ?? HouseSort::Newest;
    }

    /**
     * @return Collection<int, House>
     */
    public function houses(): Collection
    {
        $query = House::query()
            ->when(! $this->includeInactive, fn (Builder $query) => $query->available())
            ->when(! empty($this->cities), fn (Builder $query) => $query->byCity($this->selectedCities()));

        $sortedQuery = match ($this->sort()) {
            HouseSort::PriceAsc => $query->orderByPrice('asc'),
            HouseSort::PriceDesc => $query->orderByPrice('desc'),
            HouseSort::AreaDesc => $query->orderByArea('desc'),
            HouseSort::Newest => $query->latest()->orderByDesc('id'),
        };

        return $sortedQuery->get();
    }

    /**
     * Cities with available houses, in the enum order, e.g. ['el_bolson' => 'El Bolsón (45)'].
     */
    public static function cityOptions(): array
    {
        $counts = House::available()
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
