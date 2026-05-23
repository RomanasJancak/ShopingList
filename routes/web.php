<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GoogleAuthController;
use App\Models\Family;
use App\Support\UserShoppingListPreferenceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('shopping-lists.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::view('/users', 'users')->middleware('auth')->name('users.index');
Route::view('/families', 'families')->middleware('auth')->name('families.index');
Route::view('/shopping-lists', 'shopping-lists')->middleware('auth')->name('shopping-lists.index');
Route::get('/shopping-lists/{id}', function ($id) {
    return view('shopping-list-view', [
        'listId' => $id,
        'showProductPictures' => (bool) auth()->user()?->show_product_pictures_in_shopping_list,
    ]);
})->middleware('auth')->name('shopping-list.view');
Route::view('/access-control', 'access-control')->middleware('auth')->name('access-control.index');
Route::get('/profile', function () {
    $user = auth()->user();
    $resolver = app(UserShoppingListPreferenceResolver::class);
    $shoppingLists = $resolver->getAccessibleShoppingLists($user);
    $resolvedDefaultShoppingList = $resolver->resolveDefaultShoppingList($user);

    $families = Family::query()
        ->where('owner_user_id', $user->id)
        ->orWhereHas('userRoles', fn ($query) => $query->where('user_id', $user->id))
        ->orderBy('name')
        ->distinct()
        ->get(['id', 'name', 'owner_user_id']);

    return view('profile', [
        'user' => $user,
        'families' => $families,
        'shoppingLists' => $shoppingLists,
        'resolvedDefaultShoppingListId' => $resolvedDefaultShoppingList?->id,
    ]);
})->middleware('auth')->name('profile.show');

Route::post('/profile/preferences', function (Request $request) {
    $user = $request->user();
    $resolver = app(UserShoppingListPreferenceResolver::class);
    $shoppingLists = $resolver->getAccessibleShoppingLists($user);

    $validated = $request->validate([
        'default_shopping_list_id' => ['nullable', 'integer'],
        'load_default_shopping_list_on_login' => ['nullable', 'boolean'],
        'show_product_pictures_in_shopping_list' => ['nullable', 'boolean'],
    ]);

    $selectedDefaultId = $validated['default_shopping_list_id'] ?? null;

    if ($selectedDefaultId !== null && ! $shoppingLists->contains('id', (int) $selectedDefaultId)) {
        return back()->withErrors([
            'default_shopping_list_id' => 'Selected shopping list is not available for this user.',
        ])->withInput();
    }

    if ($shoppingLists->count() === 1) {
        $selectedDefaultId = (int) $shoppingLists->first()->id;
    }

    $user->forceFill([
        'default_shopping_list_id' => $selectedDefaultId,
        'load_default_shopping_list_on_login' => $request->boolean('load_default_shopping_list_on_login'),
        'show_product_pictures_in_shopping_list' => $request->boolean('show_product_pictures_in_shopping_list'),
    ])->save();

    $resolver->resolveDefaultShoppingList($user);

    return redirect()->route('profile.show')->with('status', 'Profile preferences updated.');
})->middleware('auth')->name('profile.preferences.update');
Route::view('/docs', 'docs')->name('docs.index');

Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/link', [GoogleAuthController::class, 'redirectForLink'])->name('auth.google.link');
    Route::get('/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
