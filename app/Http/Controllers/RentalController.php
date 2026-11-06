<?php

namespace App\Http\Controllers;

use App\Domains\Rentals\Enums\Location;
use App\Domains\Rentals\Models\Rental;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        $includeInactive = $request->boolean('include_inactive');
        $locations = array_filter((array) $request->input('locations', []));

        $rentals = Rental::query()
            ->when(!$includeInactive, fn (Builder $query) => $query->available())
            ->when(!empty($locations), fn (Builder $query) => $query->byLocation($locations))
            ->tap(fn (Builder $query) => $this->applyDateFilter($query, $request->input('date')))
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->get();

        $locationOptions = $this->getLocationOptions();

        return view('rentals.index', compact('rentals', 'locationOptions', 'includeInactive'));
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

    private function getLocationOptions(): array
    {
        // Enum locations + custom ones found in the DB.
        $enumLocations = Location::options();
        $customLocations = Rental::available()
            ->whereNotNull('location')
            ->whereNotIn('location', Location::values())
            ->distinct()
            ->pluck('location', 'location')
            ->toArray();

        return array_merge($enumLocations, $customLocations);
    }
}
