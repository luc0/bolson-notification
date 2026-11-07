<?php

use App\Domains\Lands\Models\Land;
use App\Domains\Members\Models\User;

function createLand(string $link, array $attributes = []): Land
{
    return Land::create([
        'source' => $link, 'content' => "Land {$link}", 'site_path' => 'https://example.com/terrenos', 'active' => true,
        ...$attributes,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::create(['phone' => '2944000000', 'password' => 'secret']));
});

it('requires login', function () {
    auth()->logout();

    $this->get('/terrenos')->assertRedirect('/login');
});

it('shows available lands with area, price and price per m²', function () {
    createLand('https://example.com/1', ['price_amount' => 36000, 'price_currency' => 'USD', 'area' => 1200, 'location' => 'Mallín Ahogado']);
    createLand('https://example.com/inactive', ['active' => false]);

    $this->get('/terrenos')
        ->assertOk()
        ->assertSee('Terrenos en venta')
        ->assertSee('Land https://example.com/1')
        ->assertSee('1.200 m²')
        ->assertSee('U$S 36.000')
        ->assertSee('U$S 30/m²')
        ->assertSee('Mallín Ahogado')
        ->assertDontSee('Land https://example.com/inactive');
});

it('shows unavailable lands when requested', function () {
    createLand('https://example.com/inactive', ['active' => false]);

    $this->get('/terrenos?include_inactive=1')
        ->assertOk()
        ->assertSee('Land https://example.com/inactive')
        ->assertSee('No disponible</span>', false);
});

it('sorts by price with dollars first and unpriced lands last', function () {
    createLand('https://example.com/no-price');
    createLand('https://example.com/pesos', ['price_amount' => 40000000, 'price_currency' => 'ARS']);
    createLand('https://example.com/expensive', ['price_amount' => 90000, 'price_currency' => 'USD']);
    createLand('https://example.com/cheap', ['price_amount' => 20000, 'price_currency' => 'USD']);

    $this->get('/terrenos?sort=price_asc')->assertSeeInOrder([
        'Land https://example.com/cheap', 'Land https://example.com/expensive',
        'Land https://example.com/pesos', 'Land https://example.com/no-price',
    ]);
});

it('sorts by area with unknown areas last', function () {
    createLand('https://example.com/no-area');
    createLand('https://example.com/small', ['area' => 500]);
    createLand('https://example.com/big', ['area' => 25000]);

    $this->get('/terrenos?sort=area_desc')
        ->assertSee('2,5 ha')
        ->assertSeeInOrder(['Land https://example.com/big', 'Land https://example.com/small', 'Land https://example.com/no-area']);
});

it('has a Terrenos tab in the navigation', function () {
    $this->get('/')->assertSee(route('lands.index'), false)->assertSee('Terrenos');
});
