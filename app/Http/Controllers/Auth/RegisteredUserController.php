<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Members\Actions\RegisterMember;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, RegisterMember $registerMember): RedirectResponse
    {
        $request->validate([
            'phone' => RegisterMember::phoneRules(),
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $registerMember->handle($request->phone, $request->password);

        Auth::login($user);

        return redirect(route('rentals.index', absolute: false));
    }
}
