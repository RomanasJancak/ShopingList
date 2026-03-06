<?php

use App\Http\Controllers\FamilyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
	Route::get('/me', [UserController::class, 'me'])->middleware('auth');
	Route::get('/users', [UserController::class, 'index']);
	Route::get('/users/{user}', [UserController::class, 'show'])->middleware('auth');
	Route::post('/users', [UserController::class, 'store']);
	Route::put('/users/{user}', [UserController::class, 'update']);
	Route::delete('/users/{user}', [UserController::class, 'destroy']);

	Route::middleware('auth')->group(function () {
		Route::get('/families', [FamilyController::class, 'index']);
		Route::post('/families', [FamilyController::class, 'store'])->middleware('permission:family.manage');
		Route::get('/families/{family}', [FamilyController::class, 'show']);
		Route::get('/families/{family}/roles', [FamilyController::class, 'roles']);
		Route::post('/families/{family}/roles', [FamilyController::class, 'storeRole'])->middleware('permission:roles.manage');
		Route::post('/families/{family}/assign-role', [FamilyController::class, 'assignRole'])->middleware('permission:roles.manage');
		Route::get('/families/{family}/permissions/me', [FamilyController::class, 'myPermissions']);
	});
});
