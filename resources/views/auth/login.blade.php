<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50">
    @include('partials.navbar')

    <div class="p-6 min-h-[calc(100vh-65px)] flex items-center justify-center">
    <div class="w-full max-w-md bg-white border border-gray-200 rounded-xl p-6">
        <h1 class="text-2xl font-bold mb-1">Login</h1>
        <p class="text-gray-600 mb-6">Sign in to access the app.</p>

        @if (session('oauth_error'))
            <div class="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {{ session('oauth_error') }}
            </div>
        @endif

        @if (session('oauth_status'))
            <div class="mb-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                {{ session('oauth_status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    class="w-full rounded border border-gray-300 px-3 py-2"
                >
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    class="w-full rounded border border-gray-300 px-3 py-2"
                >
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
                Remember me
            </label>

            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Sign in
            </button>
        </form>

        <div class="my-4 text-center text-sm text-gray-500">or</div>

        <a
            href="{{ route('auth.google.redirect') }}"
            class="block w-full text-center bg-slate-800 text-white px-4 py-2 rounded hover:bg-slate-900"
        >
            Continue with Google
        </a>
    </div>
    </div>
</body>
</html>