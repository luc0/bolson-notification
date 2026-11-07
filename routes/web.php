<?php

use App\Http\Controllers\LandController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\TelegramPreferenceController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::middleware('auth')->group(function () {
    Route::get('/', [RentalController::class, 'index'])->name('rentals.index');
    Route::get('/terrenos', [LandController::class, 'index'])->name('lands.index');
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/telegram', [TelegramPreferenceController::class, 'update'])->name('profile.telegram.update');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

// Telegram bot updates: no session, authenticated by the webhook secret header.
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('telegram.webhook');

// Local only: send a test push to every subscriber / list subscriptions.
if (app()->isLocal()) {
    Route::post('/push-subscriptions/test', [PushSubscriptionController::class, 'test'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->name('push-subscriptions.test');
    Route::get('/push-subscriptions/debug', fn () => DB::table('push_subscriptions')->select('id', 'endpoint', 'subscribable_type', 'subscribable_id', 'created_at')->get());
}

Route::get('logs', [LogViewerController::class, 'index'])->middleware('log.auth');

require __DIR__.'/auth.php';
