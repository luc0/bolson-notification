<?php

use App\Domains\Members\Models\User;
use App\Domains\Locations\Enums\City;
use App\Domains\Rentals\Enums\PropertyUse;
use App\Domains\Rentals\Models\Rental;

function createRental(string $link, bool $active = true, ?string $createdAt = null): Rental
{
    $rental = Rental::create(['source' => $link, 'content' => "Rental {$link}", 'description' => 'desc', 'active' => $active, 'property_use' => 'residential']);
    $rental->forceFill(['created_at' => $createdAt ?? now()])->save();

    return $rental;
}

beforeEach(function () {
    $this->actingAs(User::create(['phone' => '2944000000', 'password' => 'secret']));
});

it('hides inactive rentals by default', function () {
    createRental('https://example.com/active');
    createRental('https://example.com/inactive', active: false);

    $this->get('/')
        ->assertOk()
        ->assertSee('Rental https://example.com/active')
        ->assertDontSee('Rental https://example.com/inactive')
        ->assertDontSee('No disponible</span>', false);
});

it('shows inactive rentals with a badge when requested', function () {
    createRental('https://example.com/inactive', active: false);

    $this->get('/?include_inactive=1')
        ->assertOk()
        ->assertSee('Rental https://example.com/inactive')
        ->assertSee('No disponible</span>', false);
});

it('flags rentals detected 3 or more months ago as old', function () {
    createRental('https://example.com/new');
    expect(Rental::first()->isOld())->toBeFalse();

    $oldRental = createRental('https://example.com/old', createdAt: now()->subMonths(4));
    expect($oldRental->fresh()->isOld())->toBeTrue();

    $this->get('/')->assertSee('Antiguo</span>', false)->assertSee('Detectado hace 4 meses');
});

it('groups rentals into recent, old and inactive sections in that order', function () {
    createRental('https://example.com/recent');
    createRental('https://example.com/old', createdAt: now()->subMonths(4));
    createRental('https://example.com/inactive', active: false);

    $this->get('/?include_inactive=1')
        ->assertOk()
        ->assertSeeInOrder([
            'Propiedades Disponibles', 'Rental https://example.com/recent',
            'Antiguas', 'Rental https://example.com/old',
            'No disponibles', 'Rental https://example.com/inactive',
        ])
        ->assertSee('card-old', false)
        ->assertSee('card-inactive', false);
});

it('treats rented rentals as unavailable', function () {
    $rented = createRental('https://example.com/rented');
    $rented->update(['rented_at' => now()]);

    $this->get('/')->assertDontSee('Rental https://example.com/rented');

    $this->get('/?include_inactive=1')
        ->assertSeeInOrder(['No disponibles', 'Rental https://example.com/rented'])
        ->assertSee('Alquilado</span>', false);
});

it('shows clean features, the price once and friendly fallbacks', function () {
    $rental = createRental('https://example.com/1');
    $rental->update([
        'description' => "💸 precio: \$420.000\n🚿 baños: null\n🏠 0 m²\n📏 superficie: 45m²",
        'price' => '$420.000',
        'price_amount' => 420000,
        'price_currency' => 'ARS',
        'rooms' => 1,
        'bathrooms' => 0,
    ]);
    $withoutPrice = createRental('https://example.com/2');
    $withoutPrice->update(['description' => '💰 precio: N/A', 'price' => 'N/A', 'rooms' => 3]);

    $this->get('/')
        ->assertOk()
        ->assertSee('📏 superficie: 45m²')
        ->assertDontSee('0 m²')
        ->assertDontSeeText('baños: null')
        ->assertSeeText('🛏️ 1 ambiente')
        ->assertSeeText('🛏️ 3 ambientes')
        ->assertDontSee('baño')
        ->assertSee('Consultar precio')
        ->assertDontSee('N/A');

    expect(substr_count($this->get('/')->getContent(), '$420.000'))->toBe(1);
});

it('shows specs and services from the detail page', function () {
    createRental('https://example.com/1')->update([
        'covered_area' => 45, 'bedrooms' => 1, 'parking_spaces' => 1, 'age_years' => 6,
        'services' => ['electricity', 'running_water', 'natural_gas', 'sewer'],
    ]);

    $this->get('/')
        ->assertSeeText('📐 45 m² cubiertos')
        ->assertSeeText('1 dormitorio')
        ->assertSeeText('🚗 1 cochera')
        ->assertSeeText('🏗️ 6 años de antigüedad')
        ->assertSeeTextInOrder(['Servicios', '✓ Gas natural', '✓ Luz', '✓ Agua corriente', '✓ Cloacas']);
});

