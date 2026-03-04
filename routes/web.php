<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/users', 'users')->name('users.index');
Route::view('/docs', 'docs')->name('docs.index');

Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/link', [GoogleAuthController::class, 'redirectForLink'])->name('auth.google.link');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::prefix('api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
