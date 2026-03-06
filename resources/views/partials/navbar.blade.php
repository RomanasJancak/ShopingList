<nav class="bg-white border-b border-gray-200">
    <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between">
        <a href="{{ route('users.index') }}" class="font-semibold text-gray-900">ShoppingList</a>

        <div class="flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('users.index') }}" class="text-gray-700 hover:text-gray-900">Users</a>
                <a href="{{ route('families.index') }}" class="text-gray-700 hover:text-gray-900">Families</a>
                <a href="{{ route('profile.show') }}" class="text-gray-700 hover:text-gray-900">My Profile</a>
                <a href="{{ route('docs.index') }}" class="text-gray-700 hover:text-gray-900">Docs</a>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700">
                        Logout
                    </button>
                </form>
            @else
                <a href="{{ route('docs.index') }}" class="text-gray-700 hover:text-gray-900">Docs</a>
                <a href="{{ route('login') }}" class="bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700">Login</a>
            @endauth
        </div>
    </div>
</nav>