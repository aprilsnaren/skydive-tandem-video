<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('videoedit.brand_name') }} — Dashboard</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root { --brand: {{ config('videoedit.brand_color') }}; }
        .brand-bg   { background-color: var(--brand); }
        .brand-text { color: var(--brand); }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen">

    <header class="brand-bg px-6 py-4 flex items-center justify-between shadow-lg">
        <span class="text-white font-semibold text-lg tracking-wide">{{ config('videoedit.brand_name') }}</span>
        <div class="flex items-center gap-4">
            <a href="{{ route('editor.new') }}" class="bg-white/20 hover:bg-white/30 transition text-white text-sm font-medium px-4 py-2 rounded-lg">
                + New project
            </a>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-white/70 hover:text-white text-sm transition">Log out</button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">

        <h1 class="text-2xl font-bold mb-8">All projects</h1>

        @if ($exports->isEmpty())
            <div class="bg-gray-900 rounded-2xl p-12 text-center text-gray-500">
                <p class="text-lg">No projects yet.</p>
                <p class="text-sm mt-2">Create your first project using the button above.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($exports as $export)
                    @php
                        $statusColors = [
                            'pending'    => 'bg-yellow-900/60 text-yellow-300 border-yellow-700',
                            'processing' => 'bg-blue-900/60 text-blue-300 border-blue-700',
                            'done'       => 'bg-green-900/60 text-green-300 border-green-700',
                            'failed'     => 'bg-red-900/60 text-red-300 border-red-700',
                        ];
                        $statusColor = $statusColors[$export->status] ?? 'bg-gray-800 text-gray-400 border-gray-700';
                    @endphp

                    <div class="bg-gray-900 rounded-2xl px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">

                        {{-- Left: name + meta --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <span class="font-semibold text-white truncate">
                                    {{ $export->guest_name ?: 'Unnamed guest' }}
                                </span>
                                <span class="text-xs font-medium border px-2 py-0.5 rounded-full {{ $statusColor }}">
                                    {{ ucfirst($export->status) }}
                                </span>

                                @if ($export->email_ready_at)
                                    <span class="text-xs text-green-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                        </svg>
                                        Emailed
                                    </span>
                                @endif

                                @if ($export->downloaded_at)
                                    <span class="text-xs text-purple-400 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                        Downloaded
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1.5 text-xs text-gray-500">
                                @if ($export->guest_email)
                                    <span>{{ $export->guest_email }}</span>
                                @endif
                                <span>Created {{ $export->created_at->diffForHumans() }}</span>
                                @if ($export->expires_at)
                                    <span class="{{ $export->expires_at->isPast() ? 'text-red-500' : '' }}">
                                        {{ $export->expires_at->isPast() ? 'Expired' : 'Expires' }} {{ $export->expires_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>

                            @if ($export->status === 'failed' && $export->error_message)
                                <p class="text-xs text-red-400 font-mono mt-1 truncate">{{ Str::limit($export->error_message, 120) }}</p>
                            @endif
                        </div>

                        {{-- Right: actions --}}
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($export->status === 'done' && $export->path)
                                <a
                                    href="{{ route('share', $export->uuid) }}"
                                    target="_blank"
                                    class="text-xs bg-gray-800 hover:bg-gray-700 transition text-gray-300 px-3 py-1.5 rounded-lg"
                                >
                                    Share link
                                </a>
                            @endif

                            <a
                                href="{{ route('editor.edit', $export->uuid) }}"
                                class="text-xs brand-text hover:opacity-80 transition bg-gray-800 hover:bg-gray-700 px-3 py-1.5 rounded-lg"
                            >
                                Open
                            </a>

                            <form
                                method="POST"
                                action="{{ route('export.destroy', $export->uuid) }}"
                                onsubmit="return confirm('Delete this project? This cannot be undone.')"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="text-xs text-gray-500 hover:text-red-400 transition bg-gray-800 hover:bg-gray-700 px-3 py-1.5 rounded-lg"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

    </main>

</body>
</html>
