<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $export->guest_name ? $export->guest_name . "'s tandem video" : 'Tandem video' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white min-h-screen flex flex-col items-center justify-center px-4 py-10">

    @if ($export->status === 'done' && $videoUrl)

        <div class="w-full max-w-3xl space-y-5">

            @if ($export->guest_name)
                <h1 class="text-2xl font-semibold text-center text-white">{{ $export->guest_name }}</h1>
            @endif

            <video
                class="w-full rounded-xl"
                controls
                autoplay
                playsinline
                src="{{ $videoUrl }}"
            ></video>

            <div class="flex justify-center">
                <a
                    href="{{ route('share.download', $export->uuid) }}"
                    class="inline-flex items-center gap-2 bg-white text-black font-semibold px-6 py-2.5 rounded-full hover:bg-gray-100 transition text-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download video
                </a>
            </div>

        </div>

    @elseif ($export->status === 'processing' || $export->status === 'pending')

        <div class="text-center space-y-3">
            <svg class="animate-spin mx-auto h-10 w-10 text-white/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <p class="text-white/70">Din video forberedes&hellip;</p>
            <p class="text-white/30 text-sm">Siden opdateres automatisk.</p>
            <script>setTimeout(() => location.reload(), 5000);</script>
        </div>

    @elseif ($export->status === 'failed')

        <div class="text-center space-y-2">
            <p class="text-red-400 font-semibold">Noget gik galt</p>
            <p class="text-white/40 text-sm">Kontakt venligst personalet.</p>
        </div>

    @else

        <div class="text-center space-y-2">
            <p class="text-white/60 text-lg">Denne video er ikke længere tilgængelig.</p>
            <p class="text-white/30 text-sm">Videoer slettes automatisk efter 7 dage.</p>
        </div>

    @endif

</body>
</html>
