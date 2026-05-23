<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50">
    @include('partials.navbar')

    <div class="p-6">
    <div class="mx-auto max-w-xl bg-white border border-gray-200 rounded-xl p-6">
        <h1 class="text-2xl font-bold mb-6">My Profile</h1>

        @if (session('status'))
            <div class="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">User ID</p>
                <p class="font-medium">{{ $user->id }}</p>
            </div>

            <div>
                <p class="text-sm text-gray-500">Name</p>
                <p class="font-medium">{{ $user->name }}</p>
            </div>

            <div>
                <p class="text-sm text-gray-500">Email</p>
                <p class="font-medium">{{ $user->email }}</p>
            </div>

            @if ($user->google_avatar)
                <div>
                    <p class="text-sm text-gray-500 mb-2">Google Avatar</p>
                    <img src="{{ $user->google_avatar }}" alt="Google Avatar" class="w-16 h-16 rounded-full border border-gray-200">
                </div>
            @endif

            <div>
                <p class="text-sm text-gray-500 mb-2">Families</p>

                @if ($families->isEmpty())
                    <p class="text-sm text-gray-600">You do not belong to any families yet.</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($families as $family)
                            <li>
                                <a
                                    href="{{ route('families.index') }}?family={{ $family->id }}"
                                    class="text-blue-700 hover:text-blue-900 hover:underline"
                                >
                                    {{ $family->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <form method="POST" action="{{ route('profile.preferences.update') }}" class="pt-4 border-t border-gray-200 space-y-3">
                @csrf

                <div>
                    <p class="text-sm text-gray-500 mb-2">Default Shopping List</p>

                    @if ($shoppingLists->isEmpty())
                        <p class="text-sm text-gray-600">No shopping lists available.</p>
                    @elseif ($shoppingLists->count() === 1)
                        <p class="text-sm text-gray-700">
                            {{ $shoppingLists->first()->name }}
                            <span class="text-gray-500">(automatically selected because only one list is assigned)</span>
                        </p>
                        <input type="hidden" name="default_shopping_list_id" value="{{ $shoppingLists->first()->id }}">
                    @else
                        <select name="default_shopping_list_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            <option value="">No default list</option>
                            @foreach ($shoppingLists as $shoppingList)
                                <option value="{{ $shoppingList->id }}" @selected((int) old('default_shopping_list_id', $resolvedDefaultShoppingListId) === (int) $shoppingList->id)>
                                    {{ $shoppingList->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    @error('default_shopping_list_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        name="load_default_shopping_list_on_login"
                        value="1"
                        @checked(old('load_default_shopping_list_on_login', $user->load_default_shopping_list_on_login))
                    >
                    On login load default shopping list
                </label>

                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        name="show_product_pictures_in_shopping_list"
                        value="1"
                        @checked(old('show_product_pictures_in_shopping_list', $user->show_product_pictures_in_shopping_list ?? true))
                    >
                    Show product pictures in shopping list
                </label>

                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
                    Save Preferences
                </button>
            </form>
        </div>
    </div>
    </div>
</body>
</html>