<?php

namespace App\Domains\Rentals\Queries;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Models\Rental;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Rentals listing filters. Filter values are the enum values, plus UNKNOWN for rentals
 * whose city / property use couldn't be resolved.
 */
final readonly class RentalSearch
{
    public const UNKNOWN = 'unknown';

    /**
     * @param  string[]  $cities
     * @param  string[]  $propertyUses
     */
    public function __construct(
        public array $cities = [],
        public array $propertyUses = [],
        public bool $includeInactive = false,
        public ?string $date = null,
    ) {}

    public function rentals(): Collection
    {
        return Rental::query()
            ->when(! $this->includeInactive, fn (Builder $query) => $query->available())
            ->when(! empty($this->cities), fn (Builder $query) => $query->byCity(self::toEnums($this->cities, City::class)))
            ->when(! empty($this->propertyUses), fn (Builder $query) => $query->byPropertyUse(self::toEnums($this->propertyUses, PropertyUse::class)))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query))
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Cities with available rentals, in the enum order, e.g. ['el_bolson' => 'El Bolsón (45)'].
     */
    public static function cityOptions(): array
    {
        return self::optionsFor('city', City::cases());
    }

    /**
     * e.g. ['residential' => 'Para vivir (52)', 'commercial' => 'Local / comercial (3)'].
     */
    public static function propertyUseOptions(): array
    {
        return self::optionsFor('property_use', PropertyUse::cases());
    }

    /**
     * @param  array<int, City|PropertyUse>  $cases
     */
    private static function optionsFor(string $column, array $cases): array
    {
        $counts = Rental::available()
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
