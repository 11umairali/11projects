<x-layouts.admin :title="__('System Update')" :subtitle="__('Upload a new version safely — your data, .env, and uploaded media are never touched.')">
    <div class="space-y-6" x-data="updater()">

        {{-- ════════════════════════════════════════════════════════════════
             CURRENT VERSION CARD
             Shows what's installed right now. The blade is invoked from
             /admin/update; UpdaterService::currentVersion() reads
             config/version.php.
             ════════════════════════════════════════════════════════════════ --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted">{{ __('Currently Installed') }}</p>
                    <p class="mt-1 text-3xl font-bold text-ink">v{{ $currentVersion }}</p>
                    <p class="text-xs text-muted mt-0.5">{{ __('Build') }} {{ $currentBuild }} · {{ config('version.name', config('app.name')) }}</p>
                </div>
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 16v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h2m4-2v12m0 0l-4-4m4 4l4-4M16 4h2a2 2 0 012 2v7"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════
             5-STEP WIZARD
             Each step submits to a controller endpoint that returns JSON.
             Steps lock in sequence — you can't run #4 before #3 succeeds.
             stepStatus tracks idle/running/done/error per step.
             ════════════════════════════════════════════════════════════════ --}}
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <div>
                <p class="panel-heading">{{ __('Update Process') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('Run each step in order. Your code is backed up first; database rows and uploaded files are preserved throughout.') }}</p>
            </div>

            <div class="mt-6 space-y-3">

                {{-- Step 1: Backup --}}
                <div class="rounded-xl border p-4 transition-colors" :class="stepClasses(1)">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0"
                                 :class="badgeClasses(1)">
                                <span x-show="stepStatus[1] !== 'done'">1</span>
                                <svg x-show="stepStatus[1] === 'done'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-ink">{{ __('Backup files & database') }}</p>
                                <p class="text-xs text-muted">{{ __('Creates a code ZIP and SQL dump in storage/app/backups so you can roll back if anything goes wrong.') }}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary flex-shrink-0"
                                @click="runStep(1)" :disabled="(step !== 0 && step !== 1) || loading">
                            <span x-show="stepStatus[1] === 'idle'">{{ __('Start backup') }}</span>
                            <span x-show="stepStatus[1] === 'running'">{{ __('Backing up...') }}</span>
                            <span x-show="stepStatus[1] === 'done'">{{ __('Done') }}</span>
                            <span x-show="stepStatus[1] === 'error'">{{ __('Retry') }}</span>
                        </button>
                    </div>
                    <template x-if="stepMessage[1]">
                        <p class="mt-2 text-xs ml-11" :class="stepStatus[1] === 'error' ? 'text-danger' : 'text-success'" x-text="stepMessage[1]"></p>
                    </template>
                </div>

                {{-- Step 2: Upload ZIP --}}
                <div class="rounded-xl border p-4 transition-colors" :class="stepClasses(2)">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0"
                                 :class="badgeClasses(2)">
                                <span x-show="stepStatus[2] !== 'done'">2</span>
                                <svg x-show="stepStatus[2] === 'done'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-ink">{{ __('Upload update ZIP') }}</p>
                                <p class="text-xs text-muted">{{ __('Pick the .zip you downloaded from your vendor. Version inside is verified before anything else.') }}</p>
                            </div>
                        </div>
                        <label class="btn-secondary flex-shrink-0 cursor-pointer"
                               :class="{ 'opacity-50 pointer-events-none': step < 1 || loading }">
                            <span x-show="stepStatus[2] === 'idle'">{{ __('Choose ZIP') }}</span>
                            <span x-show="stepStatus[2] === 'running'">{{ __('Uploading...') }}</span>
                            <span x-show="stepStatus[2] === 'done'">{{ __('Uploaded') }}</span>
                            <span x-show="stepStatus[2] === 'error'">{{ __('Try another') }}</span>
                            <input type="file" accept=".zip" class="hidden" @change="uploadZip($event)" :disabled="step < 1 || loading">
                        </label>
                    </div>
                    {{-- Upload progress bar (visible during upload) --}}
                    <template x-if="stepStatus[2] === 'running' || (uploadFile && stepStatus[2] !== 'idle')">
                        <div class="mt-3 ml-11 space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-muted truncate flex-1 min-w-0" x-text="uploadFile ? (uploadFile.name + ' (' + formatBytes(uploadFile.size) + ')') : ''"></span>
                                <span class="font-mono font-semibold tabular-nums ml-2" :class="stepStatus[2] === 'error' ? 'text-danger' : 'text-brand'">
                                    <span x-text="uploadProgress"></span>%
                                </span>
                            </div>
                            <div class="w-full h-2 bg-surface rounded-full overflow-hidden border border-border/50">
                                <div class="h-full transition-all duration-150"
                                     :class="stepStatus[2] === 'error' ? 'bg-danger' : (uploadProgress === 100 ? 'bg-success' : 'bg-brand')"
                                     :style="`width: ${uploadProgress}%`"></div>
                            </div>
                            <p class="text-[11px] text-muted" x-show="uploadProgress < 100 && stepStatus[2] === 'running'">
                                <span x-text="formatBytes(uploadLoaded)"></span> / <span x-text="formatBytes(uploadTotal)"></span>
                                <span x-show="uploadSpeed > 0">
                                    &middot; <span x-text="formatBytes(uploadSpeed)"></span>/s
                                    &middot; ETA <span x-text="formatEta(uploadEta)"></span>
                                </span>
                            </p>
                            <p class="text-[11px] text-muted" x-show="uploadProgress === 100 && stepStatus[2] === 'running'">
                                {{ __('Upload complete — server is verifying the package...') }}
                            </p>
                        </div>
                    </template>

                    {{-- Detailed error panel — visible when upload fails so the admin
                         can see exactly what the server returned (not just a generic
                         "Upload failed"). Shows HTTP status, raw server response,
                         and where in the file the failure happened. --}}
                    <template x-if="uploadError">
                        <div class="mt-3 ml-11 rounded-lg border border-danger/40 bg-danger/5 p-3 space-y-2">
                            <div class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-danger mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-danger" x-text="uploadError.title"></p>
                                    <p class="text-[11px] text-danger/80 mt-0.5" x-text="uploadError.detail"></p>
                                </div>
                            </div>

                            <template x-if="uploadError.failedAt">
                                <p class="text-[11px] text-muted ml-6">
                                    {{ __('Failed at') }} <span class="font-mono" x-text="uploadError.failedAt"></span>
                                </p>
                            </template>

                            <template x-if="uploadError.responseBody">
                                <details class="ml-6 group">
                                    <summary class="text-[11px] text-muted cursor-pointer hover:text-ink select-none">
                                        {{ __('Server response') }} <span class="opacity-60 group-open:hidden">▶</span><span class="opacity-60 hidden group-open:inline">▼</span>
                                    </summary>
                                    <pre class="mt-2 p-2 bg-surface border border-border rounded text-[10px] text-ink overflow-x-auto max-h-40 whitespace-pre-wrap break-all" x-text="uploadError.responseBody"></pre>
                                </details>
                            </template>

                            <template x-if="uploadError.suggestions && uploadError.suggestions.length > 0">
                                <div class="ml-6 pt-1 border-t border-danger/20">
                                    <p class="text-[11px] font-semibold text-ink mt-1.5">{{ __('Likely fix:') }}</p>
                                    <ul class="mt-0.5 space-y-0.5">
                                        <template x-for="s in uploadError.suggestions" :key="s">
                                            <li class="text-[11px] text-muted">&rsaquo; <span x-text="s"></span></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="stepMessage[2]">
                        <p class="mt-2 text-xs ml-11" :class="stepStatus[2] === 'error' ? 'text-danger' : 'text-success'" x-text="stepMessage[2]"></p>
                    </template>
                </div>

                {{-- Step 3: Apply --}}
                <div class="rounded-xl border p-4 transition-colors" :class="stepClasses(3)">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0"
                                 :class="badgeClasses(3)">
                                <span x-show="stepStatus[3] !== 'done'">3</span>
                                <svg x-show="stepStatus[3] === 'done'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-ink">{{ __('Apply code update') }}</p>
                                <p class="text-xs text-muted">{{ __('Extracts the ZIP and overwrites code paths only. Your .env, storage/, vendor/, and uploaded media are skipped.') }}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary flex-shrink-0"
                                @click="runStep(3)" :disabled="step < 2 || loading">
                            <span x-show="stepStatus[3] === 'idle'">{{ __('Apply') }}</span>
                            <span x-show="stepStatus[3] === 'running'">{{ __('Applying...') }}</span>
                            <span x-show="stepStatus[3] === 'done'">{{ __('Applied') }}</span>
                            <span x-show="stepStatus[3] === 'error'">{{ __('Retry') }}</span>
                        </button>
                    </div>
                    <template x-if="stepMessage[3]">
                        <p class="mt-2 text-xs ml-11" :class="stepStatus[3] === 'error' ? 'text-danger' : 'text-success'" x-text="stepMessage[3]"></p>
                    </template>
                </div>

                {{-- Step 4: Migrate --}}
                <div class="rounded-xl border p-4 transition-colors" :class="stepClasses(4)">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0"
                                 :class="badgeClasses(4)">
                                <span x-show="stepStatus[4] !== 'done'">4</span>
                                <svg x-show="stepStatus[4] === 'done'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-ink">{{ __('Run database migrations') }}</p>
                                <p class="text-xs text-muted">{{ __('Adds new tables and columns from the update. Existing rows are NEVER deleted — Laravel only runs migrations not already recorded.') }}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary flex-shrink-0"
                                @click="runStep(4)" :disabled="step < 3 || loading">
                            <span x-show="stepStatus[4] === 'idle'">{{ __('Migrate') }}</span>
                            <span x-show="stepStatus[4] === 'running'">{{ __('Migrating...') }}</span>
                            <span x-show="stepStatus[4] === 'done'">{{ __('Migrated') }}</span>
                            <span x-show="stepStatus[4] === 'error'">{{ __('Retry') }}</span>
                        </button>
                    </div>
                    <template x-if="stepMessage[4]">
                        <p class="mt-2 text-xs ml-11" :class="stepStatus[4] === 'error' ? 'text-danger' : 'text-success'" x-text="stepMessage[4]"></p>
                    </template>
                </div>

                {{-- Step 5: Finalize --}}
                <div class="rounded-xl border p-4 transition-colors" :class="stepClasses(5)">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold flex-shrink-0"
                                 :class="badgeClasses(5)">
                                <span x-show="stepStatus[5] !== 'done'">5</span>
                                <svg x-show="stepStatus[5] === 'done'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm text-ink">{{ __('Finalize & health check') }}</p>
                                <p class="text-xs text-muted">{{ __('Clears caches, verifies database/storage/.env, removes the staging ZIP.') }}</p>
                            </div>
                        </div>
                        <button type="button" class="btn-primary flex-shrink-0"
                                @click="runStep(5)" :disabled="step < 4 || loading">
                            <span x-show="stepStatus[5] === 'idle'">{{ __('Finalize') }}</span>
                            <span x-show="stepStatus[5] === 'running'">{{ __('Finalizing...') }}</span>
                            <span x-show="stepStatus[5] === 'done'">{{ __('Complete!') }}</span>
                            <span x-show="stepStatus[5] === 'error'">{{ __('Retry') }}</span>
                        </button>
                    </div>
                    <template x-if="stepMessage[5]">
                        <p class="mt-2 text-xs ml-11" :class="stepStatus[5] === 'error' ? 'text-danger' : 'text-success'" x-text="stepMessage[5]"></p>
                    </template>

                    {{-- Health check results --}}
                    <template x-if="health">
                        <div class="mt-4 ml-11 grid grid-cols-2 gap-2 text-xs">
                            <template x-for="(val, key) in health" :key="key">
                                <div class="flex items-center gap-2">
                                    <span x-show="val" class="text-success">✓</span>
                                    <span x-show="!val" class="text-danger">✗</span>
                                    <span class="text-muted capitalize" x-text="key.replace(/_/g, ' ')"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════════════
             ROLLBACK CARD
             Lists every backup directory written by createBackup() so the
             admin can restore code + DB if an update went wrong. The path
             is path-traversal protected on the server side.
             ════════════════════════════════════════════════════════════════ --}}
        @if(count($backups) > 0)
        <div class="bg-surface-2 rounded-2xl border border-border p-6">
            <div>
                <p class="panel-heading">{{ __('Rollback') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('Restore code and database to a previous backup if something went wrong with the latest update.') }}</p>
            </div>

            <div class="mt-4 space-y-2">
                @foreach($backups as $backup)
                <div class="flex items-center justify-between gap-3 rounded-xl border border-border p-3 bg-surface">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-sm text-ink">v{{ $backup['version'] ?? 'unknown' }}</p>
                        <p class="text-xs text-muted">{{ \Carbon\Carbon::parse($backup['created_at'] ?? now())->format('d M Y, H:i') }}</p>
                    </div>
                    <button type="button" class="btn-secondary text-danger hover:text-danger flex-shrink-0"
                            @click="confirmRollback('{{ $backup['version'] ?? '' }}', '{{ addslashes($backup['path'] ?? '') }}')">
                        {{ __('Rollback') }}
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- The admin layout does not flush a `@stack('scripts')`, so we inline
         the Alpine helper directly. Without this, x-data="updater()" binds
         to an undefined function and every x-show condition silently fails,
         producing empty buttons with no step labels. --}}
    <script>
    function updater() {
        return {
            step: 0,
            loading: false,
            health: null,
            stepStatus: { 1: 'idle', 2: 'idle', 3: 'idle', 4: 'idle', 5: 'idle' },
            stepMessage: { 1: null, 2: null, 3: null, 4: null, 5: null },

            // Upload-specific state
            uploadFile: null,        // File reference for name/size display
            uploadProgress: 0,       // 0-100
            uploadLoaded: 0,         // bytes uploaded so far
            uploadTotal: 0,          // total bytes
            uploadSpeed: 0,          // bytes / sec
            uploadEta: 0,            // seconds remaining
            uploadError: null,       // {title, detail, failedAt, responseBody, suggestions[]}

            formatBytes(bytes) {
                if (!bytes && bytes !== 0) return '0 B';
                const units = ['B', 'KB', 'MB', 'GB'];
                let i = 0;
                let n = bytes;
                while (n >= 1024 && i < units.length - 1) {
                    n /= 1024;
                    i++;
                }
                return n.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
            },

            formatEta(seconds) {
                if (!seconds || !isFinite(seconds)) return '—';
                if (seconds < 60) return Math.round(seconds) + 's';
                const m = Math.floor(seconds / 60);
                const s = Math.round(seconds % 60);
                return m + 'm ' + s + 's';
            },

            // Border + background tint per step state. Uses MailTrixy
            // theme tokens so dark mode renders correctly.
            stepClasses(n) {
                const s = this.stepStatus[n];
                if (s === 'done')    return 'border-success/40 bg-success/5';
                if (s === 'running') return 'border-brand/40 bg-brand/5';
                if (s === 'error')   return 'border-danger/40 bg-danger/5';
                if (this.step >= n - 1) return 'border-border';
                return 'border-border/40 opacity-50';
            },

            badgeClasses(n) {
                const s = this.stepStatus[n];
                if (s === 'done')    return 'bg-success text-white';
                if (s === 'running') return 'bg-brand text-white';
                if (s === 'error')   return 'bg-danger text-white';
                return 'bg-surface text-muted border border-border';
            },

            async runStep(n) {
                this.loading = true;
                this.stepStatus[n] = 'running';
                this.stepMessage[n] = null;

                const urls = {
                    1: '{{ route("admin.update.backup") }}',
                    3: '{{ route("admin.update.apply") }}',
                    4: '{{ route("admin.update.migrate") }}',
                    5: '{{ route("admin.update.finalize") }}',
                };

                try {
                    const res = await fetch(urls[n], {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.stepStatus[n] = 'done';
                        this.stepMessage[n] = data.message;
                        this.step = n;
                        if (n === 5 && data.health) {
                            this.health = data.health;
                        }
                    } else {
                        this.stepStatus[n] = 'error';
                        this.stepMessage[n] = data.message || 'Step failed.';
                    }
                } catch (e) {
                    this.stepStatus[n] = 'error';
                    this.stepMessage[n] = 'Request failed: ' + e.message;
                }
                this.loading = false;
            },

            uploadZip(event) {
                const file = event.target.files[0];
                if (!file) {
                    console.log('[updater] No file selected');
                    return;
                }

                console.log('[updater] Selected ZIP:', { name: file.name, size: file.size, type: file.type });

                this.loading = true;
                this.stepStatus[2] = 'running';
                this.stepMessage[2] = null;
                this.uploadFile = file;
                this.uploadProgress = 0;
                this.uploadLoaded = 0;
                this.uploadTotal = file.size;
                this.uploadSpeed = 0;
                this.uploadEta = 0;
                this.uploadError = null;

                const fd = new FormData();
                fd.append('file', file);

                const xhr = new XMLHttpRequest();
                xhr.open('POST', '{{ route("admin.update.upload") }}', true);
                xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
                xhr.setRequestHeader('Accept', 'application/json');

                console.log('[updater] Starting upload to', '{{ route("admin.update.upload") }}');
                console.log('[updater] File size:', this.formatBytes(file.size), '— this will take a while on slow connections');

                // Progress: bytes uploaded as the request body is sent to the server
                const uploadStartTime = Date.now();
                let lastLoggedPct = -1;
                let lastProgressTime = Date.now();
                let lastProgressBytes = 0;
                let stallCheckTimer = null;

                // Stall detector — warn if no progress for 10 seconds
                const checkStall = () => {
                    const now = Date.now();
                    const stalledFor = Math.round((now - lastProgressTime) / 1000);
                    if (this.stepStatus[2] === 'running' && stalledFor > 10) {
                        console.warn(`[updater] ⚠ STALL DETECTED — no progress for ${stalledFor}s`);
                        console.warn('[updater] Likely cause: PHP upload_max_filesize / post_max_size, or web-server body limit (nginx client_max_body_size, Apache LimitRequestBody, or Cloudflare 100MB).');

                        // Surface the stall in the UI so the admin sees it
                        this.uploadError = {
                            title: `Upload stalled at ${this.uploadProgress}% (${this.formatBytes(this.uploadLoaded)} / ${this.formatBytes(this.uploadTotal)})`,
                            detail: `No bytes acknowledged by the server for ${stalledFor} seconds. The server most likely cut the connection because the request body exceeded its allowed size.`,
                            failedAt: `${this.formatBytes(this.uploadLoaded)} of ${this.formatBytes(this.uploadTotal)} (${this.uploadProgress}%)`,
                            responseBody: null,
                            suggestions: [
                                'Raise PHP upload_max_filesize and post_max_size to 512M (see public/.htaccess and public/.user.ini)',
                                'If your host runs PHP-FPM, restart PHP after editing .user.ini (or wait 5 min for the cache to refresh)',
                                'If your domain is behind Cloudflare, the body cap is 100 MB on Free/Pro — switch DNS to "DNS only" (gray cloud) for this upload',
                                'Some shared hosts cap uploads at 50–100 MB regardless of php.ini — check your control panel\'s PHP options',
                            ],
                        };
                    }
                };
                stallCheckTimer = setInterval(checkStall, 5000);

                xhr.upload.addEventListener('progress', (e) => {
                    if (!e.lengthComputable) return;
                    const pct = Math.round((e.loaded / e.total) * 100);
                    const now = Date.now();
                    const dt = (now - lastProgressTime) / 1000;
                    const dBytes = e.loaded - lastProgressBytes;

                    this.uploadLoaded = e.loaded;
                    this.uploadTotal = e.total;
                    this.uploadProgress = pct;

                    // Instantaneous speed (over the last progress window)
                    if (dt > 0.1) {
                        this.uploadSpeed = dBytes / dt;
                        const remainingBytes = e.total - e.loaded;
                        this.uploadEta = this.uploadSpeed > 0 ? remainingBytes / this.uploadSpeed : 0;
                    }

                    lastProgressTime = now;
                    lastProgressBytes = e.loaded;

                    // Log every 1% so the user gets steady feedback
                    if (pct > lastLoggedPct || pct === 100) {
                        const elapsed = ((now - uploadStartTime) / 1000).toFixed(1);
                        console.log(`[updater] ${pct}% — ${this.formatBytes(e.loaded)} / ${this.formatBytes(e.total)} @ ${this.formatBytes(this.uploadSpeed)}/s — t+${elapsed}s`);
                        lastLoggedPct = pct;
                    }
                });

                xhr.upload.addEventListener('loadstart', () => {
                    console.log('[updater] Upload loadstart — connection opened');
                });

                xhr.upload.addEventListener('load', () => {
                    console.log('[updater] Upload bytes finished — waiting for server response');
                });

                xhr.upload.addEventListener('error', () => {
                    console.error('[updater] Upload network error');
                });

                xhr.upload.addEventListener('abort', () => {
                    console.warn('[updater] Upload aborted');
                });

                // Server response (after the upload finishes + server processes it)
                xhr.addEventListener('load', () => {
                    if (stallCheckTimer) clearInterval(stallCheckTimer);
                    console.log('[updater] Server responded:', xhr.status, xhr.statusText);

                    const rawBody = xhr.responseText || '';
                    let data = null;
                    try {
                        data = JSON.parse(rawBody || '{}');
                    } catch (e) {
                        console.error('[updater] Server returned non-JSON body — likely an HTML error page from the web server / proxy, not from Laravel.');
                    }
                    console.log('[updater] Response status:', xhr.status, '— payload:', data || '(non-JSON)');

                    if (xhr.status >= 200 && xhr.status < 300 && data && data.success) {
                        this.uploadError = null;
                        this.stepStatus[2] = 'done';
                        this.stepMessage[2] = data.message;
                        this.step = 2;
                        console.log('[updater] ✓ Upload accepted — version', data.zip_version || '(unknown)');
                    } else {
                        this.stepStatus[2] = 'error';

                        // Extract the most useful error message we can find
                        let title;
                        let detail;
                        let suggestions = [];

                        if (data && data.message) {
                            // Laravel responded with a structured JSON error (best case)
                            title = `Upload rejected by MailTrixy (HTTP ${xhr.status})`;
                            detail = data.message;
                            if (data.errors) {
                                detail += ' — ' + Object.values(data.errors).flat().join(' ');
                            }
                            if (xhr.status === 422 && data.message.toLowerCase().includes('version')) {
                                suggestions.push('Re-download the update ZIP — its config/version.php must contain a version higher than your current installed version.');
                            }
                        } else if (xhr.status === 413) {
                            title = `HTTP 413 — Request Entity Too Large`;
                            detail = 'The web server (Apache/nginx) rejected the upload before it reached MailTrixy. This is a server-config limit, not a MailTrixy limit.';
                            suggestions = [
                                'Set upload_max_filesize and post_max_size to 512M in PHP (.htaccess or .user.ini)',
                                'On Nginx hosts: client_max_body_size 512M; in the server { } block',
                                'On Cloudflare Free/Pro: body is capped at 100 MB regardless of server config',
                            ];
                        } else if (xhr.status === 419) {
                            title = `HTTP 419 — CSRF Token Expired`;
                            detail = 'Your admin session expired during the upload. Refresh the page and try again.';
                            suggestions = ['Refresh the page (Ctrl+Shift+R) and re-upload from Step 2'];
                        } else if (xhr.status === 500 || xhr.status === 502 || xhr.status === 503 || xhr.status === 504) {
                            title = `HTTP ${xhr.status} — Server Error`;
                            detail = 'The server crashed or timed out while processing the upload. Check storage/logs/laravel.log for the stack trace.';
                            suggestions = [
                                'Increase max_execution_time and memory_limit (see public/.user.ini)',
                                'Check storage/logs/laravel.log for the actual exception',
                            ];
                        } else if (xhr.status === 0) {
                            title = 'Connection closed before response';
                            detail = 'The TCP connection was cut without a response. The web server, proxy, or Cloudflare killed it mid-upload.';
                            suggestions = [
                                'Raise upload_max_filesize / post_max_size to 512M',
                                'If behind Cloudflare, the 100 MB cap on Free/Pro applies — bypass for this upload',
                            ];
                        } else if (rawBody) {
                            // Non-JSON HTML error page — try to pull the readable bits out
                            title = `Upload failed (HTTP ${xhr.status})`;
                            const stripped = rawBody.replace(/<script[\s\S]*?<\/script>/gi, '')
                                                    .replace(/<style[\s\S]*?<\/style>/gi, '')
                                                    .replace(/<[^>]+>/g, ' ')
                                                    .replace(/\s+/g, ' ')
                                                    .trim();
                            detail = stripped.length > 280 ? stripped.slice(0, 280) + '…' : stripped;
                        } else {
                            title = `Upload failed (HTTP ${xhr.status})`;
                            detail = 'The server returned no response body.';
                        }

                        this.stepMessage[2] = title;
                        this.uploadError = {
                            title,
                            detail,
                            failedAt: `${this.formatBytes(this.uploadLoaded)} of ${this.formatBytes(this.uploadTotal)} (${this.uploadProgress}%)`,
                            responseBody: rawBody ? (rawBody.length > 4000 ? rawBody.slice(0, 4000) + '\n…(truncated)' : rawBody) : null,
                            suggestions,
                        };

                        console.error('[updater] ✗ Upload rejected — HTTP', xhr.status, '—', detail);
                        if (rawBody) console.error('[updater] Raw response body:', rawBody);
                    }

                    event.target.value = '';
                    this.loading = false;
                });

                xhr.addEventListener('error', () => {
                    if (stallCheckTimer) clearInterval(stallCheckTimer);
                    console.error('[updater] Network/transport error — server cut the connection mid-upload.');
                    this.stepStatus[2] = 'error';
                    this.stepMessage[2] = 'Network error during upload — server cut the connection.';
                    this.uploadError = {
                        title: 'Server cut the connection mid-upload',
                        detail: 'The TCP connection was closed before the upload finished. This is almost always caused by the web server, proxy, or Cloudflare rejecting a request body that exceeds its allowed size.',
                        failedAt: `${this.formatBytes(this.uploadLoaded)} of ${this.formatBytes(this.uploadTotal)} (${this.uploadProgress}%)`,
                        responseBody: null,
                        suggestions: [
                            'Raise PHP upload_max_filesize and post_max_size to 512M (public/.htaccess and public/.user.ini)',
                            'On Nginx: client_max_body_size 512M; — restart Nginx',
                            'On Cloudflare Free/Pro: 100 MB body cap — bypass the proxy for the upload',
                            'Some shared hosts cap uploads at 50–100 MB regardless — check your control panel',
                        ],
                    };
                    event.target.value = '';
                    this.loading = false;
                });

                xhr.addEventListener('timeout', () => {
                    if (stallCheckTimer) clearInterval(stallCheckTimer);
                    console.error('[updater] Request timed out');
                    this.stepStatus[2] = 'error';
                    this.stepMessage[2] = 'Upload timed out.';
                    this.uploadError = {
                        title: 'Upload timed out',
                        detail: 'The browser gave up waiting for a response from the server.',
                        failedAt: `${this.formatBytes(this.uploadLoaded)} of ${this.formatBytes(this.uploadTotal)} (${this.uploadProgress}%)`,
                        responseBody: null,
                        suggestions: ['Try again on a faster connection', 'Raise max_execution_time and max_input_time to 600s'],
                    };
                    event.target.value = '';
                    this.loading = false;
                });

                // No timeout — large ZIPs over slow connections need it
                xhr.timeout = 0;
                xhr.send(fd);
            },

            confirmRollback(version, path) {
                const ok = confirm('Restore code and database to v' + version + '?\n\nAny changes made since the backup will be lost.');
                if (ok) this.rollback(path);
            },

            async rollback(dir) {
                this.loading = true;
                try {
                    const res = await fetch('{{ route("admin.update.rollback") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ backup_dir: dir }),
                    });
                    const data = await res.json();
                    alert(data.message);
                    if (data.success) location.reload();
                } catch (e) {
                    alert('Rollback failed: ' + e.message);
                }
                this.loading = false;
            },
        };
    }
    </script>
</x-layouts.admin>
