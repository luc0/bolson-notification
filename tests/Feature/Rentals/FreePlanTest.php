<?php

use App\Domains\Locations\Enums\City;
use App\Domains\Members\Models\User;
use App\Domains\Rentals\Models\Rental;
use App\Domains\Subscriptions\Models\Subscriber;

beforeEach(function () {
    config(['subscriptions.free_delay_days' => 3]);
});

function rentalAddedDaysAgo(string $link, int $days): Rental
{
    $rental = Rental::create(['source' => $link, 'content' => "Rental {$link}", 'description' => 'desc', 'active' => true, 'property_use' => 'residential', 'city' => City::ElBolson]);
    $rental->forceFill(['created_at' => now()->subDays($days)->subMinute()])->save();

    return $rental;
}

function memberWithoutSubscription(): User
{
    $user = User::factory()->create();
    Subscriber::forUser($user)->update(['expires_at' => now()->subDay()]);

    return $user;
}

it('shows only rentals added 3 or more days ago without an active subscription', function () {
    rentalAddedDaysAgo('https://example.com/yesterday', 1);
    rentalAddedDaysAgo('https://example.com/two-days', 2);
    rentalAddedDaysAgo('https://example.com/three-days', 3);

    $this->actingAs(memberWithoutSubscription())->get('/')
        ->assertOk()
        ->assertDontSee('Rental https://example.com/yesterday')
        ->assertDontSee('Rental https://example.com/two-days')
        ->assertSee('Rental https://example.com/three-days')
        ->assertSee('ves los alquileres publicados hace 3 días o más')
        ->assertSee('Hay 2 alquileres nuevos de los últimos 3 días que no estás viendo')
        ->assertSee('El Bolsón (1)');
});

it('shows every rental to active subscribers, without the free plan notice', function () {
    rentalAddedDaysAgo('https://example.com/yesterday', 1);

    $this->actingAs(subscribedMember())->get('/')
        ->assertOk()
        ->assertSee('Rental https://example.com/yesterday')
        ->assertDontSee('Sin suscripción activa');
});

it('shows every rental to admins even without a subscription', function () {
    config(['app.admin_phones' => ['2944999999']]);
    rentalAddedDaysAgo('https://example.com/yesterday', 1);

    $this->actingAs(User::factory()->create(['phone' => '2944999999']))->get('/')
        ->assertOk()
        ->assertSee('Rental https://example.com/yesterday');
});

it('offers to enable notifications only to active subscribers', function () {
    $this->actingAs(subscribedMember())->get('/')->assertSee('Activar notificaciones!');
    $this->actingAs(memberWithoutSubscription())->get('/')->assertDontSee('Activar notificaciones!');
});
