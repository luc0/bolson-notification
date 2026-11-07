<?php

namespace App\Http\Controllers;

use App\Domains\Lands\Enums\LandSort;
use App\Domains\Lands\Queries\LandSearch;
use App\Http\Controllers\Concerns\BuildsFilterChips;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandController extends Controller
{
    use BuildsFilterChips;

    public function index(Request $request): View
    {
        $search = new LandSearch(
            cities: array_values(array_filter((array) $request->input('cities', []), 'is_string')),
            includeInactive: $request->boolean('include_inactive'),
            sort: $request->string('sort')->toString() ?: LandSort::Newest->value,
        );

        return view('lands.index', [
            'lands' => $search->lands(),
            'filterChips' => $this->filterChips($search),
        ]);
    }

    /**
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    private function filterChips(LandSearch $search): array
    {
        $current = ['cities' => $search->cities, 'include_inactive' => $search->includeInactive, 'sort' => $search->sort()];
        $url = fn (array $changes) => $this->listingUrl([...$current, ...$changes]);

        return [
            'cities' => $this->toggleChips('cities', 'Todas', LandSearch::cityOptions(), $search->cities, $url),
            // Newest is the default: no chip for it, clicking the active sort goes back to it.
            'sort' => collect([LandSort::PriceAsc, LandSort::PriceDesc, LandSort::AreaDesc])->map(fn (LandSort $sort) => [
                'label' => $sort->label(),
                'url' => $url(['sort' => $search->sort() === $sort ? LandSort::Newest : $sort]),
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
        $isDefaultSort = $state['sort'] === LandSort::Newest;

        return route('lands.index', array_filter([
            'cities' => array_values($state['cities']),
            'include_inactive' => $state['include_inactive'] ? 1 : null,
            'sort' => $isDefaultSort ? null : $state['sort']->value,
        ]), absolute: false);
    }
}