it('filters by city and offers only cities with rentals', function () {
    createRental('https://example.com/bolson')->update(['city' => City::ElBolson]);
    createRental('https://example.com/bolson-2')->update(['city' => City::ElBolson, 'neighborhood' => 'Mallín Ahogado']);
    createRental('https://example.com/puelo')->update(['city' => City::LagoPuelo]);
    createRental('https://example.com/unknown');

    $this->get('/')
        ->assertSeeInOrder(['El Bolsón (2)', 'Lago Puelo (1)', 'Sin especificar (1)'])
        ->assertDontSee('Cholila (');

    $this->get('/?cities[]=lago_puelo')
        ->assertSee('Rental https://example.com/puelo')
        ->assertDontSee('Rental https://example.com/bolson');

    $this->get('/?cities[]=el_bolson&cities[]=unknown')
        ->assertSee('Rental https://example.com/bolson-2')
        ->assertSee('📍 Mallín Ahogado, El Bolsón')
        ->assertSee('Rental https://example.com/unknown')
        ->assertDontSee('Rental https://example.com/puelo');
});

it('shows one-click city chips that toggle the filter', function () {
    createRental('https://example.com/bolson')->update(['city' => City::ElBolson]);
    createRental('https://example.com/puelo')->update(['city' => City::LagoPuelo]);

    $page = $this->get('/?cities[]=el_bolson')->assertOk();

    // Active chip removes the city, inactive chip adds it, "Todas" clears the filter.
    $page->assertSee('href="'.e(route('rentals.index', absolute: false)).'"', false)
        ->assertSee('href="'.e(route('rentals.index', ['cities' => ['el_bolson', 'lago_puelo']], false)).'"', false)
        ->assertSee('href="'.e(route('rentals.index', ['cities' => ['el_bolson'], 'include_inactive' => 1], false)).'"', false);

    expect($page->getContent())->toMatch('/aria-current="true"\s*>El Bolsón \(1\)/');
});

it('shows rentals to live in by default and every use with "Todos"', function () {
    createRental('https://example.com/house')->update(['city' => City::ElBolson, 'property_use' => PropertyUse::Residential]);
    createRental('https://example.com/store')->update(['city' => City::ElBolson, 'property_use' => PropertyUse::Commercial]);
    createRental('https://example.com/unknown')->update(['property_use' => null]);

    $page = $this->get('/')
        ->assertSee('Rental https://example.com/house')
        ->assertDontSee('Rental https://example.com/store')
        ->assertDontSee('Rental https://example.com/unknown')
        ->assertSeeInOrder(['Para vivir (1)', 'Local / comercial (1)'])
        ->assertSee('href="'.e(route('rentals.index', ['uses' => 'all'], false)).'"', false);
    expect($page->getContent())->toMatch('/aria-current="true"\s*>Para vivir \(1\)/');

    $this->get('/?uses=all')
        ->assertSee('Rental https://example.com/house')
        ->assertSee('Rental https://example.com/store')
        ->assertSee('Rental https://example.com/unknown')
        ->assertSee('🏪 Local / comercial');
});

it('filters by property use and combines it with the city', function () {
    createRental('https://example.com/house')->update(['city' => City::ElBolson, 'property_use' => PropertyUse::Residential]);
    createRental('https://example.com/store')->update(['city' => City::ElBolson, 'property_use' => PropertyUse::Commercial]);
    createRental('https://example.com/puelo-store')->update(['city' => City::LagoPuelo, 'property_use' => PropertyUse::Commercial]);

    $page = $this->get('/?cities[]=el_bolson&uses[]=commercial')
        ->assertSee('Rental https://example.com/store')
        ->assertDontSee('Rental https://example.com/house')
        ->assertDontSee('Rental https://example.com/puelo-store');

    // Toggling the use keeps the selected city; going back to the default use drops it from the URL.
    $page->assertSee('href="'.e(route('rentals.index', ['cities' => ['el_bolson'], 'uses' => ['commercial', 'residential']], false)).'"', false)
        ->assertSee('href="'.e(route('rentals.index', ['cities' => ['el_bolson'], 'uses' => 'all'], false)).'"', false);
    $this->get('/?uses[]=commercial&uses[]=residential')
        ->assertSee('href="'.e(route('rentals.index', ['uses' => 'all'], false)).'"', false);
});
