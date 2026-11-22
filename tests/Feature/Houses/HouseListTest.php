<?php

use App\Domains\Houses\Models\House;
use App\Domains\Locations\Enums\City;
use App\Domains\Members\Models\User;

function createHouse(string $link, array $attributes = []): House
{
    return House::create([
        'source' => $link, 'content' => "House {$link}", 'site_path' => 'https://example.com/casas', 'active' => true,
        ...$attributes,
    ]);
}

beforeEach(function () {
    config(['app.admin_phones' => ['2944000000']]);
    $this->actingAs(User::create(['phone' => '2944000000', 'password' => 'secret']));
});

it('is only for admins: other members get 403 and no tab', function () {
    $this->actingAs(User::create(['phone' => '2944111111', 'password' => 'secret']));

    $this->get('/casas')->assertForbidden();
    $this->get('/')->assertOk()->assertDontSee(route('houses.index'), false);
});

it('has a Casas tab in the navigation for admins', function () {
    $this->get('/')->assertSee(route('houses.index'), false)->assertSee('Casas');
});

it('requires login', function () {
    auth()->logout();

    $this->get('/casas')->assertRedirect('/login');
});

it('shows available houses with their specs, price and location', function () {
    createHouse('https://example.com/1', [
        'price_amount' => 120000, 'price_currency' => 'USD', 'bedrooms' => 3, 'bathrooms' => 1,
        'covered_area' => 110, 'total_area' => 15000, 'city' => City::ElBolson, 'neighborhood' => 'Mallín Ahogado',
    ]);
    createHouse('https://example.com/inactive', ['active' => false]);

    $this->get('/casas')
        ->assertOk()
        ->assertSee('Casas en venta')
        ->assertSee('House https://example.com/1')
        ->assertSee('3 dormitorios')
        ->assertSee('1 baño')
        ->assertSee('110 m² cubiertos')
        ->assertSee('Terreno de 1,5 ha')
        ->assertSee('U$S 120.000')
        ->assertSee('Mallín Ahogado, El Bolsón')
        ->assertDontSee('House https://example.com/inactive');
});

it('shows unavailable houses when requested', function () {
    createHouse('https://example.com/inactive', ['active' => false]);

    $this->get('/casas?include_inactive=1')
        ->assertOk()
        ->assertSee('House https://example.com/inactive')
        ->assertSee('No disponible</span>', false);
});

it('sorts by price with dollars first and unpriced houses last', function () {
    createHouse('https://example.com/no-price');
    createHouse('https://example.com/pesos', ['price_amount' => 90000000, 'price_currency' => 'ARS']);
    createHouse('https://example.com/expensive', ['price_amount' => 250000, 'price_currency' => 'USD']);
    createHouse('https://example.com/cheap', ['price_amount' => 80000, 'price_currency' => 'USD']);

    $this->get('/casas?sort=price_asc')->assertSeeInOrder([
        'House https://example.com/cheap', 'House https://example.com/expensive',
        'House https://example.com/pesos', 'House https://example.com/no-price',
    ]);
});

it('sorts by covered area with unknown areas last', function () {
    createHouse('https://example.com/no-area');
    createHouse('https://example.com/small', ['covered_area' => 60]);
    createHouse('https://example.com/big', ['covered_area' => 200]);

    $this->get('/casas?sort=area_desc')
        ->assertSeeInOrder(['House https://example.com/big', 'House https://example.com/small', 'House https://example.com/no-area']);
});

it('filters by city, including houses without a known city', function () {
    createHouse('https://example.com/bolson', ['city' => City::ElBolson]);
    createHouse('https://example.com/puelo', ['city' => City::LagoPuelo]);
    createHouse('https://example.com/unknown');

    $this->get('/casas')
        ->assertSee('El Bolsón (1)')
        ->assertSee('Lago Puelo (1)')
        ->assertSee('Sin especificar (1)');

    $this->get('/casas?cities[]=lago_puelo')
        ->assertSee('House https://example.com/puelo')
        ->assertDontSee('House https://example.com/bolson')
        ->assertDontSee('House https://example.com/unknown');
});
