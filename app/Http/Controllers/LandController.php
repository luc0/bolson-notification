<?php

namespace App\Http\Controllers;

use App\Domains\Lands\Enums\LandSort;
use App\Domains\Lands\Queries\LandSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandController extends Controller
{
    public function index(Request $request): View
    {
        $search = new LandSearch(
            includeInactive: $request->boolean('include_inactive'),
            sort: $request->string('sort')->toString() ?: LandSort::Newest->value,
        );

        return view('lands.index', [
            'lands' => $search->lands(),
            'filterChips' => $this->filterChips($search),
        ]);
    }

    /**
     * One-click filters: each chip is a link to the listing with that filter changed.
     *
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    private function filterChips(LandSearch $search): array
    {
        $current = ['include_inactive' => $search->includeInactive, 'sort' => $search->sort()];
        $url = fn (array $changes) => $this->listingUrl([...$current, ...$changes]);

        return [
            // Newest is the default: no chip for it, clicking the active sort goes back to it.
            'sort' => collect([LandSort::PriceAsc, LandSort::PriceDesc, LandSort::AreaDesc])->map(fn (LandSort $sort) => [
                'label' => $sort->label(),
                'url' => $url(['sort' => $search->sort() === $sort ? LandSort::Newest : $sort]),
                'active' => $search->sort() === $sort,
            ])->all(),
            'include_inactive' => [[
                'label' => ($search->includeInactive ? '✓ ' : '').'No disponibles',
                'url' => $url(['include_inactive' => ! $search->includeInactive]),
                'active' => $search->includeInactive,
            ]],
        ];
    }

    /**
     * Only non-default values go in the URL.
     */
    private function listingUrl(array $state): string
    {
        $isDefaultSort = $state['sort'] === LandSort::Newest;

        return route('lands.index', array_filter([
            'include_inactive' => $state['include_inactive'] ? 1 : null,
            'sort' => $isDefaultSort ? null : $state['sort']->value,
        ]), absolute: false);
    }
}
