<?php

use App\Domains\Market\Models\PriceStat;
use App\Domains\Members\Models\User;

function storeStat(array $attributes): PriceStat
{
    return PriceStat::create([
        'period' => '2026-10-01', 'series' => 'residential_rentals', 'currency' => 'ARS', 'unit' => 'price',
        'listings_count' => 3, 'median' => 700000, 'average' => 800000, 'p25' => 600000, 'p75' => 955000,
        ...$attributes,
    ]);
}

beforeEach(function () {
    config(['app.admin_phones' => ['2944000000']]);
    $this->actingAs(User::create(['phone' => '2944000000', 'password' => 'secret']));
});

it('is only for admins: other members get 403 and no tab', function () {
    $this->actingAs(User::create(['phone' => '2944111111', 'password' => 'secret']));

    $this->get('/analisis')->assertForbidden();
    $this->get('/')->assertOk()->assertDontSee(route('market.index'), false);
});

it('has an Análisis tab for admins', function () {
    $this->get('/')->assertSee(route('market.index'), false)->assertSee('Análisis');
});

it('shows a chart per series with the stats of the whole Comarca in pesos', function () {
    storeStat([]);
    storeStat(['city' => 'el_bolson', 'median' => 111111]);
    storeStat(['currency' => 'USD', 'median' => 636.36]);

    $response = $this->get('/analisis')->assertOk()
        ->assertSee('Alquileres para vivir')
        ->assertSee('Alquileres comerciales')
        ->assertSee('Casas en venta')
        ->assertSee('Terrenos en venta')
        ->assertSee('Todavía no hay datos.');

    $chart = collect($response->viewData('charts'))->firstWhere('key', 'residential_rentals');
    expect($chart['currency'])->toBe('ARS')
        ->and($chart['points'])->toHaveCount(1)
        ->and($chart['points'][0])->toMatchArray(['month' => 'Oct 2026', 'median' => 700000.0, 'count' => 3]);
});

it('filters by city and currency, keeping dollar-only series in dollars', function () {
    storeStat(['city' => 'el_bolson', 'currency' => 'USD', 'median' => 500]);
    storeStat(['series' => 'houses_for_sale', 'currency' => 'USD', 'city' => 'el_bolson', 'median' => 120000]);

    $response = $this->get('/analisis?city=el_bolson&currency=USD')->assertOk();
    $charts = collect($response->viewData('charts'));
    expect($charts->firstWhere('key', 'residential_rentals')['points'][0]['median'])->toBe(500.0)
        ->and($charts->firstWhere('key', 'houses_for_sale')['points'][0]['median'])->toBe(120000.0);

    $housesInPesos = collect($this->get('/analisis?city=el_bolson')->viewData('charts'))->firstWhere('key', 'houses_for_sale');
    expect($housesInPesos['currency'])->toBe('USD')->and($housesInPesos['points'])->toHaveCount(1);
});
