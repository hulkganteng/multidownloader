@php
    $apiBase = (string) route('api.tasks.show', ['task' => '__UUID__'], false);
    $apiBase = rtrim(str_replace('__UUID__', '', $apiBase), '/').'/';
@endphp

{{-- Shared download-status panel. Used by status.blade.php (server-rendered) and
     analyze.blade.php (inline, no page refresh). Exposes window.startDownloadPolling(uuid, opts). --}}
<div id="status-root" class="hidden">
    <div class="mt-10 border-t border-slate-200 pt-8">
        <div class="flex items-start gap-4">
            <span id="status-dot" class="mt-1.5 size-3 shrink-0 rounded-full bg-amber-500 shadow-[0_0_0_5px_rgba(245,158,11,0.15)]" aria-hidden="true"></span>
            <div class="min-w-0 flex-1">
                <p id="status-label" role="status" aria-live="polite" class="text-sm font-semibold uppercase tracking-wide text-electric-600">{{ __('Waiting to start') }}</p>
                <h2 id="status-title" class="mt-2 break-words text-pretty text-xl font-bold tracking-tight text-navy-900 sm:text-2xl"></h2>
            </div>
        </div>

        <div id="progress-section" class="mt-8">
            <div class="flex items-center justify-between gap-4 text-sm text-slate-600">
                <span class="font-medium">{{ __('Preparing file') }}</span>
                <span id="progress-text" class="tabular-nums font-semibold text-electric-600">0%</span>
            </div>
            <div id="progress-bar-container" role="progressbar" aria-label="Download progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" class="mt-3 h-2.5 overflow-hidden rounded-full bg-electric-100">
                <div id="progress-bar-fill" class="h-full rounded-full bg-gradient-to-r from-electric-600 to-electric-400 transition-[width] duration-500 motion-reduce:transition-none" style="width: 0%"></div>
            </div>
            <p class="mt-4 text-sm text-slate-500">{{ __('Keep this page open. The download button will appear when the file is ready.') }}</p>
        </div>

        <div id="actions-success" class="mt-8 hidden">
            <p class="text-sm text-slate-600">{{ __('Your file is ready. The link expires automatically.') }}</p>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('File') }}</dt>
                    <dd id="file-name" class="mt-0.5 break-all text-sm font-medium text-navy-900"></dd>
                </div>
                <div class="rounded-xl bg-slate-50 px-4 py-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Size') }}</dt>
                    <dd id="file-size" class="mt-0.5 text-sm font-medium text-navy-900"></dd>
                </div>
            </dl>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <a id="download-btn" href="#" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-electric-600 to-electric-500 px-6 py-3.5 text-sm font-semibold text-white shadow-glow-blue transition-all duration-200 hover:from-electric-700 hover:to-electric-600 sm:min-h-0">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                    {{ __('Download file') }}
                </a>
                <button id="copy-link-btn" type="button" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-700 transition-colors hover:border-electric-200 hover:bg-electric-50 hover:text-electric-700 sm:min-h-0">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span data-copy-label>{{ __('Copy link') }}</span>
                </button>
                <a href="{{ route('home') }}" class="inline-flex min-h-14 items-center justify-center rounded-2xl border border-slate-200 px-5 py-3.5 text-sm font-semibold text-slate-700 transition-colors hover:border-electric-200 hover:bg-electric-50 hover:text-electric-700 sm:min-h-0">
                    {{ __('Download another') }}
                </a>
            </div>
        </div>

        <div id="actions-error" class="mt-8 hidden rounded-2xl border border-red-100 bg-red-50 p-5 text-red-700" role="alert">
            <p class="font-semibold">{{ __('The file could not be prepared.') }}</p>
            <p id="error-message" class="mt-1 text-sm text-red-600"></p>
            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <button id="retry-status-btn" type="button" class="hidden min-h-11 rounded-2xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-700 sm:min-h-0">
                    {{ __('Check status again') }}
                </button>
                <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-red-200 bg-white px-5 py-2.5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-100 sm:min-h-0">
                    {{ __('Use another link') }}
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.startDownloadPolling = (function () {
        const statusCopy = {
            queued: @json(__('Waiting in queue')),
            processing: @json(__('Preparing your file')),
        };
        const statusMessages = {
            ready: @json(__('File ready')),
            failed: @json(__('Download failed')),
            checking: @json(__('Checking download status')),
            default: @json(__('Preparing your file')),
            errorMessage: @json(__('Check the source link and try again.')),
            pollError: @json(__('Could not check the download status. Check your connection and try again.')),
            copied: @json(__('Copied!')),
        };

        const apiBase = @json($apiBase);

        let pollTimer = null;
        let consecutiveFailures = 0;
        let pollingStopped = false;

        function els() {
            return {
                label: document.getElementById('status-label'),
                dot: document.getElementById('status-dot'),
                title: document.getElementById('status-title'),
                progressSection: document.getElementById('progress-section'),
                progressContainer: document.getElementById('progress-bar-container'),
                progressFill: document.getElementById('progress-bar-fill'),
                progressText: document.getElementById('progress-text'),
                actionsSuccess: document.getElementById('actions-success'),
                downloadButton: document.getElementById('download-btn'),
                copyLinkButton: document.getElementById('copy-link-btn'),
                copyLabel: document.querySelector('[data-copy-label]'),
                fileName: document.getElementById('file-name'),
                fileSize: document.getElementById('file-size'),
                actionsError: document.getElementById('actions-error'),
                errorMessage: document.getElementById('error-message'),
                retryStatusButton: document.getElementById('retry-status-btn'),
            };
        }

        function formatBytes(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '—';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let value = bytes;
            let i = 0;
            while (value >= 1024 && i < units.length - 1) { value /= 1024; i++; }
            return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
        }

        function updateUI(data, fallbackTitle) {
            const e = els();
            const progress = Math.min(100, Math.max(0, Number(data.progress) || 0));
            e.progressText.textContent = `${progress}%`;
            e.progressFill.style.width = `${progress}%`;
            e.progressContainer.setAttribute('aria-valuenow', String(progress));
            e.title.textContent = data.title || fallbackTitle || e.title.textContent;

            if (data.status === 'finished') {
                stopPolling();
                showSuccess(data, e);
            } else if (data.status === 'failed') {
                stopPolling();
                showError(data.error_message || statusMessages.errorMessage, e);
            } else {
                showLoading(statusCopy[data.status] || statusMessages.default, e);
            }
        }

        function showLoading(label, e) {
            e.label.textContent = label;
            e.dot.className = 'mt-1.5 size-3 shrink-0 rounded-full bg-amber-500 shadow-[0_0_0_5px_rgba(245,158,11,0.15)]';
            e.progressSection.classList.remove('hidden');
            e.actionsSuccess.classList.add('hidden');
            e.actionsError.classList.add('hidden');
        }

        function showSuccess(data, e) {
            e.label.textContent = statusMessages.ready;
            e.dot.className = 'mt-1.5 size-3 shrink-0 rounded-full bg-emerald-500 shadow-[0_0_0_5px_rgba(16,185,129,0.15)]';
            e.progressSection.classList.add('hidden');
            e.actionsError.classList.add('hidden');
            e.downloadButton.href = data.download_url;
            e.fileName.textContent = data.filename || '—';
            e.fileSize.textContent = formatBytes(data.size_bytes);
            e.copyLinkButton.dataset.url = data.download_url;
            e.copyLabel.textContent = statusMessages.copied;
            e.copyLinkButton.classList.remove('bg-emerald-50', 'text-emerald-700');
            e.actionsSuccess.classList.remove('hidden');
        }

        function showError(message, e) {
            e.label.textContent = statusMessages.failed;
            e.dot.className = 'mt-1.5 size-3 shrink-0 rounded-full bg-red-500 shadow-[0_0_0_5px_rgba(239,68,68,0.15)]';
            e.progressSection.classList.add('hidden');
            e.actionsSuccess.classList.add('hidden');
            e.errorMessage.textContent = message;
            e.retryStatusButton.classList.add('hidden');
            e.actionsError.classList.remove('hidden');
        }

        function stopPolling() {
            pollingStopped = true;
            if (pollTimer) clearTimeout(pollTimer);
        }

        function startPolling(apiUrl) {
            stopPolling();
            pollingStopped = false;
            consecutiveFailures = 0;
            showLoading(statusMessages.checking, els());
            pollOnce(apiUrl);
        }

        async function pollOnce(apiUrl) {
            const e = els();
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);
            try {
                const response = await fetch(apiUrl, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                consecutiveFailures = 0;
                updateUI(await response.json());
            } catch (error) {
                consecutiveFailures += 1;
                if (consecutiveFailures >= 3) {
                    stopPolling();
                    showError(statusMessages.pollError, els());
                }
            } finally {
                clearTimeout(timeout);
            }
            if (!pollingStopped) pollTimer = setTimeout(() => pollOnce(apiUrl), 2000);
        }

        return function startDownloadPolling(uuid, opts = {}) {
            const rootEl = document.getElementById('status-root');
            if (!rootEl) return;
            rootEl.classList.remove('hidden');
            const e = els();
            if (opts.title) e.title.textContent = opts.title;
            const apiUrl = opts.apiUrl || apiBase + uuid;

            e.retryStatusButton.addEventListener('click', () => startPolling(apiUrl));
            e.copyLinkButton.addEventListener('click', async () => {
                const url = e.copyLinkButton.dataset.url;
                if (!url) return;
                try {
                    await navigator.clipboard.writeText(url);
                    e.copyLabel.textContent = statusMessages.copied;
                    e.copyLinkButton.classList.add('bg-emerald-50', 'text-emerald-700');
                } catch { /* clipboard unavailable */ }
            });

            startPolling(apiUrl);
        };
    })();
</script>
@endpush
