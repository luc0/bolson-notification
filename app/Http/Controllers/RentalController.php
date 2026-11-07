<?php

namespace App\Http\Controllers;

use App\Domains\Rentals\Queries\RentalSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    public function index(Request $request): View
    {
        $search = new RentalSearch(
            cities: $this->selectedValues($request, 'cities'),
            propertyUses: $this->selectedValues($request, 'uses'),
            includeInactive: $request->boolean('include_inactive'),
            date: $request->input('date'),
        );

        return view('rentals.index', [
            'rentals' => $search->rentals(),
            'filterChips' => $this->filterChips($search),
        ]);
    }

    /**
     * @return string[]
     */
    private function selectedValues(Request $request, string $parameter): array
    {
        return array_values(array_filter((array) $request->input($parameter, []), 'is_string'));
    }

    /**
     * One-click filters: each chip is a link to the listing with that filter toggled.
     *
     * @return array{cities: array, uses: array, inactive: array{label: string, url: string, active: bool}}
     */
    private function filterChips(RentalSearch $search): array
    {
        $url = fn (array $overrides) => route('rentals.index', array_filter([
            'cities' => array_values($overrides['cities'] ?? $search->cities),
            'uses' => array_values($overrides['uses'] ?? $search->propertyUses),
            'include_inactive' => ($overrides['include_inactive'] ?? $search->includeInactive) ? 1 : null,
            'date' => $search->date,
        ]), absolute: false);

        return [
            'cities' => $this->toggleChips('cities', 'Todas', RentalSearch::cityOptions(), $search->cities, $url),
            'uses' => $this->toggleChips('uses', 'Todos', RentalSearch::propertyUseOptions(), $search->propertyUses, $url),
            'inactive' => [
                'label' => 'No disponibles',
                'url' => $url(['include_inactive' => ! $search->includeInactive]),
                'active' => $search->includeInactive,
            ],
        ];
    }

    /**
     * "All" chip + one chip per option that adds or removes it from the selection.
     *
     * @param  array<string, string>  $options  value => label
     * @param  string[]  $selected
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function toggleChips(string $parameter, string $allLabel, array $options, array $selected, callable $url): array
    {
        $allChip = ['label' => $allLabel, 'url' => $url([$parameter => []]), 'active' => empty($selected)];

        $optionChips = collect($options)->map(function (string $label, string $value) use ($parameter, $selected, $url) {
            $isSelected = in_array($value, $selected, true);
            $toggled = $isSelected ? array_diff($selected, [$value]) : [...$selected, $value];

            return ['label' => $label, 'url' => $url([$parameter => $toggled]), 'active' => $isSelected];
        })->values()->all();

        return [$allChip, ...$optionChips];
    }
}
