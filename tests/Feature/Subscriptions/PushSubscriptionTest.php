<?php

use App\Domains\Subscriptions\Models\AnonSubscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function pushSubscriptionPayload(string $endpoint = 'https://push.example.com/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key']];
}

function subscriberWithPush(string $endpoint): AnonSubscriber
{
    $subscriber = AnonSubscriber::create(['uuid' => (string) Str::uuid()]);
    $subscriber->updatePushSubscription($endpoint, 'p256dh-key', 'auth-key');

    return $subscriber;
}

it('stores a push subscription with a stable morph alias', function () {
    $this->postJson('/api/push/subscribe', pushSubscriptionPayload())->assertOk();

    expect(DB::table('push_subscriptions')->value('subscribable_type'))->toBe('anon_subscriber')
        ->and(AnonSubscriber::first()->pushSubscriptions)->toHaveCount(1);
});

it('validates the push subscription', function () {
    $this->postJson('/api/push/subscribe', ['endpoint' => 'https://push.example.com/abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

it('notifies every subscriber with a push subscription about new rentals', function () {
    Notification::fake();
    $subscribers = [subscriberWithPush('https://push.example.com/1'), subscriberWithPush('https://push.example.com/2')];
    $withoutPush = AnonSubscriber::create(['uuid' => (string) Str::uuid()]);

    expect(app(NewRentalsNotifier::class)->notifyNewRentals(3))->toBe(2);

    Notification::assertSentTo($subscribers, NewRentalsNotification::class, fn ($notification) => $notification->body === 'Hoy hay 3 alquileres nuevos!');
    Notification::assertNotSentTo($withoutPush, NewRentalsNotification::class);
});

it('does not notify when there are no new rentals', function () {
    Notification::fake();
    subscriberWithPush('https://push.example.com/1');

    expect(app(NewRentalsNotifier::class)->notifyNewRentals(0))->toBe(0);
    Notification::assertNothingSent();
});

it('reuses the subscriber when the same browser subscribes again', function () {
    $this->postJson('/api/push/subscribe', pushSubscriptionPayload())->assertOk();
    $this->postJson('/api/push/subscribe', pushSubscriptionPayload())->assertOk();

    expect(AnonSubscriber::count())->toBe(1)
        ->and(DB::table('push_subscriptions')->count())->toBe(1);
});

it('unsubscribes the browser', function () {
    $this->postJson('/api/push/subscribe', pushSubscriptionPayload())->assertOk();

    $this->postJson('/api/push/unsubscribe', ['endpoint' => 'https://push.example.com/abc'])->assertOk();

    expect(DB::table('push_subscriptions')->count())->toBe(0);
});
