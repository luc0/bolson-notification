<?php

namespace App\Http\Controllers;

use App\Domains\Members\Models\User;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Enums\RentalSort;
use App\Domains\Rentals\Queries\RentalSearch;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Http\Controllers\Concerns\BuildsFilterChips;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    use BuildsFilterChips;

    /**
     * Without a `uses` param the listing shows rentals to live in; `uses=all` shows every use.
     */
    private const DEFAULT_USES = [PropertyUse::Residential->value];

    private const ALL_USES = 'all';

    public function index(Request $request): View
    {
        $search = new RentalSearch(
            cities: $this->selectedValues($request, 'cities'),
            propertyUses: $this->selectedUses($request),
            priceRange: $request->string('price')->toString() ?: null,
            rooms: $this->selectedValues($request, 'rooms'),
            withNaturalGas: $request->boolean('gas'),
            includeInactive: $request->boolean('include_inactive'),
            date: $request->input('date'),
            sort: $request->string('sort')->toString() ?: RentalSort::Newest->value,
            addedBefore: $this->freePlanCutoff($request->user()),
        );

        return view('rentals.index', [
            'rentals' => $search->rentals(),
            'freePlanDelayDays' => $search->addedBefore === null ? null : (int) config('subscriptions.free_delay_days'),
            'hiddenRecentCount' => $search->hiddenRecentCount(),
            'filterChips' => $this->filterChips($search),
            'moreFiltersCount' => $this->moreFiltersCount($search),
        ]);
    }

    /**
     * Without an active subscription only rentals added free_delay_days ago or more are shown.
     * Admins always see everything. null: no delay.
     */
    private function freePlanCutoff(User $user): ?CarbonInterface
    {
        $seesLatest = $user->isAdmin() || Subscriber::forUser($user)->isActive();
        if ($seesLatest) {
            return null;
        }

        return now()->subDays((int) config('subscriptions.free_delay_days'));
    }

    /**
     * Active filters inside "Más filtros" (the default use doesn't count), so it opens when there's any.
     */
    private function moreFiltersCount(RentalSearch $search): int
    {
        $isCustomUse = $search->propertyUses !== self::DEFAULT_USES;

        return count(array_filter([$isCustomUse, $search->priceRange !== null, ! empty($search->rooms), $search->withNaturalGas]));
    }

    /**
     * @return string[]
     */
    private function selectedUses(Request $request): array
    {
        if (! $request->has('uses')) {
            return self::DEFAULT_USES;
        }

        $isAllUses = $request->input('uses') === self::ALL_USES;
        if ($isAllUses) {
            return [];
        }

        return $this->selectedValues($request, 'uses');
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
            'cities' => $this->toggleChips('cities', 'Todas', $search->cityOptions(), $search->cities, $url),
            'uses' => $this->toggleChips('uses', 'Todos', $search->propertyUseOptions(), $search->propertyUses, $url),
            'price' => $this->singleChoiceChips('price', $search->priceRangeOptions(), $search->priceRange, $url),
            'rooms' => $this->toggleChips('rooms', null, $search->roomOptions(), $search->rooms, $url),
            'gas' => [$this->switchChip('gas', '🔥 Con gas natural ('.$search->naturalGasCount().')', $search->withNaturalGas, $url)],
            'include_inactive' => [$this->switchChip('include_inactive', 'No disponibles', $search->includeInactive, $url)],
            // Newest is the default: no chip for it, clicking the active price sort goes back to it.
            'sort' => collect([RentalSort::PriceAsc, RentalSort::PriceDesc])->map(fn (RentalSort $sort) => [
                'label' => $sort->label(),
                'url' => $url(['sort' => $search->sort() === $sort ? RentalSort::Newest : $sort]),
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
            'uses' => $this->usesParameter(array_values($state['uses'])),
            'price' => $state['price'],
            'rooms' => array_values($state['rooms']),
            'gas' => $state['gas'] ? 1 : null,
            'include_inactive' => $state['include_inactive'] ? 1 : null,
            'date' => $state['date'],
            'sort' => $isDefaultSort ? null : $state['sort']->value,
        ]), absolute: false);
    }

    /**
     * @param  string[]  $uses
     */
    private function usesParameter(array $uses): string|array|null
    {
        if (empty($uses)) {
            return self::ALL_USES;
        }

        $isDefault = $uses === self::DEFAULT_USES;

        return $isDefault ? null : $uses;
    }
}
