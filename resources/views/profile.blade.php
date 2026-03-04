<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 p-6">
    <div class="mx-auto max-w-xl bg-white border border-gray-200 rounded-xl p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">My Profile</h1>
            <a href="{{ route('users.index') }}" class="text-blue-600 hover:underline">Back to Users</a>
        </div>

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
        </div>
    </div>
</body>
</html>