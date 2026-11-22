<?php

namespace App\Http\Controllers;

use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Queries\PriceHistory;
use App\Domains\Market\Support\PriceSeriesRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Análisis section: monthly price charts of every series (admins only), one line per place.
 * The places shown are picked in the browser (market-charts.js) and kept in the `places` query param.
 */
class MarketController extends Controller
{
    public function index(Request $request, PriceSeriesRegistry $registry): View
    {
        $history = new PriceHistory(
            currency: Currency::tryFrom($request->string('currency')->toString()) ?? Currency::Ars,
        );
        $places = PriceHistory::places();
        $selectedPlaces = $this->selectedPlaces($request, array_keys($places));

        return view('market.index', [
            'charts' => array_map(fn (PriceSeries $series) => $history->chart($series), $registry->all()),
            'dollar' => config('market.dollar'),
            'places' => $places,
            'selectedPlaces' => $selectedPlaces,
            'currencyChips' => $this->currencyChips($history, $selectedPlaces, count($places)),
        ]);
    }

    /**
     * Every place when the param is missing or has no known place.
     *
     * @param  string[]  $knownPlaces
     * @return string[]
     */
    private function selectedPlaces(Request $request, array $knownPlaces): array
    {
        $requested = explode(',', $request->string('places')->toString());
        $selected = array_values(array_intersect($knownPlaces, $requested));

        return $selected ?: $knownPlaces;
    }

    /**
     * @param  string[]  $selectedPlaces
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    private function currencyChips(PriceHistory $history, array $selectedPlaces, int $placesCount): array
    {
        $showsEveryPlace = count($selectedPlaces) === $placesCount;

        return array_map(fn (Currency $currency) => [
            'label' => $currency->label(),
            'url' => route('market.index', array_filter([
                'currency' => $currency === Currency::Ars ? null : $currency->value,
                'places' => $showsEveryPlace ? null : implode(',', $selectedPlaces),
            ]), absolute: false),
            'active' => $history->currency === $currency,
        ], Currency::cases());
    }
}
