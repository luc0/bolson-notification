<?php

use App\Domains\Members\Models\User;
use App\Domains\Subscriptions\Models\Subscriber;
use App\Domains\Subscriptions\Services\NewRentalsNotifier;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-token',
        'services.telegram.bot_username' => 'TechitoBot',
        'services.telegram.webhook_secret' => 'webhook-secret',
    ]);
    // Http::fake() keeps the first stub registered, so each test can change the answer here.
    $this->telegramResponse = fn () => Http::response(['ok' => true]);
    Http::fake(['api.telegram.org/*' => fn () => ($this->telegramResponse)()]);
});

function telegramUpdate(string $text, int $chatId = 555): array
{
    return ['update_id' => 1, 'message' => ['message_id' => 1, 'chat' => ['id' => $chatId], 'text' => $text]];
}

function postTelegramWebhook(array $update, string $secret = 'webhook-secret')
{
    return test()->postJson('/telegram/webhook', $update, ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
}

it('shows the connect link in the profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/profile')->assertOk();

    $token = Subscriber::forUser($user)->telegram_link_token;
    expect($token)->not->toBeNull();
    $response->assertSee('Avisos por Telegram')
        ->assertSee("https://t.me/TechitoBot?start={$token}", false)
        ->assertSee('Recibir avisos por Telegram');
});

it('hides the Telegram section when the bot is not configured', function () {
    config(['services.telegram.bot_token' => null]);

    $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->assertDontSee('Avisos por Telegram');
});

it('links the chat when the member taps Start from the profile link', function () {
    $subscriber = Subscriber::forUser(User::factory()->create());
    $token = $subscriber->telegramLinkToken();

    postTelegramWebhook(telegramUpdate("/start {$token}"))->assertOk();

    expect($subscriber->fresh())->telegram_chat_id->toBe('555')->receivesTelegram()->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottest-token/sendMessage')
        && $request['chat_id'] === '555'
        && str_contains($request['text'], 'Listo'));
});

it('moves the chat to the last subscriber that links it', function () {
    $previous = Subscriber::forUser(User::factory()->create());
    $previous->linkTelegramChat('555');
    $current = Subscriber::forUser(User::factory()->create());

    postTelegramWebhook(telegramUpdate('/start '.$current->telegramLinkToken()))->assertOk();

    expect($previous->fresh()->telegram_chat_id)->toBeNull()
        ->and($current->fresh()->telegram_chat_id)->toBe('555');
});

it('answers with the profile link for an unknown start code', function () {
    postTelegramWebhook(telegramUpdate('/start wrong-code'))->assertOk();

    expect(Subscriber::whereNotNull('telegram_chat_id')->exists())->toBeFalse();
    Http::assertSent(fn ($request) => str_contains($request['text'], route('profile.edit')));
});

it('ignores other messages', function () {
    postTelegramWebhook(telegramUpdate('hola'))->assertOk();

    Http::assertNothingSent();
});

it('rejects webhook calls without the secret', function () {
    postTelegramWebhook(telegramUpdate('/start x'), secret: 'wrong')->assertForbidden();
});

it('saves the "Recibir avisos por Telegram" checkbox', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile/telegram', ['telegram_enabled' => '0'])->assertRedirect('/profile');
    expect(Subscriber::forUser($user)->telegram_enabled)->toBeFalse();

    $this->actingAs($user)->patch('/profile/telegram', ['telegram_enabled' => '1']);
    expect(Subscriber::forUser($user)->fresh()->telegram_enabled)->toBeTrue();
});

it('sends new rentals by Telegram only to linked members with it enabled', function () {
    $enabled = Subscriber::forUser(User::factory()->create());
    $enabled->linkTelegramChat('111');
    $disabled = Subscriber::forUser(User::factory()->create());
    $disabled->linkTelegramChat('222');
    $disabled->update(['telegram_enabled' => false]);
    Subscriber::forUser(User::factory()->create()); // not linked

    $notified = app(NewRentalsNotifier::class)->notifyNewRentals(3);

    expect($notified)->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request['chat_id'] === '111' && str_contains($request['text'], '3 alquileres nuevos'));
});

it('sends the link without a button when the URL is not public https', function () {
    $subscriber = Subscriber::forUser(User::factory()->create());
    $subscriber->linkTelegramChat('111');
    $client = app(\App\Integrations\Telegram\TelegramClient::class);

    $client->sendMessage('111', 'Hola', 'Ver', 'https://localhost');
    $client->sendMessage('111', 'Hola', 'Ver', 'https://192.168.0.10/');
    $client->sendMessage('111', 'Hola', 'Ver', 'https://techito.example.com');

    $sent = Http::recorded()->map(fn ($pair) => $pair[0]['reply_markup'] ?? null)->all();
    expect($sent[0])->toBeNull()->and($sent[1])->toBeNull()
        ->and($sent[2]['inline_keyboard'][0][0]['url'])->toBe('https://techito.example.com');
});

it('unlinks the chat when the member blocked the bot', function () {
    $this->telegramResponse = fn () => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403);
    $subscriber = Subscriber::forUser(User::factory()->create());
    $subscriber->linkTelegramChat('111');

    app(NewRentalsNotifier::class)->notifyNewRentals(2);

    expect($subscriber->fresh()->telegram_chat_id)->toBeNull();
});

it('registers the webhook with the secret', function () {
    config(['app.url' => 'https://techito.example.com']);
    \Illuminate\Support\Facades\URL::forceRootUrl('https://techito.example.com');

    $this->artisan('telegram:set-webhook')->assertSuccessful();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/setWebhook')
        && $request['url'] === 'https://techito.example.com/telegram/webhook'
        && $request['secret_token'] === 'webhook-secret');
});
