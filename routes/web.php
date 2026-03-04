<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('users.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::view('/users', 'users')->middleware('auth')->name('users.index');
Route::get('/profile', function () {
    return view('profile', ['user' => auth()->user()]);
})->middleware('auth')->name('profile.show');
Route::view('/docs', 'docs')->name('docs.index');

Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/link', [GoogleAuthController::class, 'redirectForLink'])->name('auth.google.link');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::prefix('api')->group(function () {
    Route::get('/me', [UserController::class, 'me'])->middleware('auth');
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('auth');
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});
