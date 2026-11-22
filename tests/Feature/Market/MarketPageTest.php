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
    $this->travelTo('2026-11-22');
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

it('shows a chart per series with a line for the whole Comarca and one per city with data', function () {
    storeStat([]);
    storeStat(['city' => 'el_bolson', 'median' => 111111]);
    storeStat(['city' => 'lago_puelo', 'period' => '2026-11-01', 'median' => 222222]);
    storeStat(['currency' => 'USD', 'median' => 636.36]);

    $response = $this->get('/analisis')->assertOk()
        ->assertSee('Alquileres para vivir')
        ->assertSee('Alquileres comerciales')
        ->assertSee('Casas en venta')
        ->assertSee('Terrenos en venta')
        ->assertSee('Todavía no hay datos.');

    $chart = collect($response->viewData('charts'))->firstWhere('key', 'residential_rentals');
    $lines = collect($chart['lines'])->keyBy('place');
    expect($chart['currency'])->toBe('ARS')
        ->and($chart['months'])->toBe(['Oct 2026', 'Nov 2026 (en curso)'])
        ->and($lines->keys()->all())->toBe(['comarca', 'el_bolson', 'lago_puelo'])
        ->and($lines['comarca']['label'])->toBe('Toda la Comarca')
        ->and($lines['comarca']['points'][0])->toMatchArray(['median' => 700000.0, 'count' => 3])
        ->and($lines['comarca']['points'][1])->toBeNull()
        ->and($lines['el_bolson']['points'][0]['median'])->toBe(111111.0)
        ->and($lines['lago_puelo']['points'])->toBe([null, ['median' => 222222.0, 'p25' => 600000.0, 'p75' => 955000.0, 'average' => 800000.0, 'count' => 3]]);
});

it('filters by currency, keeping dollar-only series in dollars', function () {
    storeStat(['city' => 'el_bolson', 'currency' => 'USD', 'median' => 500]);
    storeStat(['series' => 'houses_for_sale', 'currency' => 'USD', 'city' => 'el_bolson', 'median' => 120000]);

    $charts = collect($this->get('/analisis?currency=USD')->assertOk()->viewData('charts'));
    expect($charts->firstWhere('key', 'residential_rentals')['lines'][0]['points'][0]['median'])->toBe(500.0)
        ->and($charts->firstWhere('key', 'houses_for_sale')['lines'][0]['points'][0]['median'])->toBe(120000.0);

    $housesInPesos = collect($this->get('/analisis')->viewData('charts'))->firstWhere('key', 'houses_for_sale');
    expect($housesInPesos['currency'])->toBe('USD')->and($housesInPesos['lines'])->toHaveCount(1);
});

it('starts with every place selected and keeps the chosen places in the currency links', function () {
    $response = $this->get('/analisis')->assertOk();
    expect($response->viewData('selectedPlaces'))->toBe(['comarca', 'el_bolson', 'lago_puelo', 'el_hoyo', 'epuyen', 'cholila']);

    $response = $this->get('/analisis?places=lago_puelo,unknown,comarca')->assertOk();
    expect($response->viewData('selectedPlaces'))->toBe(['comarca', 'lago_puelo'])
        ->and($response->viewData('currencyChips')[1]['url'])->toBe('/analisis?currency=USD&places=comarca%2Clago_puelo');

    expect($this->get('/analisis?places=unknown')->viewData('selectedPlaces'))->toHaveCount(6);
});

it('has the lines of each size range for houses and lands, with their chips', function () {
    storeStat(['series' => 'lands_for_sale', 'currency' => 'USD', 'unit' => 'price_per_m2', 'median' => 20]);
    storeStat(['series' => 'lands_for_sale', 'currency' => 'USD', 'unit' => 'price_per_m2', 'size_range' => '0-1000', 'city' => 'el_bolson', 'median' => 35]);

    $response = $this->get('/analisis')->assertOk()
        ->assertSee('data-size-picker="lands_for_sale"', false)
        ->assertSee('Hasta 1.000 m²')
        ->assertDontSee('data-size-picker="residential_rentals"', false);

    $charts = collect($response->viewData('charts'));
    $lands = $charts->firstWhere('key', 'lands_for_sale');
    expect($lands['sizeLabel'])->toBe('Superficie')
        ->and($lands['lines'])->toHaveCount(1)
        ->and($lands['lines'][0]['points'][0]['median'])->toBe(20.0)
        ->and(collect($lands['sizes'])->pluck('key')->all())->toBe(['0-1000', '1000-5000', '5000+'])
        ->and($lands['sizes'][0]['lines'][0])->toMatchArray(['place' => 'el_bolson'])
        ->and($lands['sizes'][1]['lines'])->toBe([])
        ->and($charts->firstWhere('key', 'residential_rentals')['sizes'])->toBe([]);
});
