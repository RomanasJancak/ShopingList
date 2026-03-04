<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User CRUD</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div
        id="app"
        data-is-authenticated="{{ auth()->check() ? '1' : '0' }}"
        data-oauth-status="{{ session('oauth_status') }}"
        data-oauth-error="{{ session('oauth_error') }}"
    ></div>
</body>
</html>