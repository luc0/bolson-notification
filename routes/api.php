<?php

use App\Http\Controllers\AnonPushController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Web push subscriptions
Route::post('/push/subscribe', [AnonPushController::class, 'store'])->name('push.subscribe');
Route::post('/push/unsubscribe', [AnonPushController::class, 'destroy'])->name('push.unsubscribe');

// Local only: send a test push to every subscriber / list subscriptions.
if (app()->isLocal()) {
    Route::post('/push/test', [AnonPushController::class, 'test'])->name('push.test');
    Route::get('/push/debug', fn () => DB::table('push_subscriptions')->select('id', 'endpoint', 'created_at')->get());
}
