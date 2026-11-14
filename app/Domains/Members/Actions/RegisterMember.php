<?php

namespace App\Domains\Members\Actions;

use App\Domains\Members\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a member and fires Registered (Subscriptions subscribes them, see AppServiceProvider).
 * Used by the web registration and by the admin's /registrar command on the Telegram bot.
 */
class RegisterMember
{
    /**
     * @return array<int, string>
     */
    public static function phoneRules(): array
    {
        return ['required', 'string', 'max:20', 'unique:'.User::class];
    }

    /**
     * @throws ValidationException when the phone is invalid or already registered.
     */
    public function handle(string $phone, string $password): User
    {
        Validator::make(['phone' => $phone], ['phone' => self::phoneRules()])->validate();

        $user = User::create(['phone' => $phone, 'password' => $password]);
        event(new Registered($user));

        return $user;
    }
}
