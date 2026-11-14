<?php

namespace App\Domains\Rentals\Queries;

use App\Domains\Locations\Enums\City;
use App\Domains\Rentals\Enums\PriceRange;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Enums\RentalSort;
use App\Domains\Rentals\Enums\Utility;
use App\Domains\Rentals\Models\Rental;
use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Rentals listing filters. Filter values are the enum values, plus UNKNOWN for rentals
 * whose city / property use couldn't be resolved. With addedBefore only rentals added up to
 * that moment are listed and counted (free plan delay).
 */
final readonly class RentalSearch
{
    public const UNKNOWN = 'unknown';

    /**
     * @param  string[]  $cities
     * @param  string[]  $propertyUses
     * @param  string[]  $rooms  "1", "2", "3" or "4" (4 or more).
     */
    public function __construct(
        public array $cities = [],
        public array $propertyUses = [],
        public ?string $priceRange = null,
        public array $rooms = [],
        public bool $withNaturalGas = false,
        public bool $includeInactive = false,
        public ?string $date = null,
        public string $sort = 'newest',
        public ?CarbonInterface $addedBefore = null,
    ) {}

    public function sort(): RentalSort
    {
        return RentalSort::tryFrom($this->sort) ?? RentalSort::Newest;
    }

    public function rentals(): Collection
    {
        $priceRange = PriceRange::tryFrom((string) $this->priceRange);
        $rooms = array_values(array_filter(array_map('intval', $this->rooms), fn (int $count) => $count > 0));

        return Rental::query()
            ->tap(fn (Builder $query) => $this->applyAddedBefore($query))
            ->when(! $this->includeInactive, fn (Builder $query) => $query->available())
            ->when(! empty($this->cities), fn (Builder $query) => $query->byCity(self::toEnums($this->cities, City::class)))
            ->when(! empty($this->propertyUses), fn (Builder $query) => $query->byPropertyUse(self::toEnums($this->propertyUses, PropertyUse::class)))
            ->when($priceRange !== null, fn (Builder $query) => $query->byPriceRange($priceRange))
            ->when(! empty($rooms), fn (Builder $query) => $query->byRooms($rooms))
            ->when($this->withNaturalGas, fn (Builder $query) => $query->withUtility(Utility::NaturalGas))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query))
            ->orderByDesc('active')
            ->tap(fn (Builder $query) => $this->applySort($query))
            ->get();
    }

    /**
     * e.g. ['up_to_400k' => 'Hasta $400k (12)', ..., 'usd' => 'En dólares (9)'], only ranges with rentals.
     */
    public function priceRangeOptions(): array
    {
        return collect(PriceRange::cases())
            ->mapWithKeys(fn (PriceRange $range) => [$range->value => $this->availableRentals()->byPriceRange($range)->count()])
            ->filter()
            ->map(fn (int $count, string $value) => PriceRange::from($value)->label()." ({$count})")
            ->all();
    }

    /**
     * e.g. ['1' => '1 amb. (10)', '2' => '2 amb. (18)', '4' => '4+ amb. (3)'], only counts with rentals.
     */
    public function roomOptions(): array
    {
        $countsByRooms = $this->availableRentals()
            ->where('rooms', '>', 0)
            ->selectRaw('rooms, count(*) as total')
            ->groupBy('rooms')
            ->pluck('total', 'rooms')
            ->groupBy(fn (int $total, int $rooms) => min($rooms, Rental::MANY_ROOMS), preserveKeys: true)
            ->map->sum()
            ->sortKeys();

        return $countsByRooms
            ->mapWithKeys(fn (int $count, int $rooms) => [(string) $rooms => ($rooms === Rental::MANY_ROOMS ? "{$rooms}+" : $rooms)." amb. ({$count})"])
            ->all();
    }

    public function naturalGasCount(): int
    {
        return $this->availableRentals()->withUtility(Utility::NaturalGas)->count();
    }

    /**
     * Cities with available rentals, in the enum order, e.g. ['el_bolson' => 'El Bolsón (45)'].
     */
    public function cityOptions(): array
    {
        return $this->optionsFor('city', City::cases());
    }

    /**
     * e.g. ['residential' => 'Para vivir (52)', 'commercial' => 'Local / comercial (3)'].
     */
    public function propertyUseOptions(): array
    {
        return $this->optionsFor('property_use', PropertyUse::cases());
    }

    /**
     * @param  array<int, City|PropertyUse>  $cases
     */
    private function optionsFor(string $column, array $cases): array
    {
        $counts = $this->availableRentals()
            ->selectRaw("{$column}, count(*) as total")
            ->groupBy($column)
            ->pluck('total', $column);

        $options = collect($cases)
            ->filter(fn (BackedEnum $case) => $counts->has($case->value))
            ->mapWithKeys(fn (BackedEnum $case) => [$case->value => "{$case->label()} ({$counts[$case->value]})"]);

        // pluck() turns the null value into an empty string key.
        $unknownCount = $counts->get('', 0);
        if ($unknownCount > 0) {
            $options->put(self::UNKNOWN, "Sin especificar ({$unknownCount})");
        }

        return $options->all();
    }

    /**
     * @param  string[]  $values
     * @param  class-string<BackedEnum>  $enumClass
     * @return array<int, BackedEnum|null> null = unknown.
     */
    private static function toEnums(array $values, string $enumClass): array
    {
        $enums = array_values(array_filter(array_map(fn (string $value) => $enumClass::tryFrom($value), $values)));
        $includesUnknown = in_array(self::UNKNOWN, $values, true);
        if ($includesUnknown) {
            $enums[] = null;
        }

        return $enums;
    }

    /**
     * Available rentals added after addedBefore: the ones the free plan doesn't show yet.
     */
    public function hiddenRecentCount(): int
    {
        if ($this->addedBefore === null) {
            return 0;
        }

        return Rental::available()->where('created_at', '>', $this->addedBefore)->count();
    }

    private function availableRentals(): Builder
    {
        return Rental::available()->tap(fn (Builder $query) => $this->applyAddedBefore($query));
    }

    private function applyAddedBefore(Builder $query): void
    {
        if ($this->addedBefore === null) {
            return;
        }

        $query->where('created_at', '<=', $this->addedBefore);
    }

    private function applySort(Builder $query): void
    {
        match ($this->sort()) {
            RentalSort::Newest => $query->orderByDesc('created_at'),
            RentalSort::PriceAsc => $query->orderByPrice('asc'),
            RentalSort::PriceDesc => $query->orderByPrice('desc'),
        };
    }

    private function applyDateFilter(Builder $query): void
    {
        if ($this->date === null) {
            return;
        }

        if ($this->date === 'today') {
            $query->whereDate('created_at', today());

            return;
        }

        try {
            $query->whereDate('created_at', Carbon::parse($this->date)->format('Y-m-d'));
        } catch (Throwable) {
            // Invalid date: ignore the filter.
        }
    }
}
