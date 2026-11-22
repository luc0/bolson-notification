<?php

namespace App\Http\Controllers;

use App\Domains\Houses\Enums\HouseSort;
use App\Domains\Houses\Queries\HouseSearch;
use App\Http\Controllers\Concerns\BuildsFilterChips;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HouseController extends Controller
{
    use BuildsFilterChips;

    public function index(Request $request): View
    {
        $search = new HouseSearch(
            cities: array_values(array_filter((array) $request->input('cities', []), 'is_string')),
            includeInactive: $request->boolean('include_inactive'),
            sort: $request->string('sort')->toString() ?: HouseSort::Newest->value,
        );

        return view('houses.index', [
            'houses' => $search->houses(),
            'filterChips' => $this->filterChips($search),
        ]);
    }

    /**
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    private function filterChips(HouseSearch $search): array
    {
        $current = ['cities' => $search->cities, 'include_inactive' => $search->includeInactive, 'sort' => $search->sort()];
        $url = fn (array $changes) => $this->listingUrl([...$current, ...$changes]);

        return [
            'cities' => $this->toggleChips('cities', 'Todas', HouseSearch::cityOptions(), $search->cities, $url),
            // Newest is the default: no chip for it, clicking the active sort goes back to it.
            'sort' => collect([HouseSort::PriceAsc, HouseSort::PriceDesc, HouseSort::AreaDesc])->map(fn (HouseSort $sort) => [
                'label' => $sort->label(),
                'url' => $url(['sort' => $search->sort() === $sort ? HouseSort::Newest : $sort]),
                'active' => $search->sort() === $sort,
            ])->all(),
            'include_inactive' => [$this->switchChip('include_inactive', 'No disponibles', $search->includeInactive, $url)],
        ];
    }

    /**
     * Only non-default values go in the URL.
     */
    private function listingUrl(array $state): string
    {
        $isDefaultSort = $state['sort'] === HouseSort::Newest;

        return route('houses.index', array_filter([
            'cities' => array_values($state['cities']),
            'include_inactive' => $state['include_inactive'] ? 1 : null,
            'sort' => $isDefaultSort ? null : $state['sort']->value,
        ]), absolute: false);
    }
}
