<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FavouriteController;
use App\Http\Controllers\MovieController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MovieController::class, 'index'])->name('movies.index');

Route::get('/search', [MovieController::class, 'search'])
    ->middleware('throttle:30,1')
    ->name('movies.search');

Route::get('/search/autocomplete', [MovieController::class, 'autocomplete'])
    ->middleware('throttle:120,1')
    ->name('movies.autocomplete');

Route::get('/movies/{tmdbMovieId}', [MovieController::class, 'show'])
    ->whereNumber('tmdbMovieId')
    ->middleware('throttle:60,1')
    ->name('movies.show');

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
