<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Models\Subscriber;
use App\Http\Requests\ProfileUpdateRequest;
use App\Integrations\Telegram\TelegramClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, TelegramClient $telegram): View
    {
        $subscriber = Subscriber::forUser($request->user());

        return view('profile.edit', [
            'user' => $request->user(),
            'subscriber' => $subscriber,
            'telegramLink' => $telegram->isConfigured() ? $telegram->startLink($subscriber->telegramLinkToken()) : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
