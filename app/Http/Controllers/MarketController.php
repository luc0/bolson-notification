<?php

namespace App\Http\Controllers;

use App\Domains\Locations\Enums\City;
use App\Domains\Market\Contracts\PriceSeries;
use App\Domains\Market\Enums\Currency;
use App\Domains\Market\Queries\PriceHistory;
use App\Domains\Market\Support\PriceSeriesRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Análisis section: monthly price charts of every series (admins only).
 */
class MarketController extends Controller
{
    public function index(Request $request, PriceSeriesRegistry $registry): View
    {
        $history = new PriceHistory(
            city: City::tryFrom($request->string('city')->toString()),
            currency: Currency::tryFrom($request->string('currency')->toString()) ?? Currency::Ars,
        );

        return view('market.index', [
            'charts' => array_map(fn (PriceSeries $series) => $history->chart($series), $registry->all()),
            'dollar' => config('market.dollar'),
            'filterChips' => $this->filterChips($history),
        ]);
    }

    /**
     * @return array<string, array<int, array{label: string, url: string, active: bool}>>
     */
    private function filterChips(PriceHistory $history): array
    {
        $url = fn (?City $city, Currency $currency) => route('market.index', array_filter([
            'city' => $city?->value,
            'currency' => $currency === Currency::Ars ? null : $currency->value,
        ]), absolute: false);

        $comarcaChip = ['label' => 'Toda la Comarca', 'url' => $url(null, $history->currency), 'active' => $history->city === null];
        $cityChips = array_map(fn (City $city) => [
            'label' => $city->label(),
            'url' => $url($city, $history->currency),
            'active' => $history->city === $city,
        ], City::cases());

        return [
            'cities' => [$comarcaChip, ...$cityChips],
            'currency' => array_map(fn (Currency $currency) => [
                'label' => $currency->label(),
                'url' => $url($history->city, $currency),
                'active' => $history->currency === $currency,
            ], Currency::cases()),
        ];
    }
}
