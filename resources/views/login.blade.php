<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('videoedit.brand_name') }} — Login</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root { --brand: {{ config('videoedit.brand_color') }}; }
        .brand-bg   { background-color: var(--brand); }
        .brand-ring:focus { outline: none; box-shadow: 0 0 0 2px var(--brand); }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen flex flex-col">

    <header class="brand-bg px-6 py-4 shadow-lg">
        <span class="text-white font-semibold text-lg tracking-wide">{{ config('videoedit.brand_name') }}</span>
    </header>

    <main class="flex flex-1 items-center justify-center px-4">
        <div class="w-full max-w-sm space-y-6">
            <h1 class="text-2xl font-bold text-center">Editor login</h1>

            @if ($errors->has('password'))
                <p class="bg-red-900/50 border border-red-700 text-red-300 text-sm rounded-xl px-4 py-3">
                    {{ $errors->first('password') }}
                </p>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="password" class="text-sm text-gray-400 block mb-1">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autofocus
                        autocomplete="current-password"
                        class="brand-ring w-full bg-gray-900 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-transparent transition"
                        placeholder="Enter editor password"
                    >
                </div>

                <button
                    type="submit"
                    class="brand-bg w-full text-white font-semibold py-3 rounded-xl hover:opacity-90 transition"
                >
                    Sign in
                </button>
            </form>
        </div>
    </main>

</body>
</html>
