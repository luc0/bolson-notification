<?php

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Notifications\NewRentalsNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionExpiringNotification;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'subscriptions.duration_days' => 30,
        'subscriptions.expiry_reminder.days_before' => 3,
        'subscriptions.expiry_reminder.active_within_days' => 5,
    ]);
    $this->travelTo(now()->startOfDay()->setHour(12));
});

function subscriberExpiringIn(int $days, ?string $lastSeenDaysAgo = '1', bool $withPush = true): Subscriber
{
    $user = User::factory()->create(['last_seen_at' => $lastSeenDaysAgo === null ? null : now()->subDays((int) $lastSeenDaysAgo)]);
    $subscriber = Subscriber::forUser($user);
    $subscriber->update(['expires_at' => now()->addDays($days)]);
    if ($withPush) {
        $subscriber->updatePushSubscription("https://push.example.com/{$user->id}", 'p256dh-key', 'auth-key');
    }

    return $subscriber;
}

it('subscribes the member for one period when registering', function () {
    $this->post('/register', ['phone' => '2944123456', 'password' => 'password', 'password_confirmation' => 'password']);

    $subscriber = Subscriber::forUser(User::where('phone', '2944123456')->sole());
    expect($subscriber->isActive())->toBeTrue()
        ->and($subscriber->expires_at->equalTo(now()->addDays(30)))->toBeTrue();
});

it('extends from the current expiry when renewing early, and from now when expired', function () {
    $active = subscriberExpiringIn(10);
    $active->update(['expiry_reminder_sent_at' => now()]);
    $expired = subscriberExpiringIn(-10);

    $active->extend();
    $expired->extend(7);

    expect($active->expires_at->equalTo(now()->addDays(40)))->toBeTrue()
        ->and($active->expiry_reminder_sent_at)->toBeNull()
        ->and($expired->expires_at->equalTo(now()->addDays(7)))->toBeTrue();
});

it('notifies new rentals only to active subscribers', function () {
    Notification::fake();
    $active = subscriberExpiringIn(10);
    $expired = subscriberExpiringIn(-1);
    $neverSubscribed = Subscriber::create(['user_id' => User::factory()->create()->id]);
    $neverSubscribed->updatePushSubscription('https://push.example.com/never', 'p256dh-key', 'auth-key');

    expect(app(NewRentalsNotifier::class)->notifyNewRentals(2))->toBe(1);

    Notification::assertSentTo($active, NewRentalsNotification::class);
    Notification::assertNotSentTo([$expired, $neverSubscribed], NewRentalsNotification::class);
});

it('renews a subscription by phone from the command', function () {
    $subscriber = subscriberExpiringIn(-5);

    $this->artisan('subscriptions:extend', ['phone' => $subscriber->user->phone])->assertSuccessful();
    expect($subscriber->fresh()->expires_at->equalTo(now()->addDays(30)))->toBeTrue();

    $this->artisan('subscriptions:extend', ['phone' => $subscriber->user->phone, '--days' => 15])->assertSuccessful();
    expect($subscriber->fresh()->expires_at->equalTo(now()->addDays(45)))->toBeTrue();
});

it('fails to renew an unknown phone or with invalid days', function () {
    $user = User::factory()->create();

    $this->artisan('subscriptions:extend', ['phone' => '000'])->assertFailed();
    $this->artisan('subscriptions:extend', ['phone' => $user->phone, '--days' => 'abc'])->assertFailed();
    $this->artisan('subscriptions:extend', ['phone' => $user->phone, '--days' => '0'])->assertFailed();
});

it('reminds once the members that still use the app and whose subscription expires soon', function () {
    Notification::fake();
    $expiringSoon = subscriberExpiringIn(2);
    $notUsingTheApp = subscriberExpiringIn(2, lastSeenDaysAgo: '6');
    $neverSeen = subscriberExpiringIn(2, lastSeenDaysAgo: null);
    $notYet = subscriberExpiringIn(10);
    $expired = subscriberExpiringIn(-1);
    $withoutChannels = subscriberExpiringIn(2, withPush: false);

    $this->artisan('subscriptions:remind-expiring')->assertSuccessful();
    $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

    Notification::assertSentToTimes($expiringSoon, SubscriptionExpiringNotification::class, 1);
    Notification::assertNotSentTo([$notUsingTheApp, $neverSeen, $notYet, $expired, $withoutChannels], SubscriptionExpiringNotification::class);
    expect($expiringSoon->fresh()->expiry_reminder_sent_at)->not->toBeNull();
});

it('reminds again in the next period after renewing', function () {
    Notification::fake();
    $subscriber = subscriberExpiringIn(2);
    $this->artisan('subscriptions:remind-expiring');

    $subscriber->fresh()->extend();
    $this->travel(30)->days(); // 2 days before the new expiry
    $subscriber->user->forceFill(['last_seen_at' => now()])->save();
    $this->artisan('subscriptions:remind-expiring');

    Notification::assertSentToTimes($subscriber, SubscriptionExpiringNotification::class, 2);
});

it('tells the expiry date in the reminder', function () {
    $subscriber = subscriberExpiringIn(2);

    $message = (new SubscriptionExpiringNotification)->toTelegram($subscriber);

    expect($message['text'])->toContain('vence el '.now()->addDays(2)->format('d/m'))
        ->and($message['button_url'])->toBe(url('/profile'));
});

it('shows the subscription status in the profile', function () {
    $active = subscriberExpiringIn(10);
    $expired = subscriberExpiringIn(-3);

    $this->actingAs($active->user)->get('/profile')->assertOk()
        ->assertSee('Activa hasta el <strong>'.now()->addDays(10)->format('d/m/Y').'</strong>', false);
    $this->actingAs($expired->user)->get('/profile')->assertOk()
        ->assertSee('Venció el <strong>'.now()->subDays(3)->format('d/m/Y').'</strong>', false);
});

it('stores when the member last opened the app, at most once per hour', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile');
    $firstSeen = $user->fresh()->last_seen_at;
    $this->travel(30)->minutes();
    $this->actingAs($user->fresh())->get('/profile');
    $afterHalfAnHour = $user->fresh()->last_seen_at;
    $this->travel(31)->minutes();
    $this->actingAs($user->fresh())->get('/profile');

    expect($firstSeen->equalTo(now()->subMinutes(61)))->toBeTrue()
        ->and($afterHalfAnHour->equalTo($firstSeen))->toBeTrue()
        ->and($user->fresh()->last_seen_at->equalTo(now()))->toBeTrue();
});
