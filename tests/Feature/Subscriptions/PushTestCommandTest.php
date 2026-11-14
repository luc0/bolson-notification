<?php

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    config(['webpush.vapid.public_key' => 'public', 'webpush.vapid.private_key' => 'private']);
});

it('sends a test push to every browser of the member and reports each one', function () {
    $user = User::factory()->create(['phone' => '2944123456']);
    $subscriber = Subscriber::forUser($user);
    $subscriber->updatePushSubscription('https://push.example.com/phone', 'p256dh-key', 'auth-key');
    $subscriber->updatePushSubscription('https://push.example.com/old-laptop', 'p256dh-key', 'auth-key');

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->twice();
    $webPush->shouldReceive('flush')->once()->andReturnUsing(function () {
        yield new MessageSentReport(new Request('POST', 'https://push.example.com/phone'), new Response(201));
        yield new MessageSentReport(new Request('POST', 'https://push.example.com/old-laptop'), new Response(410), false, 'Gone');
    });
    // The package gives the channel its own WebPush (contextual binding): replace the whole channel.
    app()->instance(WebPushChannel::class, new WebPushChannel($webPush, app(ReportHandler::class)));

    $this->artisan('push:send-test', ['phone' => '2944123456'])
        ->expectsOutput('Sent to 1 of 2 browsers of 2944123456.')
        ->expectsOutput('Failed: Gone (expired, removed)')
        ->assertFailed();

    expect($subscriber->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example.com/phone']);
});

it('fails without VAPID keys, for an unknown phone or without subscribed browsers', function () {
    User::factory()->create(['phone' => '2944123456']);

    $this->artisan('push:send-test', ['phone' => '000'])->assertFailed();
    $this->artisan('push:send-test', ['phone' => '2944123456'])
        ->expectsOutputToContain('Activar notificaciones')
        ->assertFailed();

    config(['webpush.vapid.public_key' => null]);
    $this->artisan('push:send-test', ['phone' => '2944123456'])
        ->expectsOutputToContain('VAPID')
        ->assertFailed();
});
