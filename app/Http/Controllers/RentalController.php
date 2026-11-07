<?php

namespace App\Http\Controllers;

use App\Domains\Rentals\Enums\RentalSort;
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
            priceRange: $request->string('price')->toString() ?: null,
            rooms: $this->selectedValues($request, 'rooms'),
            withNaturalGas: $request->boolean('gas'),
            includeInactive: $request->boolean('include_inactive'),
            date: $request->input('date'),
            sort: $request->string('sort')->toString() ?: RentalSort::Newest->value,
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
     * One-click filters: each chip is a link to the listing with that filter changed.
     *
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    private function filterChips(RentalSearch $search): array
    {
        $current = [
            'cities' => $search->cities,
            'uses' => $search->propertyUses,
            'price' => $search->priceRange,
            'rooms' => $search->rooms,
            'gas' => $search->withNaturalGas,
            'include_inactive' => $search->includeInactive,
            'date' => $search->date,
            'sort' => $search->sort(),
        ];
        $url = fn (array $changes) => $this->listingUrl([...$current, ...$changes]);

        return [
            'cities' => $this->toggleChips('cities', 'Todas', RentalSearch::cityOptions(), $search->cities, $url),
            'uses' => $this->toggleChips('uses', 'Todos', RentalSearch::propertyUseOptions(), $search->propertyUses, $url),
            'price' => $this->singleChoiceChips('price', RentalSearch::priceRangeOptions(), $search->priceRange, $url),
            'rooms' => $this->toggleChips('rooms', null, RentalSearch::roomOptions(), $search->rooms, $url),
            'switches' => [
                $this->switchChip('gas', '🔥 Con gas natural ('.RentalSearch::naturalGasCount().')', $search->withNaturalGas, $url),
                $this->switchChip('include_inactive', 'No disponibles', $search->includeInactive, $url),
            ],
            'sort' => collect(RentalSort::cases())->map(fn (RentalSort $sort) => [
                'label' => $sort->label(),
                'url' => $url(['sort' => $sort]),
                'active' => $search->sort() === $sort,
            ])->all(),
        ];
    }

    /**
     * Only non-default values go in the URL.
     */
    private function listingUrl(array $state): string
    {
        $isDefaultSort = $state['sort'] === RentalSort::Newest;

        return route('rentals.index', array_filter([
            'cities' => array_values($state['cities']),
            'uses' => array_values($state['uses']),
            'price' => $state['price'],
            'rooms' => array_values($state['rooms']),
            'gas' => $state['gas'] ? 1 : null,
            'include_inactive' => $state['include_inactive'] ? 1 : null,
            'date' => $state['date'],
            'sort' => $isDefaultSort ? null : $state['sort']->value,
        ]), absolute: false);
    }

    /**
     * Optional "all" chip + one chip per option that adds or removes it from the selection.
     *
     * @param  array<string, string>  $options  value => label
     * @param  string[]  $selected
     */
    private function toggleChips(string $parameter, ?string $allLabel, array $options, array $selected, callable $url): array
    {
        $optionChips = collect($options)->map(function (string $label, string|int $value) use ($parameter, $selected, $url) {
            $value = (string) $value;
            $isSelected = in_array($value, $selected, true);
            $toggled = $isSelected ? array_diff($selected, [$value]) : [...$selected, $value];

            return ['label' => $label, 'url' => $url([$parameter => $toggled]), 'active' => $isSelected];
        })->values()->all();

        if ($allLabel === null) {
            return $optionChips;
        }

        return [['label' => $allLabel, 'url' => $url([$parameter => []]), 'active' => empty($selected)], ...$optionChips];
    }

    /**
     * One option at a time: clicking the active one clears it.
     *
     * @param  array<string, string>  $options  value => label
     */
    private function singleChoiceChips(string $parameter, array $options, ?string $selected, callable $url): array
    {
        return collect($options)->map(function (string $label, string $value) use ($parameter, $selected, $url) {
            $isSelected = $value === $selected;

            return ['label' => $label, 'url' => $url([$parameter => $isSelected ? null : $value]), 'active' => $isSelected];
        })->values()->all();
    }

    private function switchChip(string $parameter, string $label, bool $isOn, callable $url): array
    {
        return ['label' => ($isOn ? '✓ ' : '').$label, 'url' => $url([$parameter => ! $isOn]), 'active' => $isOn];
    }
}
