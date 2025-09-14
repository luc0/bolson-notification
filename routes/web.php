<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use Illuminate\Support\Facades\Route;

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/', [RentalController::class, 'index'])->name('rentals.index');
    
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

//Route::get('/rentals', function () {
//    return view('rentals.index');
//});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::get('logs', [LogViewerController::class, 'index'])->middleware('log.auth');

require __DIR__.'/auth.php';
