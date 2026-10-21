<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavouriteController;


Route::get('/',[MovieController::class, 'index'])->name('movies.index');


Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/favourites', [FavouriteController::class, 'index'])->name('favourites.index');

    Route::post('/favourites', [FavouriteController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('favourites.store');

    Route::delete('/favourites/{tmdbMovieId}', [FavouriteController::class, 'destroy'])
        ->whereNumber('tmdbMovieId')
        ->middleware('throttle:30,1')
        ->name('favourites.destroy');
});

Route::view('/contact', 'contact')->name('contact');