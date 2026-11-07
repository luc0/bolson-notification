<?php

namespace App\Http\Controllers;

use App\Domains\Rentals\Enums\City;
use App\Domains\Rentals\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class RentalController extends Controller
{
    // Filter value for rentals whose city couldn't be resolved.
    private const UNKNOWN_CITY = 'unknown';

    public function index(Request $request)
    {
        $includeInactive = $request->boolean('include_inactive');
        $selectedCities = array_values(array_filter((array) $request->input('cities', [])));

        $rentals = Rental::query()
            ->when(! $includeInactive, fn (Builder $query) => $query->available())
            ->when(! empty($selectedCities), fn (Builder $query) => $query->byCity($this->citiesToFilter($selectedCities)))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query, $request->input('date')))
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->get();

        $cityOptions = $this->cityOptions();

        return view('rentals.index', compact('rentals', 'cityOptions', 'selectedCities', 'includeInactive'));
    }

    /**
     * @param  string[]  $selectedCities
     * @return array<int, City|null>
     */
    private function citiesToFilter(array $selectedCities): array
    {
        $cities = array_values(array_filter(array_map(fn (string $value) => City::tryFrom($value), $selectedCities)));
        $includesUnknown = in_array(self::UNKNOWN_CITY, $selectedCities, true);
        if ($includesUnknown) {
            $cities[] = null;
        }

        return $cities;
    }

    /**
     * Cities with available rentals, in the enum order, e.g. ['el_bolson' => 'El Bolsón (45)'].
     */
    private function cityOptions(): array
    {
        $countsByCity = Rental::available()
            ->selectRaw('city, count(*) as total')
            ->groupBy('city')
            ->pluck('total', 'city');

        $options = collect(City::cases())
            ->filter(fn (City $city) => $countsByCity->has($city->value))
            ->mapWithKeys(fn (City $city) => [$city->value => "{$city->label()} ({$countsByCity[$city->value]})"]);

        // pluck() turns the null city into an empty string key.
        $unknownCount = $countsByCity->get('', 0);
        if ($unknownCount > 0) {
            $options->put(self::UNKNOWN_CITY, "Sin especificar ({$unknownCount})");
        }

        return $options->all();
    }

    private function applyDateFilter(Builder $query, ?string $date): void
    {
        if ($date === null) {
            return;
        }

        if ($date === 'today') {
            $query->whereDate('created_at', today());

            return;
        }

        try {
            $query->whereDate('created_at', Carbon::parse($date)->format('Y-m-d'));
        } catch (Throwable) {
            // Invalid date: ignore the filter.
        }
    }
}
