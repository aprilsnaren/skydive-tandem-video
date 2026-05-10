<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('videoedit.brand_name') }} — Editor</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root { --brand: {{ config('videoedit.brand_color') }}; }
        .brand-bg        { background-color: var(--brand); }
        .brand-text      { color: var(--brand); }
        .brand-border    { border-color: var(--brand); }
        .brand-ring:focus { outline: none; box-shadow: 0 0 0 2px var(--brand); }
        [x-cloak]        { display: none !important; }
        .dragging        { opacity: 0.35; }
        .drag-over       { outline: 2px dashed var(--brand); outline-offset: 2px; }

        /* Range input styling */
        input[type=range] { accent-color: var(--brand); }

        /* Video preview */
        .clip-preview { width: 100%; border-radius: 0.5rem; background: #000; max-height: 220px; }
    </style>
    <script>window.__editorInitial = @json($initial ?? null);</script>
</head>
<body class="bg-gray-950 text-white min-h-screen" x-data="editor(window.__editorInitial)" x-cloak>

    {{-- Header --}}
    <header class="brand-bg px-6 py-4 flex items-center gap-3 shadow-lg">
        <a href="{{ route('dashboard') }}" class="text-white/70 hover:text-white transition text-sm flex items-center gap-1 mr-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            All projects
        </a>
        <span class="text-white/30">|</span>
        <span class="text-white font-semibold text-lg tracking-wide">{{ config('videoedit.brand_name') }}</span>
    </header>

    {{-- -------------------------------------------------------------------- --}}
    {{-- LANDING: Upload drop zone — shown until uploads begin                 --}}
    {{-- -------------------------------------------------------------------- --}}
    <div x-show="!isReturning && clips.length === 0 && pendingUploads.length === 0" class="flex flex-col items-center justify-center min-h-[80vh] px-4">
        <h1 class="text-3xl font-bold mb-2">Create a tandem video</h1>
        <p class="text-gray-400 mb-10 text-center max-w-md">Upload your clips, trim them, add music, and share with your guest.</p>

        <label
            class="flex flex-col items-center justify-center border-2 border-dashed rounded-2xl p-14 cursor-pointer transition w-full max-w-lg"
            :class="pendingUploads.length ? 'border-[color:var(--brand)] opacity-60 pointer-events-none' : 'border-gray-600 hover:border-[color:var(--brand)]'"
            @dragover.prevent
            @drop.prevent="dropFiles($event)"
        >
            <svg class="w-14 h-14 text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <span class="text-white font-semibold text-lg mb-1">Drop clips here</span>
            <span class="text-gray-400 text-sm">or click to browse — MP4, MOV, AVI, WebM</span>
            <input type="file" multiple accept="video/*" class="hidden" @change="addClips($event)" :disabled="pendingUploads.length > 0">
        </label>

        {{-- Per-file upload progress --}}
        <div x-show="pendingUploads.length > 0" class="w-full max-w-lg mt-6 space-y-3">
            <template x-for="p in pendingUploads" :key="p.uuid">
                <div class="bg-gray-900 rounded-xl px-4 py-3 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-300 truncate max-w-xs" x-text="p.name"></span>
                        <span x-show="!p.error" class="text-gray-400 shrink-0 ml-2" x-text="p.progress + '%'"></span>
                        <span x-show="p.error" class="text-red-400 shrink-0 ml-2 text-xs" x-text="p.error"></span>
                    </div>
                    <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="h-full brand-bg rounded-full transition-all duration-150"
                            :class="p.error ? 'bg-red-500' : ''"
                            :style="`width: ${p.error ? 100 : p.progress}%`"
                        ></div>
                    </div>
                </div>
            </template>
        </div>

        <p x-show="uploadError && !pendingUploads.length" x-text="uploadError" class="mt-4 text-red-400 text-sm"></p>
    </div>

    {{-- -------------------------------------------------------------------- --}}
    {{-- EDITOR: shown as soon as uploads start (clips list may be empty while --}}
    {{--         files are still uploading)                                    --}}
    {{-- -------------------------------------------------------------------- --}}
    <main x-show="isReturning || clips.length > 0 || pendingUploads.length > 0" class="max-w-3xl mx-auto px-4 py-8 space-y-6">

        {{-- ---------------------------------------------------------------- --}}
        {{-- Step 1: Clips — trim + preview + reorder                         --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold">1. Clips</h2>
                    <p class="text-gray-500 text-sm mt-0.5">Preview each clip, set trim points. Use the arrows to reorder.</p>
                </div>

                {{-- Add more clips button --}}
                <label class="cursor-pointer text-sm brand-text hover:opacity-80 transition flex items-center gap-1.5" :class="{'opacity-50 pointer-events-none': pendingUploads.length}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add clips
                    <input type="file" multiple accept="video/*" class="hidden" @change="addClips($event)" :disabled="pendingUploads.length > 0">
                </label>
            </div>

            <p x-show="uploadError" x-text="uploadError" class="text-red-400 text-sm"></p>

            {{-- Upload progress (visible while files are being uploaded) --}}
            <div x-show="pendingUploads.length > 0" class="space-y-2">
                <template x-for="p in pendingUploads" :key="p.uuid">
                    <div class="bg-gray-800 rounded-xl px-4 py-3 space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-300 truncate max-w-xs" x-text="p.name"></span>
                            <span x-show="!p.error" class="text-gray-400 shrink-0 ml-2" x-text="p.progress + '%'"></span>
                            <span x-show="p.error" class="text-red-400 shrink-0 ml-2 text-xs" x-text="p.error"></span>
                        </div>
                        <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                            <div
                                class="h-full brand-bg rounded-full transition-all duration-150"
                                :class="p.error ? 'bg-red-500' : ''"
                                :style="`width: ${p.error ? 100 : p.progress}%`"
                            ></div>
                        </div>
                    </div>
                </template>
            </div>

            <ul class="space-y-4">
                <template x-for="(clip, index) in clips" :key="clip.uuid">
                    <li class="bg-gray-800 rounded-xl overflow-hidden">
                        {{-- Clip header --}}
                        <div class="flex items-center gap-2 px-4 pt-4 pb-2">
                            <div class="flex flex-col shrink-0">
                                <button @click="moveUp(index)" :disabled="index === 0" class="text-gray-500 hover:text-white disabled:opacity-20 transition leading-none p-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <button @click="moveDown(index)" :disabled="index === clips.length - 1" class="text-gray-500 hover:text-white disabled:opacity-20 transition leading-none p-0.5">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                            </div>
                            <span class="text-sm font-medium text-gray-200 truncate flex-1" x-text="clip.original_name"></span>
                            <span class="text-xs text-gray-500 shrink-0" x-text="clip.nativeDuration ? formatDuration(clip.nativeDuration) + ' total' : ''"></span>
                            <button @click="removeClip(index)" class="text-gray-500 hover:text-red-400 transition shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Video preview --}}
                        <div class="px-4 pb-3">
                            <video
                                x-show="!clip.fileExpired"
                                class="clip-preview"
                                preload="metadata"
                                :src="clip.localUrl"
                                @loadedmetadata="onVideoLoaded($event, clip)"
                                @timeupdate="onTimeUpdate($event, clip)"
                            ></video>
                            <p x-show="clip.fileExpired" class="text-yellow-500/80 text-xs py-2">
                                Original file has been pruned — preview unavailable. Trim values are preserved.
                            </p>
                        </div>

                        {{-- Trim controls --}}
                        <div class="px-4 pb-4 space-y-3" @pointerdown.stop>
                            {{-- Visual trim bar --}}
                            <div class="relative h-2 bg-gray-700 rounded-full" x-show="clip.nativeDuration">
                                <div
                                    class="absolute h-full rounded-full brand-bg opacity-60"
                                    :style="`left: ${(clip.trim_start / clip.nativeDuration) * 100}%; width: ${((clip.trim_end - clip.trim_start) / clip.nativeDuration) * 100}%`"
                                ></div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">
                                        Trim start (s)
                                        <span class="brand-text ml-1" x-text="clip.trim_start.toFixed(1)"></span>
                                    </label>
                                    <input
                                        type="range" min="0" step="0.1"
                                        :max="clip.nativeDuration || 3600"
                                        x-model.number="clip.trim_start"
                                        @input="onTrimChange(clip, $el.closest('li').querySelector('video'))"
                                        class="w-full"
                                    >
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">
                                        Trim end (s)
                                        <span class="brand-text ml-1" x-text="clip.trim_end.toFixed(1)"></span>
                                    </label>
                                    <input
                                        type="range" min="0" step="0.1"
                                        :max="clip.nativeDuration || 3600"
                                        x-model.number="clip.trim_end"
                                        @input="onTrimChange(clip, $el.closest('li').querySelector('video'))"
                                        class="w-full"
                                    >
                                </div>
                            </div>

                            <p x-show="clip.trimError" x-text="clip.trimError" class="text-red-400 text-xs"></p>

                            <p class="text-xs text-gray-500">
                                Clip length after trim:
                                <span class="text-white font-medium" x-text="formatDuration(Math.max(0, clip.trim_end - clip.trim_start))"></span>
                            </p>

                            {{-- Audio controls --}}
                            <div class="pt-1 space-y-2">
                                <p class="text-xs text-gray-500 mb-1">Audio</p>
                                <div class="flex gap-2">
                                    <template x-for="mode in ['full','muted','range']" :key="mode">
                                        <button
                                            type="button"
                                            @click="
                                                if (mode === 'range' && clip.audio_mode !== 'range') {
                                                    clip.audio_start = 0;
                                                    clip.audio_end   = Math.max(0, clip.trim_end - clip.trim_start);
                                                }
                                                clip.audio_mode = mode;
                                            "
                                            :class="clip.audio_mode === mode
                                                ? 'brand-bg text-white border-transparent'
                                                : 'bg-gray-800 text-gray-400 border-gray-700 hover:border-gray-500'"
                                            class="flex-1 py-1 px-2 rounded-lg border text-xs font-medium transition capitalize"
                                            x-text="mode === 'full' ? 'Full' : mode === 'muted' ? 'Muted' : 'Range'"
                                        ></button>
                                    </template>
                                </div>

                                <div x-show="clip.audio_mode === 'range'" class="grid grid-cols-2 gap-3 pt-1">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">
                                            Audio start (s)
                                            <span class="brand-text ml-1" x-text="clip.audio_start.toFixed(1)"></span>
                                        </label>
                                        <input
                                            type="range" min="0" step="0.1"
                                            :max="Math.max(0, clip.trim_end - clip.trim_start)"
                                            x-model.number="clip.audio_start"
                                            class="w-full"
                                        >
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">
                                            Audio end (s)
                                            <span class="brand-text ml-1" x-text="clip.audio_end.toFixed(1)"></span>
                                        </label>
                                        <input
                                            type="range" min="0" step="0.1"
                                            :max="Math.max(0, clip.trim_end - clip.trim_start)"
                                            x-model.number="clip.audio_end"
                                            class="w-full"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Step 2: Background music                                          --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-3">
            <h2 class="text-lg font-semibold">2. Background music <span class="text-gray-500 text-sm font-normal">(optional)</span></h2>

            <label
                class="flex items-center gap-4 border border-gray-700 rounded-xl px-4 py-3 cursor-pointer hover:border-[color:var(--brand)] transition"
                :class="{ 'opacity-50 pointer-events-none': pendingUploads.length }"
            >
                <svg class="w-6 h-6 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/>
                </svg>

                <template x-if="!music">
                    <span class="text-gray-400 text-sm">Click to select an MP3 or audio file</span>
                </template>
                <template x-if="music">
                    <div class="flex items-center gap-2 text-sm text-green-400 min-w-0">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="truncate" x-text="music.name"></span>
                    </div>
                </template>

                <input type="file" accept="audio/*" class="hidden" @change="setMusic($event)" :disabled="pendingUploads.length > 0">
            </label>

            <button x-show="music" @click="clearMusic()" class="text-xs text-gray-500 hover:text-red-400 transition">
                Remove music
            </button>
        </section>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Step 3: Logo                                                      --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-3">
            <h2 class="text-lg font-semibold">3. Logo <span class="text-gray-500 text-sm font-normal">(optional — burned into video)</span></h2>

            <label
                class="flex items-center gap-4 border border-gray-700 rounded-xl px-4 py-3 cursor-pointer hover:border-[color:var(--brand)] transition"
                :class="{ 'opacity-50 pointer-events-none': logoUploading }"
            >
                <svg class="w-6 h-6 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>

                <template x-if="!logo && !logoUploading">
                    <span class="text-gray-400 text-sm">Click to select a PNG or JPG logo</span>
                </template>
                <template x-if="logoUploading">
                    <span class="text-gray-400 text-sm animate-pulse">Uploading…</span>
                </template>
                <template x-if="logo && !logoUploading">
                    <div class="flex items-center gap-3 min-w-0">
                        <img :src="logo.localUrl" class="h-8 w-auto object-contain rounded" alt="logo preview">
                        <span class="text-sm text-green-400 truncate" x-text="logo.name"></span>
                    </div>
                </template>

                <input type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="hidden" @change="setLogo($event)" :disabled="logoUploading">
            </label>

            <button x-show="logo" @click="clearLogo()" class="text-xs text-gray-500 hover:text-red-400 transition">
                Remove logo
            </button>
        </section>

        {{-- ---------------------------------------------------------------- --}}
        {{-- Step 4: Export + stats                                            --}}
        {{-- ---------------------------------------------------------------- --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <h2 class="text-lg font-semibold">4. Export</h2>

            {{-- Guest name --}}
            <div>
                <label class="text-sm text-gray-400 block mb-1">Guest name <span class="text-gray-600">(used as filename)</span></label>
                <input
                    type="text"
                    x-model="guestName"
                    placeholder="e.g. Lars Hansen"
                    maxlength="100"
                    class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-[color:var(--brand)] transition"
                >
            </div>

            {{-- Guest email --}}
            <div>
                <label class="text-sm text-gray-400 block mb-1">Guest email <span class="text-gray-600">(for automatic notifications)</span></label>
                <input
                    type="email"
                    x-model="guestEmail"
                    placeholder="e.g. lars@example.com"
                    maxlength="254"
                    class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-[color:var(--brand)] transition"
                >
                <p class="text-gray-600 text-xs mt-1">The guest will receive an email when the video is ready, plus reminders before it expires after 7 days.</p>
            </div>
            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="bg-gray-800 rounded-xl py-3 px-2">
                    <p class="text-xs text-gray-500 mb-1">Clips</p>
                    <p class="text-lg font-bold" x-text="clips.length"></p>
                </div>
                <div class="bg-gray-800 rounded-xl py-3 px-2">
                    <p class="text-xs text-gray-500 mb-1">Total length</p>
                    <p class="text-lg font-bold" x-text="formatDuration(totalDuration)"></p>
                </div>
                <div class="bg-gray-800 rounded-xl py-3 px-2">
                    <p class="text-xs text-gray-500 mb-1">Est. size</p>
                    <p class="text-lg font-bold" x-text="estimatedSize"></p>
                </div>
            </div>

            <p class="text-gray-500 text-xs">
                <span x-show="music" class="text-green-400"> Background music included.</span>
            </p>

            <p x-show="exportError" x-text="exportError" class="text-red-400 text-sm"></p>

            {{-- Export button --}}
            <button
                @click="startExport()"
                :disabled="exporting || pendingUploads.length > 0 || clips.length === 0"
                class="w-full brand-bg text-white font-semibold py-3 rounded-xl hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <svg x-show="exporting" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <span x-text="exporting ? 'Processing…' : 'Export video'"></span>
            </button>

            {{-- Processing --}}
            <div x-show="exportUuid && !shareUrl && !exportFailed" class="bg-gray-800 rounded-xl p-4 space-y-3">
                <div class="flex items-center gap-2 text-sm">
                    <svg class="animate-spin h-4 w-4 brand-text shrink-0" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span class="text-white font-medium" x-text="exportStep || 'Queued…'"></span>
                    <span class="ml-auto text-gray-500 tabular-nums shrink-0" x-text="formatElapsed(exportElapsed)"></span>
                </div>
                <div x-show="exportStalled" class="text-yellow-400 text-xs flex items-start gap-1.5">
                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    Taking longer than expected. FFmpeg is still running — large clips can take several minutes.
                </div>
            </div>

            {{-- Video ready --}}
            <div x-show="shareUrl" class="bg-gray-800 rounded-xl p-4 space-y-4">
                <p class="text-green-400 font-semibold">Video ready</p>

                <div class="flex flex-col sm:flex-row gap-2">
                    {{-- Preview --}}
                    <a
                        :href="videoUrl"
                        target="_blank"
                        class="flex-1 flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Preview video
                    </a>

                    {{-- Copy link --}}
                    <button
                        @click="copyLink()"
                        class="flex-1 flex items-center justify-center gap-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium px-4 py-2.5 rounded-xl transition"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="copied ? 'Copied!' : 'Copy link'"></span>
                    </button>

                    {{-- Send email --}}
                    <button
                        @click="sendEmail()"
                        :disabled="!guestEmail || emailSending || emailSent"
                        class="flex-1 flex items-center justify-center gap-2 brand-bg text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:opacity-90 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        :title="!guestEmail ? 'Enter guest email in the form above first' : ''"
                    >
                        <svg x-show="emailSending" class="animate-spin w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        <svg x-show="!emailSending" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span x-text="emailSent ? 'Email sent!' : (emailSending ? 'Sending…' : 'Send email')"></span>
                    </button>
                </div>

                <p x-show="emailError" x-text="emailError" class="text-red-400 text-xs"></p>
                <p x-show="!guestEmail" class="text-gray-600 text-xs">Add a guest email above to enable sending.</p>
            </div>

            {{-- Failed --}}
            <div x-show="exportFailed" class="bg-red-950 border border-red-800 rounded-xl p-4 space-y-2">
                <p class="text-red-400 font-semibold text-sm">Export failed</p>
                <p x-show="exportErrMsg" x-text="exportErrMsg" class="text-red-300 text-xs font-mono break-all"></p>
                <p x-show="!exportErrMsg" class="text-red-300 text-xs">Unknown error. Check the server logs.</p>
                <button @click="exportFailed = false; exportErrMsg = null" class="text-xs text-red-400 hover:text-white transition underline mt-1">Dismiss and try again</button>
            </div>
        </section>

    </main>

    <script>
    const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB per chunk

    function editor(initial) {
        return {
            isReturning:    initial?.isReturning ?? false,
            clips: (initial?.clips || []).map(c => ({
                uuid:           c.uuid,
                original_name:  c.original_name,
                localUrl:       c.localUrl ?? null,
                trim_start:     c.trim_start ?? 0,
                trim_end:       c.trim_end ?? 0,
                audio_mode:     c.audio_mode  ?? 'full',
                audio_start:    c.audio_start ?? 0,
                audio_end:      c.audio_end   ?? 0,
                nativeDuration: null,
                trimError:      null,
                fileExpired:    c.fileExpired ?? false,
            })),
            pendingUploads: [], // [{ uuid, name, progress, error }]
            uploadError:    null,

            guestName:      initial?.guestName ?? '',
            guestEmail:     initial?.guestEmail ?? '',

            music:          initial?.music ?? null,
            musicUuid:      initial?.musicUuid ?? null,

            logo:           initial?.logo ?? null,
            logoUuid:       initial?.logoUuid ?? null,
            logoUploading:  false,

            exporting:      false,
            exportUuid:     initial?.exportUuid ?? null,
            exportError:    null,
            exportFailed:   initial?.exportFailed ?? false,
            exportErrMsg:   initial?.exportErrMsg ?? null,
            exportStep:     null,
            exportElapsed:  0,
            exportStalled:  false,
            shareUrl:       initial?.shareUrl ?? null,
            videoUrl:       initial?.videoUrl ?? null,
            copied:         false,

            emailSending:   false,
            emailSent:      false,
            emailError:     null,

            init() {
                // Resume polling if we loaded with an in-progress export
                if (this.exportUuid && !this.shareUrl && !this.exportFailed) {
                    this.exporting = true;
                    this.pollStatus();
                }
            },


            // ----------------------------------------------------------------
            // Computed
            // ----------------------------------------------------------------
            get totalDuration() {
                return this.clips.reduce((sum, c) => sum + Math.max(0, c.trim_end - c.trim_start), 0);
            },

            get estimatedSize() {
                const mb = (this.totalDuration * 5 / 8).toFixed(0);
                return mb < 1024 ? mb + ' MB' : (mb / 1024).toFixed(1) + ' GB';
            },

            // ----------------------------------------------------------------
            // File drop
            // ----------------------------------------------------------------
            dropFiles(event) {
                const files = Array.from(event.dataTransfer.files).filter(f => f.type.startsWith('video/'));
                if (files.length) this._uploadClipFiles(files);
            },

            async addClips(event) {
                const files = Array.from(event.target.files);
                event.target.value = '';
                if (files.length) await this._uploadClipFiles(files);
            },

            // ----------------------------------------------------------------
            // Chunked upload — clips (sequential to avoid saturating the connection)
            // ----------------------------------------------------------------
            async _uploadClipFiles(files) {
                this.uploadError = null;
                for (const f of files) {
                    await this._chunkUpload(f, 'video');
                }
            },

            async _chunkUpload(file, type) {
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
                const uuid        = crypto.randomUUID();
                const localUrl    = type === 'video' ? URL.createObjectURL(file) : null;

                const pending = { uuid, name: file.name, progress: 0, error: null };
                this.pendingUploads.push(pending);

                try {
                    for (let i = 0; i < totalChunks; i++) {
                        const chunk = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                        const form  = new FormData();
                        form.append('chunk',    chunk);
                        form.append('uuid',     uuid);
                        form.append('index',    i);
                        form.append('total',    totalChunks);
                        form.append('filename', file.name);
                        form.append('type',     type);

                        const resp = await fetch('/upload/chunk', {
                            method:  'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body:    form,
                        });

                        if (!resp.ok) {
                            const data = await resp.json().catch(() => ({}));
                            throw new Error(data.message || `Upload failed (${resp.status})`);
                        }

                        const data = await resp.json();
                        // Update progress through the reactive array so Alpine detects the change
                        const reactive = this.pendingUploads.find(p => p.uuid === uuid);
                        if (reactive) reactive.progress = Math.round(((i + 1) / totalChunks) * 100);

                        if (data.status === 'done') {
                            if (type === 'video') {
                                this.clips.push({
                                    uuid:           data.uuid,
                                    original_name:  data.original_name,
                                    localUrl:       localUrl,
                                    trim_start:     0,
                                    trim_end:       30,
                                    audio_mode:     'full',
                                    audio_start:    0,
                                    audio_end:      0,
                                    nativeDuration: null,
                                    trimError:      null,
                                });
                            } else {
                                this.musicUuid = data.uuid;
                            }
                        }
                    }
                } catch (err) {
                    const reactive = this.pendingUploads.find(p => p.uuid === uuid);
                    if (reactive) reactive.error = err.message;
                    this.uploadError = err.message;
                    if (localUrl) URL.revokeObjectURL(localUrl);
                } finally {
                    // Compute the index inside the callback — not before — so it's
                    // always current even when multiple uploads finish simultaneously.
                    setTimeout(() => {
                        const idx = this.pendingUploads.indexOf(pending);
                        if (idx !== -1) this.pendingUploads.splice(idx, 1);
                    }, pending.error ? 3000 : 600);
                }
            },

            // ----------------------------------------------------------------
            // Video metadata loaded — set trim_end to actual duration
            // ----------------------------------------------------------------
            onVideoLoaded(event, clip) {
                const dur = event.target.duration;
                if (!isFinite(dur)) return;
                clip.nativeDuration = dur;
                clip.trim_end = parseFloat(dur.toFixed(1));
            },

            onTrimChange(clip, videoEl) {
                if (clip.trim_start >= clip.trim_end) {
                    clip.trimError = 'Trim start must be before trim end.';
                } else {
                    clip.trimError = null;
                }
                if (videoEl) videoEl.currentTime = clip.trim_start;
            },

            onTimeUpdate(event, clip) {
                if (event.target.currentTime >= clip.trim_end) {
                    event.target.pause();
                    event.target.currentTime = clip.trim_start;
                }
            },

            // ----------------------------------------------------------------
            // Remove clip
            // ----------------------------------------------------------------
            removeClip(index) {
                const clip = this.clips[index];
                if (clip.localUrl?.startsWith('blob:')) URL.revokeObjectURL(clip.localUrl);
                this.clips.splice(index, 1);
            },

            // ----------------------------------------------------------------
            // Music upload (small files — chunked for consistency)
            // ----------------------------------------------------------------
            async setMusic(event) {
                const file = event.target.files[0];
                event.target.value = '';
                if (!file) return;

                this.music     = file;
                this.musicUuid = null;
                await this._chunkUpload(file, 'music');
            },

            clearMusic() {
                this.music     = null;
                this.musicUuid = null;
            },

            // ----------------------------------------------------------------
            // Logo upload
            // ----------------------------------------------------------------
            async setLogo(event) {
                const file = event.target.files[0];
                event.target.value = '';
                if (!file) return;

                this.logoUploading = true;
                this.logo          = null;
                this.logoUuid      = null;

                const form = new FormData();
                form.append('logo', file);

                try {
                    const resp = await fetch('/upload/logo', {
                        method:  'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body:    form,
                    });

                    if (!resp.ok) throw new Error('Logo upload failed');

                    const data    = await resp.json();
                    this.logoUuid = data.uuid;
                    this.logo     = { name: file.name, localUrl: URL.createObjectURL(file) };
                } catch (err) {
                    this.uploadError = err.message;
                } finally {
                    this.logoUploading = false;
                }
            },

            clearLogo() {
                if (this.logo?.localUrl?.startsWith('blob:')) URL.revokeObjectURL(this.logo.localUrl);
                this.logo     = null;
                this.logoUuid = null;
            },

            // ----------------------------------------------------------------
            // Reorder clips
            // ----------------------------------------------------------------
            moveUp(index) {
                if (index === 0) return;
                const moved = this.clips.splice(index, 1)[0];
                this.clips.splice(index - 1, 0, moved);
            },

            moveDown(index) {
                if (index === this.clips.length - 1) return;
                const moved = this.clips.splice(index, 1)[0];
                this.clips.splice(index + 1, 0, moved);
            },

            // ----------------------------------------------------------------
            // Export
            // ----------------------------------------------------------------
            async startExport() {
                this.exportError   = null;
                this.exportFailed  = false;
                this.exportErrMsg  = null;
                this.exportStep    = null;
                this.exportElapsed = 0;
                this.exportStalled = false;
                this.shareUrl      = null;
                this.videoUrl      = null;

                for (const clip of this.clips) {
                    if (clip.trim_end <= clip.trim_start) {
                        this.exportError = `Fix trim points on "${clip.original_name}" before exporting.`;
                        return;
                    }
                }

                this.exporting = true;

                try {
                    const payload = {
                        clips:       this.clips.map(c => ({
                            uuid:        c.uuid,
                            trim_start:  c.trim_start,
                            trim_end:    c.trim_end,
                            audio_mode:  c.audio_mode  ?? 'full',
                            audio_start: c.audio_start ?? 0,
                            audio_end:   c.audio_end   ?? 0,
                        })),
                        music_uuid:  this.musicUuid ?? null,
                        logo_uuid:   this.logoUuid ?? null,
                        guest_name:  this.guestName.trim() || null,
                        guest_email: this.guestEmail.trim() || null,
                    };

                    const resp = await fetch('/export', {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept':       'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!resp.ok) {
                        const data = await resp.json().catch(() => ({}));
                        throw new Error(data.message || 'Export request failed');
                    }

                    const data      = await resp.json();
                    this.exportUuid = data.export_uuid;
                    this.pollStatus();
                } catch (err) {
                    this.exportError = err.message;
                    this.exporting   = false;
                }
            },

            pollStatus() {
                let lastStep      = null;
                let stalledFor    = 0;
                const STALL_WARN  = 45; // seconds with no step change before warning

                const interval = setInterval(async () => {
                    this.exportElapsed++;

                    try {
                        const resp = await fetch(`/export/${this.exportUuid}/status`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!resp.ok) return;
                        const data = await resp.json();

                        if (data.status_message) {
                            if (data.status_message !== lastStep) {
                                lastStep   = data.status_message;
                                stalledFor = 0;
                                this.exportStalled = false;
                            } else {
                                stalledFor++;
                                this.exportStalled = stalledFor >= STALL_WARN;
                            }
                            this.exportStep = data.status_message;
                        }

                        if (data.status === 'done') {
                            clearInterval(interval);
                            this.shareUrl  = data.share_url;
                            this.videoUrl  = data.video_url;
                            this.exporting = false;
                        } else if (data.status === 'failed') {
                            clearInterval(interval);
                            this.exportFailed  = true;
                            this.exportErrMsg  = data.error_message || null;
                            this.exporting     = false;
                        }
                    } catch (_) {}
                }, 1000);
            },

            // ----------------------------------------------------------------
            // Helpers
            // ----------------------------------------------------------------
            formatDuration(seconds) {
                if (!seconds || isNaN(seconds)) return '0s';
                const m = Math.floor(seconds / 60);
                const s = Math.floor(seconds % 60);
                return m > 0 ? `${m}m ${s}s` : `${s}s`;
            },

            formatElapsed(seconds) {
                if (!seconds) return '';
                const m = Math.floor(seconds / 60);
                const s = seconds % 60;
                return m > 0 ? `${m}m ${String(s).padStart(2,'0')}s` : `${s}s`;
            },

            async copyLink() {
                try {
                    await navigator.clipboard.writeText(this.shareUrl);
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                } catch (_) {}
            },

            async sendEmail() {
                if (!this.guestEmail || this.emailSending || this.emailSent) return;
                this.emailSending = true;
                this.emailError   = null;
                try {
                    const resp = await fetch(`/export/${this.exportUuid}/send-email`, {
                        method:  'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Content-Type': 'application/json',
                            'Accept':       'application/json',
                        },
                        body: JSON.stringify({ guest_email: this.guestEmail.trim() }),
                    });
                    const data = await resp.json();
                    if (resp.ok) {
                        this.emailSent = true;
                    } else {
                        this.emailError = data.error || 'Failed to send email.';
                    }
                } catch (_) {
                    this.emailError = 'Network error — could not send email.';
                } finally {
                    this.emailSending = false;
                }
            },
        };
    }
    </script>

</body>
</html>
