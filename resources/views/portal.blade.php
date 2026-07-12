<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('videoedit.brand_name') }} — Upload files</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root { --brand: {{ config('videoedit.brand_color') }}; }
        .brand-bg        { background-color: var(--brand); }
        .brand-text      { color: var(--brand); }
        .brand-ring:focus { outline: none; box-shadow: 0 0 0 2px var(--brand); }
        [x-cloak]        { display: none !important; }
    </style>
</head>
<body class="bg-gray-950 text-white min-h-screen" x-data="portal()" x-cloak>

    {{-- Header --}}
    <header class="brand-bg px-6 py-4 shadow-lg">
        <span class="text-white font-semibold text-lg tracking-wide">{{ config('videoedit.brand_name') }}</span>
    </header>

    {{-- Success state --}}
    <main x-show="submitted" class="max-w-xl mx-auto px-4 py-24 text-center space-y-6">
        <svg class="w-16 h-16 mx-auto text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="space-y-2">
            <h1 class="text-2xl font-bold">Files submitted!</h1>
            <p class="text-gray-400">Thanks — the editor has been notified and will take it from here.</p>
        </div>
        <button
            @click="reset()"
            class="brand-bg text-white font-semibold px-6 py-3 rounded-xl hover:opacity-90 transition"
        >
            Upload files for another guest
        </button>
    </main>

    {{-- Form --}}
    <main x-show="!submitted" class="max-w-xl mx-auto px-4 py-10 space-y-6">

        <div class="space-y-1">
            <h1 class="text-2xl font-bold">Upload files</h1>
            <p class="text-gray-400 text-sm">Upload the videos and photos for a guest. The editor will trim and finish the video.</p>
        </div>

        {{-- Who --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <div>
                <label class="text-sm text-gray-400 block mb-1">Your name <span class="text-red-400">*</span></label>
                <input
                    type="text"
                    x-model="uploaderName"
                    placeholder="e.g. Mikkel (camera flyer)"
                    maxlength="100"
                    class="brand-ring w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-transparent transition"
                >
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400 block mb-1">Guest name <span class="text-red-400">*</span></label>
                    <input
                        type="text"
                        x-model="receiverName"
                        placeholder="e.g. Lars Hansen"
                        maxlength="100"
                        class="brand-ring w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-transparent transition"
                    >
                </div>
                <div>
                    <label class="text-sm text-gray-400 block mb-1">Guest email <span class="text-red-400">*</span></label>
                    <input
                        type="email"
                        x-model="receiverEmail"
                        placeholder="e.g. lars@example.com"
                        maxlength="254"
                        class="brand-ring w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-transparent transition"
                    >
                </div>
            </div>

            <div>
                <label class="text-sm text-gray-400 block mb-1">Message to the editor <span class="text-gray-600">(optional)</span></label>
                <textarea
                    x-model="message"
                    rows="2"
                    maxlength="2000"
                    placeholder="Anything the editor should know — jump number, special moments, etc."
                    class="brand-ring w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-600 focus:border-transparent transition resize-y"
                ></textarea>
            </div>
        </section>

        {{-- Files --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Videos &amp; photos</h2>
                <p class="text-gray-500 text-sm mt-0.5">Videos become the film; photos are shown at the end and can be downloaded by the guest.</p>
            </div>

            <label
                class="flex flex-col items-center justify-center border-2 border-dashed rounded-2xl p-10 cursor-pointer transition w-full"
                :class="uploadsInProgress ? 'border-[color:var(--brand)] opacity-60' : 'border-gray-600 hover:border-[color:var(--brand)]'"
                @dragover.prevent
                @drop.prevent="dropFiles($event)"
            >
                <svg class="w-12 h-12 text-gray-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <span class="text-white font-semibold mb-1">Drop videos and photos here</span>
                <span class="text-gray-400 text-sm">or click to browse — you can add more afterwards</span>
                <input type="file" multiple accept="video/*,image/png,image/jpeg,image/webp" class="hidden" @change="addFiles($event)">
            </label>

            {{-- File queue: every selected file with its own status --}}
            <div x-show="queue.length > 0" class="space-y-2">
                <template x-for="f in queue" :key="f.uuid">
                    <div class="bg-gray-800 rounded-xl px-4 py-3 space-y-2">
                        <div class="flex items-center justify-between text-sm gap-2">
                            <span class="flex items-center gap-2 min-w-0">
                                <svg x-show="f.kind === 'video'" class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                <svg x-show="f.kind === 'image'" class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-gray-300 truncate" x-text="f.name"></span>
                                <span class="text-gray-600 text-xs shrink-0" x-text="formatSize(f.size)"></span>
                            </span>
                            <span class="flex items-center gap-2 shrink-0">
                                <span x-show="f.status === 'pending'" class="text-gray-500 text-xs">Waiting&hellip;</span>
                                <span x-show="f.status === 'uploading'" class="text-gray-400 text-xs tabular-nums" x-text="f.progress + '%'"></span>
                                <span x-show="f.status === 'done'" class="text-green-400 text-xs flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                    Done
                                </span>
                                <span x-show="f.status === 'error'" class="text-red-400 text-xs" x-text="f.error"></span>
                                <button
                                    x-show="f.status === 'done' || f.status === 'error'"
                                    @click="removeFile(f.uuid)"
                                    class="text-gray-500 hover:text-red-400 transition"
                                    title="Remove"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </span>
                        </div>
                        <div class="h-1.5 bg-gray-700 rounded-full overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-150"
                                :class="f.status === 'error' ? 'bg-red-500' : (f.status === 'done' ? 'bg-green-500' : 'brand-bg')"
                                :style="`width: ${f.status === 'error' || f.status === 'done' ? 100 : f.progress}%`"
                            ></div>
                        </div>
                    </div>
                </template>
            </div>

            <p x-show="queue.length" class="text-gray-500 text-xs">
                <span x-text="doneCount('video')"></span> video(s) and <span x-text="doneCount('image')"></span> photo(s) uploaded.
            </p>
        </section>

        {{-- Submit --}}
        <section class="bg-gray-900 rounded-2xl p-6 space-y-3">
            <p x-show="submitError" x-text="submitError" class="text-red-400 text-sm"></p>

            <button
                @click="submit()"
                :disabled="!canSubmit"
                class="w-full brand-bg text-white font-semibold py-3 rounded-xl hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <svg x-show="submitting" class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                <span x-text="submitting ? 'Submitting…' : (uploadsInProgress ? 'Uploading files…' : 'Submit files')"></span>
            </button>
            <p class="text-gray-600 text-xs text-center">Your name, the guest's name and email, and at least one uploaded file are required.</p>
        </section>

    </main>

    <script>
    const CHUNK_SIZE = 2 * 1024 * 1024; // 2 MB per chunk

    function portal() {
        return {
            uploaderName:  '',
            receiverName:  '',
            receiverEmail: '',
            message:       '',

            // [{ uuid, name, size, kind: video|image, status: pending|uploading|done|error, progress, error }]
            queue:       [],
            uploading:   false, // worker running?
            submitting:  false,
            submitted:   false,
            submitError: null,

            get uploadsInProgress() {
                return this.queue.some(f => f.status === 'pending' || f.status === 'uploading');
            },

            get canSubmit() {
                return !this.submitting
                    && !this.uploadsInProgress
                    && this.uploaderName.trim() !== ''
                    && this.receiverName.trim() !== ''
                    && this.receiverEmail.trim() !== ''
                    && this.queue.some(f => f.status === 'done');
            },

            doneCount(kind) {
                return this.queue.filter(f => f.kind === kind && f.status === 'done').length;
            },

            formatSize(bytes) {
                if (!bytes) return '';
                const mb = bytes / (1024 * 1024);
                return mb < 1024 ? mb.toFixed(mb < 10 ? 1 : 0) + ' MB' : (mb / 1024).toFixed(1) + ' GB';
            },

            // ----------------------------------------------------------------
            // File selection — queue everything at once, upload sequentially
            // ----------------------------------------------------------------
            dropFiles(event) {
                this._enqueue(Array.from(event.dataTransfer.files));
            },

            addFiles(event) {
                const files = Array.from(event.target.files);
                event.target.value = '';
                this._enqueue(files);
            },

            _enqueue(files) {
                for (const file of files) {
                    let kind = null;
                    if (file.type.startsWith('video/')) kind = 'video';
                    else if (['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) kind = 'image';
                    if (!kind) continue; // skip unsupported files silently

                    this.queue.push({
                        uuid:     crypto.randomUUID(),
                        file:     file,
                        name:     file.name,
                        size:     file.size,
                        kind:     kind,
                        status:   'pending',
                        progress: 0,
                        error:    null,
                    });
                }
                this._processQueue();
            },

            _job(uuid) {
                return this.queue.find(f => f.uuid === uuid);
            },

            // Single worker: uploads pending files one at a time so newly added
            // files simply join the back of the queue.
            async _processQueue() {
                if (this.uploading) return;
                this.uploading = true;

                try {
                    let next;
                    while ((next = this.queue.find(f => f.status === 'pending'))) {
                        await this._uploadFile(next.uuid);
                    }
                } finally {
                    this.uploading = false;
                }
            },

            async _uploadFile(uuid) {
                const job = this._job(uuid);
                if (!job) return;
                job.status = 'uploading';

                const file        = job.file;
                const totalChunks = Math.ceil(file.size / CHUNK_SIZE);

                try {
                    for (let i = 0; i < totalChunks; i++) {
                        const chunk = file.slice(i * CHUNK_SIZE, (i + 1) * CHUNK_SIZE);
                        const form  = new FormData();
                        form.append('chunk',    chunk);
                        form.append('uuid',     uuid);
                        form.append('index',    i);
                        form.append('total',    totalChunks);
                        form.append('filename', file.name);
                        form.append('type',     job.kind);

                        const resp = await fetch('/portal/chunk', {
                            method:  'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body:    form,
                        });

                        if (!resp.ok) {
                            const data = await resp.json().catch(() => ({}));
                            throw new Error(data.message || `Upload failed (${resp.status})`);
                        }

                        const j = this._job(uuid);
                        if (j) j.progress = Math.round(((i + 1) / totalChunks) * 100);
                    }

                    const j = this._job(uuid);
                    if (j) j.status = 'done';
                } catch (err) {
                    const j = this._job(uuid);
                    if (j) { j.status = 'error'; j.error = err.message; }
                }
            },

            removeFile(uuid) {
                const idx = this.queue.findIndex(f => f.uuid === uuid);
                if (idx !== -1) this.queue.splice(idx, 1);
            },

            // ----------------------------------------------------------------
            // Submit
            // ----------------------------------------------------------------
            async submit() {
                if (!this.canSubmit) return;
                this.submitting  = true;
                this.submitError = null;

                try {
                    const done = this.queue.filter(f => f.status === 'done');
                    const resp = await fetch('/portal/submit', {
                        method:  'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept':       'application/json',
                        },
                        body: JSON.stringify({
                            uploader_name:  this.uploaderName.trim(),
                            receiver_name:  this.receiverName.trim(),
                            receiver_email: this.receiverEmail.trim(),
                            message:        this.message.trim() || null,
                            videos:         done.filter(f => f.kind === 'video').map(f => f.uuid),
                            images:         done.filter(f => f.kind === 'image').map(f => f.uuid),
                        }),
                    });

                    if (!resp.ok) {
                        const data = await resp.json().catch(() => ({}));
                        throw new Error(data.message || 'Submission failed — please try again.');
                    }

                    this.submitted = true;
                } catch (err) {
                    this.submitError = err.message;
                } finally {
                    this.submitting = false;
                }
            },

            reset() {
                this.receiverName  = '';
                this.receiverEmail = '';
                this.message       = '';
                this.queue         = [];
                this.submitted     = false;
                this.submitError   = null;
                // uploaderName intentionally kept — same person uploading again
            },
        };
    }
    </script>

</body>
</html>
