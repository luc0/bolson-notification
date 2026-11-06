<?php

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use Illuminate\Support\Facades\Notification;

/*
 * End to end: a new member registers, enables notifications and gets notified
 * when rentals:analyze finds new rentals (FakeRentalScraper returns 3).
 */

beforeEach(function () {
    config(['mock.scrape_mock' => true]);
    Notification::fake();
});

it('notifies a newly registered member about new rentals', function () {
    $this->post('/register', [
        'phone' => '+5492944000000',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('rentals.index', absolute: false));

    $this->postJson('/push-subscriptions', [
        'endpoint' => 'https://push.example.com/new-member',
        'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
    ])->assertOk();

    $this->artisan('rentals:analyze')->assertSuccessful();

    $subscriber = Subscriber::forUser(User::where('phone', '+5492944000000')->sole());
    Notification::assertSentTo($subscriber, NewRentalsNotification::class, fn ($notification) => $notification->body === 'Hoy hay 3 alquileres nuevos!');
});

it('does not notify members that did not enable notifications', function () {
    User::factory()->create();

    $this->artisan('rentals:analyze')->assertSuccessful();

    Notification::assertNothingSent();
});
