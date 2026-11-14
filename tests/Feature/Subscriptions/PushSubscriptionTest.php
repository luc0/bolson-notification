<?php

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function pushSubscriptionPayload(string $endpoint = 'https://push.example.com/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key']];
}

function subscriberWithPush(string $endpoint, ?User $user = null): Subscriber
{
    $subscriber = Subscriber::create(['user_id' => $user?->id, 'expires_at' => now()->addMonth()]);
    $subscriber->updatePushSubscription($endpoint, 'p256dh-key', 'auth-key');

    return $subscriber;
}

it('requires a logged in member to subscribe', function () {
    $this->postJson('/push-subscriptions', pushSubscriptionPayload())->assertUnauthorized();

    expect(Subscriber::count())->toBe(0);
});

it('links the push subscription to the logged in member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/push-subscriptions', pushSubscriptionPayload())->assertOk();

    $subscriber = Subscriber::sole();
    expect($subscriber->user->is($user))->toBeTrue()
        ->and($subscriber->pushSubscriptions)->toHaveCount(1)
        ->and(DB::table('push_subscriptions')->value('subscribable_type'))->toBe('subscriber');
});

it('validates the push subscription', function () {
    $this->actingAs(User::factory()->create())
        ->postJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

it('keeps one subscriber per member across browsers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/push-subscriptions', pushSubscriptionPayload('https://push.example.com/phone'));
    $this->actingAs($user)->postJson('/push-subscriptions', pushSubscriptionPayload('https://push.example.com/laptop'));
    $this->actingAs($user)->postJson('/push-subscriptions', pushSubscriptionPayload('https://push.example.com/laptop'));

    expect(Subscriber::count())->toBe(1)
        ->and(Subscriber::sole()->pushSubscriptions)->toHaveCount(2);
});

it('moves the browser to the member that subscribes it last', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $this->actingAs($firstUser)->postJson('/push-subscriptions', pushSubscriptionPayload());
    $this->actingAs($secondUser)->postJson('/push-subscriptions', pushSubscriptionPayload());

    expect(Subscriber::forUser($firstUser)->pushSubscriptions)->toHaveCount(0)
        ->and(Subscriber::forUser($secondUser)->pushSubscriptions)->toHaveCount(1);
});

it('unsubscribes only the member own browser', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    subscriberWithPush('https://push.example.com/other', $otherUser);
    $this->actingAs($user)->postJson('/push-subscriptions', pushSubscriptionPayload('https://push.example.com/mine'));

    $this->actingAs($user)->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/mine'])->assertOk();
    $this->actingAs($user)->deleteJson('/push-subscriptions', ['endpoint' => 'https://push.example.com/other'])->assertOk();

    expect(DB::table('push_subscriptions')->pluck('endpoint')->all())->toBe(['https://push.example.com/other']);
});

it('removes the subscriber and its push subscriptions when the member deletes the account', function () {
    $user = User::factory()->create();
    subscriberWithPush('https://push.example.com/abc', $user);

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    expect(Subscriber::count())->toBe(0)
        ->and(DB::table('push_subscriptions')->count())->toBe(0);
});

it('notifies every subscriber with a push subscription about new rentals', function () {
    Notification::fake();
    $memberSubscriber = subscriberWithPush('https://push.example.com/1', User::factory()->create());
    $legacySubscriber = subscriberWithPush('https://push.example.com/2');
    $withoutPush = Subscriber::create(['user_id' => User::factory()->create()->id]);

    expect(app(NewRentalsNotifier::class)->notifyNewRentals(3))->toBe(2);

    Notification::assertSentTo([$memberSubscriber, $legacySubscriber], NewRentalsNotification::class, fn ($notification) => $notification->body === 'Hoy hay 3 alquileres nuevos!');
    Notification::assertNotSentTo($withoutPush, NewRentalsNotification::class);
});

it('does not notify when there are no new rentals', function () {
    Notification::fake();
    subscriberWithPush('https://push.example.com/1');

    expect(app(NewRentalsNotifier::class)->notifyNewRentals(0))->toBe(0);
    Notification::assertNothingSent();
});
