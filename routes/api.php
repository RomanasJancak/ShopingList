<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShoppingListController;
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
		Route::get('/products', [ProductController::class, 'index']);
		Route::post('/products', [ProductController::class, 'store']);
		Route::put('/products/{product}', [ProductController::class, 'update']);
		Route::delete('/products/{product}', [ProductController::class, 'destroy']);

		Route::get('/access-control/capabilities', [AccessControlController::class, 'capabilities']);

		Route::get('/access-control/permissions', [AccessControlController::class, 'permissions'])
			->middleware('permission:permissions.view');
		Route::post('/access-control/permissions', [AccessControlController::class, 'storePermission'])
			->middleware('permission:permissions.manage');
		Route::put('/access-control/permissions/{permission}', [AccessControlController::class, 'updatePermission'])
			->middleware('permission:permissions.manage');
		Route::delete('/access-control/permissions/{permission}', [AccessControlController::class, 'destroyPermission'])
			->middleware('permission:permissions.manage');

		Route::get('/access-control/roles', [AccessControlController::class, 'roles'])
			->middleware('permission:roles.view');
		Route::post('/access-control/roles', [AccessControlController::class, 'storeRole'])
			->middleware('permission:roles.manage');
		Route::put('/access-control/roles/{role}', [AccessControlController::class, 'updateRole'])
			->middleware('permission:roles.manage');
		Route::delete('/access-control/roles/{role}', [AccessControlController::class, 'destroyRole'])
			->middleware('permission:roles.manage');

		Route::get('/shopping-lists', [ShoppingListController::class, 'index']);
		Route::post('/shopping-lists', [ShoppingListController::class, 'store']);
		Route::get('/shopping-lists/{shoppingList}', [ShoppingListController::class, 'show']);
		Route::post('/shopping-lists/{shoppingList}/items', [ShoppingListController::class, 'storeItem']);
		Route::put('/shopping-lists/{shoppingList}/items/{item}', [ShoppingListController::class, 'updateItem']);
		Route::post('/shopping-lists/{shoppingList}/items/return-skipped', [ShoppingListController::class, 'returnSkippedItems']);
		Route::delete('/shopping-lists/{shoppingList}/items/{item}', [ShoppingListController::class, 'destroyItem']);
		Route::put('/shopping-lists/{shoppingList}', [ShoppingListController::class, 'update']);
		Route::delete('/shopping-lists/{shoppingList}', [ShoppingListController::class, 'destroy']);
		Route::post('/shopping-lists/{shoppingList}/users', [ShoppingListController::class, 'shareUser']);
		Route::put('/shopping-lists/{shoppingList}/users/{userId}', [ShoppingListController::class, 'updateUserShare']);
		Route::delete('/shopping-lists/{shoppingList}/users/{userId}', [ShoppingListController::class, 'removeUserShare']);
		Route::post('/shopping-lists/{shoppingList}/families', [ShoppingListController::class, 'shareFamily']);
		Route::put('/shopping-lists/{shoppingList}/families/{family}', [ShoppingListController::class, 'updateFamilyShare']);
		Route::delete('/shopping-lists/{shoppingList}/families/{family}', [ShoppingListController::class, 'removeFamilyShare']);
		Route::post('/shopping-lists/{shoppingList}/families/{family}/members', [ShoppingListController::class, 'shareFamilyMember']);
		Route::put('/shopping-lists/{shoppingList}/families/{family}/members/{userId}', [ShoppingListController::class, 'updateFamilyMemberShare']);
		Route::delete('/shopping-lists/{shoppingList}/families/{family}/members/{userId}', [ShoppingListController::class, 'removeFamilyMemberShare']);

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
