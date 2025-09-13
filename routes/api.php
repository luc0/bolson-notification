<?php

use App\Http\Controllers\AnonPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

# webpush subscriptions
Route::post('/push/subscribe',   [AnonPushController::class, 'store'])->name('push.subscribe');
Route::post('/push/unsubscribe', [AnonPushController::class, 'destroy'])->name('push.unsubscribe');

// ruta de prueba para enviar a todos
Route::post('/push/test', [AnonPushController::class, 'test'])->name('push.test');

Route::get('/push/debug', fn() =>
response()->json(\DB::table('push_subscriptions')->select('id','endpoint','created_at')->get())
);
