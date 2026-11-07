<?php

use App\Domains\Members\Models\User;
use App\Domains\Rentals\Models\Rental;

function listedRental(string $name, array $attributes = []): Rental
{
    return Rental::create(['source' => "https://example.com/{$name}", 'content' => "Rental {$name}", 'description' => '', 'active' => true, 'property_use' => 'residential', ...$attributes]);
}

function pesos(int $amount): array
{
    return ['price' => '$'.$amount, 'price_amount' => $amount, 'price_currency' => 'ARS'];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('filters by price range in pesos and by dollars', function () {
    listedRental('cheap', pesos(350_000));
    listedRental('edge', pesos(400_000));
    listedRental('expensive', pesos(1_200_000));
    listedRental('dollars', ['price' => 'U$S 550', 'price_amount' => 550, 'price_currency' => 'USD']);
    listedRental('ask', ['price' => 'Consultar']);

    $this->get('/?price=up_to_400k')->assertSee('Rental cheap')->assertDontSee('Rental edge')->assertDontSee('Rental dollars');
    $this->get('/?price=400k_600k')->assertSee('Rental edge')->assertDontSee('Rental cheap');
    $this->get('/?price=over_900k')->assertSee('Rental expensive')->assertDontSee('Rental ask');
    $this->get('/?price=usd')->assertSee('Rental dollars')->assertDontSee('Rental cheap');

    $this->get('/')->assertSeeInOrder(['Hasta $400k (1)', '$400k–600k (1)', '+$900k (1)', 'En dólares (1)'])
        ->assertDontSee('$600k–900k');
});

it('filters by rooms, grouping 4 or more', function () {
    listedRental('one', ['rooms' => 1]);
    listedRental('two', ['rooms' => 2]);
    listedRental('five', ['rooms' => 5]);
    listedRental('unknown', ['rooms' => 0]);

    $this->get('/?rooms[]=1&rooms[]=4')
        ->assertSee('Rental one')
        ->assertSee('Rental five')
        ->assertDontSee('Rental two')
        ->assertDontSee('Rental unknown');

    $this->get('/')->assertSeeInOrder(['1 amb. (1)', '2 amb. (1)', '4+ amb. (1)']);
});

it('filters rentals with natural gas', function () {
    listedRental('gas', ['services' => ['electricity', 'natural_gas']]);
    listedRental('bottled', ['services' => ['bottled_gas']]);
    listedRental('no-data');

    $this->get('/?gas=1')
        ->assertSee('Rental gas')
        ->assertDontSee('Rental bottled')
        ->assertDontSee('Rental no-data');
});

it('sorts by price: pesos, then dollars, then without price', function () {
    listedRental('mid', pesos(600_000));
    listedRental('dollars', ['price' => 'U$S 550', 'price_amount' => 550, 'price_currency' => 'USD']);
    listedRental('ask', ['price' => 'Consultar']);
    listedRental('low', pesos(300_000));

    $this->get('/?sort=price_asc')->assertSeeInOrder(['Rental low', 'Rental mid', 'Rental dollars', 'Rental ask']);
    $this->get('/?sort=price_desc')->assertSeeInOrder(['Rental mid', 'Rental low', 'Rental dollars', 'Rental ask']);

    // Only the price sorts have a chip; newest is the default.
    $page = $this->get('/')->assertSeeInOrder(['Menor precio', 'Mayor precio'])->assertDontSee('Más nuevos');
    $page->assertSee('href="'.e(route('rentals.index', ['sort' => 'price_asc'], false)).'"', false);
});

it('keeps the other filters in the chip links', function () {
    listedRental('house', [...pesos(500_000), 'rooms' => 2]);

    $page = $this->get('/?price=400k_600k&rooms[]=2&sort=price_asc');

    // Price is single choice: the active chip clears it, keeping rooms and sort.
    $page->assertSee('href="'.e(route('rentals.index', ['rooms' => ['2'], 'sort' => 'price_asc'], false)).'"', false)
        // Default sort is not in the URL.
        ->assertSee('href="'.e(route('rentals.index', ['price' => '400k_600k', 'rooms' => ['2']], false)).'"', false)
        // The gas switch adds gas=1 to the current filters.
        ->assertSee('href="'.e(route('rentals.index', ['price' => '400k_600k', 'rooms' => ['2'], 'gas' => 1, 'sort' => 'price_asc'], false)).'"', false);
});

it('hides use, price, rooms and gas under "Más filtros", opened when one is not default', function () {
    listedRental('one', ['rooms' => 1]);

    expect($this->get('/')->getContent())->toMatch('/<details class="more-filters"\s*>.*Para vivir \(1\).*<\/details>/s');
    expect($this->get('/?uses=all')->getContent())->toMatch('/<details class="more-filters"\s+open\s*>/');

    expect($this->get('/')->getContent())->toMatch('/<details class="more-filters"\s*>/')
        ->toContain('1 amb. (1)');
    expect($this->get('/?rooms[]=1')->getContent())->toMatch('/<details class="more-filters"\s+open\s*>/')
        ->toMatch('/Más filtros\s+\(1\)/');
});
