<?php

use App\Domains\Members\Models\User;

function basicAuth(string $phone, string $password = 'password'): array
{
    return ['Authorization' => 'Basic '.base64_encode("{$phone}:{$password}")];
}

it('asks for credentials', function () {
    $this->get('/logs')->assertUnauthorized();
});

it('forbids members not allowed to see the logs', function () {
    $user = User::factory()->create();
    config(['app.log_viewer_phones' => []]);

    $this->get('/logs', basicAuth($user->phone))->assertForbidden();
});

it('lets allowed members see the logs', function () {
    $user = User::factory()->create();
    config(['app.log_viewer_phones' => [$user->phone]]);

    $this->get('/logs', basicAuth($user->phone))->assertOk();
});

it('rejects a wrong password', function () {
    $user = User::factory()->create();
    config(['app.log_viewer_phones' => [$user->phone]]);

    $this->get('/logs', basicAuth($user->phone, 'wrong'))->assertUnauthorized();
});
