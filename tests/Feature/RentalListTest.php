<?php

use App\Models\Rental;
use App\Models\User;

function createRental(string $link, bool $active = true, ?string $createdAt = null): Rental
{
    $rental = Rental::create(['source' => $link, 'content' => "Rental {$link}", 'description' => 'desc', 'active' => $active]);
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
