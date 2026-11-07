<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Recibir avisos por Telegram" checkbox of the profile.
 */
class TelegramPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        Subscriber::forUser($request->user())->update(['telegram_enabled' => $request->boolean('telegram_enabled')]);

        return redirect()->route('profile.edit')->with('status', 'telegram-updated');
    }
}
