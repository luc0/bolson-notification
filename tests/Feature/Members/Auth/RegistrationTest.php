<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'phone' => '+5492944000000',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('rentals.index', absolute: false));
});

test('phone must be unique', function () {
    $existingUser = App\Domains\Members\Models\User::factory()->create();

    $this->post('/register', [
        'phone' => $existingUser->phone,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('registration requires phone and matching passwords', function (array $data, string $invalidField) {
    $this->post('/register', $data)->assertSessionHasErrors($invalidField);

    $this->assertGuest();
})->with([
    'missing phone' => [['password' => 'password', 'password_confirmation' => 'password'], 'phone'],
    'passwords do not match' => [['phone' => '+5492944000001', 'password' => 'password', 'password_confirmation' => 'other'], 'password'],
    'missing password' => [['phone' => '+5492944000001'], 'password'],
]);
