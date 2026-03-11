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
		Route::put('/families/{family}', [FamilyController::class, 'update'])->middleware('permission:family.manage');
		Route::delete('/families/{family}', [FamilyController::class, 'destroy'])->middleware('permission:family.manage');
		Route::get('/families/{family}/roles', [FamilyController::class, 'roles']);
		Route::post('/families/{family}/roles', [FamilyController::class, 'storeRole'])->middleware('permission:roles.manage');
		Route::put('/families/{family}/roles/{role}', [FamilyController::class, 'updateRole'])->middleware('permission:roles.manage');
		Route::delete('/families/{family}/roles/{role}', [FamilyController::class, 'destroyRole'])->middleware('permission:roles.manage');
		Route::post('/families/{family}/assign-role', [FamilyController::class, 'assignRole'])->middleware('permission:roles.manage');
		Route::get('/families/{family}/members', [FamilyController::class, 'members']);
		Route::post('/families/{family}/members', [FamilyController::class, 'addMember'])->middleware('permission:roles.manage');
		Route::put('/families/{family}/members/{user}', [FamilyController::class, 'updateMemberRole'])->middleware('permission:roles.manage');
		Route::delete('/families/{family}/members/{user}', [FamilyController::class, 'removeMember'])->middleware('permission:roles.manage');
		Route::get('/families/{family}/permissions/me', [FamilyController::class, 'myPermissions']);
	});
});
