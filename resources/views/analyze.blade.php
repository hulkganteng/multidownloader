@extends('layouts.app')

@section('title', __('Choose a format') . ' · DownloadIn')

@section('content')
@php
    $platform = $metadata['platform'] ?? '';
    $originalUrl = $metadata['original_url'] ?? '';

    $ytId = null;
    if ($platform === 'youtube') {
        parse_str((string) parse_url($originalUrl, PHP_URL_QUERY), $query);
        $ytId = $query['v'] ?? null;
        if (! $ytId) {
            $path = basename((string) parse_url($originalUrl, PHP_URL_PATH));
            if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $path)) {
                $ytId = $path;
            }
        }
    }

    $isDirectVideo = $platform === 'direct'
        && in_array(strtolower((string) pathinfo(parse_url($originalUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov', 'm4v', 'mpg', 'mpeg', 'avi'], true);

    $hasPreview = ($platform === 'youtube' && $ytId) || $isDirectVideo;
    $sizeBytes = $metadata['size_bytes'] ?? null;
@endphp
<section class="soft-bg py-12 sm:py-16">
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 transition-colors hover:text-electric-600">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            {{ __('Use a different link') }}
        </a>

        <div class="mt-6 rounded-3xl border border-slate-200/80 bg-white p-6 shadow-soft-lg sm:p-8 lg:p-10">
            <div class="grid gap-8 lg:grid-cols-5 lg:gap-10">
                {{-- MEDIA DETAILS --}}
                <div class="lg:col-span-2">
                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 shadow-card">
                        @if(!empty($metadata['thumbnail_url']))
                            <img
                                src="{{ $metadata['thumbnail_url'] }}"
                                width="640"
                                height="360"
                                class="aspect-video w-full object-cover"
                                alt=""
                            >
                        @else
                            <div class="flex aspect-video items-center justify-center text-sm text-slate-400" role="img" aria-label="{{ __('No preview image available') }}">
                                <svg class="size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="13" height="12" rx="2"/><path d="m15 10 6-3v10l-6-3z"/></svg>
                            </div>
                        @endif

                        @if($hasPreview)
                            <button id="preview-open" type="button" aria-haspopup="dialog" aria-controls="preview-modal"
                                    class="group absolute inset-0 flex items-center justify-center bg-navy-950/0 transition-colors duration-200 hover:bg-navy-950/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-500">
                                <span class="flex size-14 items-center justify-center rounded-full bg-white/95 text-electric-600 shadow-soft-lg transition-transform duration-200 group-hover:scale-110">
                                    <svg class="size-6 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                                <span class="sr-only">{{ __('Preview media') }}</span>
                            </button>
                        @endif
                    </div>

                    <div class="mt-6 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-electric-50 px-3 py-1 text-xs font-semibold capitalize text-electric-700">
                                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ $platform }}
                            </span>
                            @if(!empty($metadata['duration_seconds']))
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 7v5l3 2"/><circle cx="12" cy="12" r="9"/></svg>
                                    {{ gmdate('i:s', $metadata['duration_seconds']) }}
                                </span>
                            @endif
                            @if($sizeBytes)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                                    {{ \Illuminate\Support\Number::fileSize($sizeBytes) }}
                                </span>
                            @endif
                        </div>
                        <h1 class="mt-3 text-pretty text-2xl font-bold tracking-tight text-navy-900">{{ $metadata['title'] }}</h1>
                    </div>
                </div>

                {{-- DOWNLOAD SETTINGS --}}
                <div id="settings-column" class="lg:col-span-3">
                    <form id="process-form" action="{{ route('process') }}" method="POST">
                        @csrf
                        <input type="hidden" name="url" value="{{ $metadata['original_url'] }}">
                        <input type="hidden" name="title" value="{{ $metadata['title'] }}">
                        <input type="hidden" name="thumbnail_url" value="{{ $metadata['thumbnail_url'] }}">
                        <input type="hidden" name="duration_seconds" value="{{ $metadata['duration_seconds'] }}">

                        <div>
                            <h2 class="text-2xl font-bold tracking-tight text-navy-900">{{ __('Choose a format') }}</h2>
                            <p class="mt-1.5 text-sm text-slate-500">{{ __('Your last choice is remembered for next time.') }}</p>
                        </div>

                        <fieldset class="mt-7">
                            <legend class="text-sm font-semibold text-navy-800">{{ __('Format') }}</legend>
                            <div id="format-selector" class="mt-3 grid grid-cols-2 gap-3">
                                @foreach($metadata['formats'] as $format => $options)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="format"
                                            value="{{ $format }}"
                                            data-pref
                                            class="peer sr-only"
                                            {{ $loop->first ? 'checked' : '' }}
                                        >
                                        <span class="flex min-h-16 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-base font-semibold uppercase text-slate-600 transition-all duration-200 peer-checked:border-electric-500 peer-checked:bg-electric-50 peer-checked:text-electric-700 peer-checked:shadow-card peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-electric-600">
                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                @if($format === 'mp3')
                                                    <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
                                                @else
                                                    <rect x="2" y="6" width="13" height="12" rx="2"/><path d="m15 10 6-3v10l-6-3z"/>
                                                @endif
                                            </svg>
                                            {{ $format }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        @if(isset($metadata['formats']['mp4']))
                            <div id="mp4-options" class="mt-6">
                                <label for="video-quality" class="text-sm font-semibold text-navy-800">{{ __('Video quality') }}</label>
                                <div class="relative mt-2">
                                    <select id="video-quality" name="quality" data-pref class="w-full appearance-none rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3.5 pr-11 text-sm font-medium text-navy-900 outline-none transition-all focus:border-electric-400 focus:bg-white focus:ring-4 focus:ring-electric-100">
                                        @foreach($metadata['formats']['mp4'] as $quality)
                                            <option value="{{ $quality }}">{{ $quality === 'default' ? __('Best available') : $quality.'p'.(in_array($quality, ['720', '1080', '1440', '2160']) ? ['720' => ' HD', '1080' => ' Full HD', '1440' => ' 2K', '2160' => ' 4K'][$quality] : '') }}</option>
                                        @endforeach
                                    </select>
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </span>
                                </div>
                            </div>
                        @endif

                        @if(isset($metadata['formats']['mp3']))
                            <div id="mp3-options" class="mt-6 hidden">
                                <label for="audio-bitrate" class="text-sm font-semibold text-navy-800">{{ __('Audio quality') }}</label>
                                <div class="relative mt-2">
                                    <select id="audio-bitrate" name="bitrate" data-pref class="w-full appearance-none rounded-2xl border border-slate-200 bg-slate-50/60 px-4 py-3.5 pr-11 text-sm font-medium text-navy-900 outline-none transition-all focus:border-electric-400 focus:bg-white focus:ring-4 focus:ring-electric-100">
                                        @foreach($metadata['formats']['mp3'] as $bitrate)
                                            <option value="{{ $bitrate }}">{{ $bitrate }}</option>
                                        @endforeach
                                    </select>
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400" aria-hidden="true">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                    </span>
                                </div>
                            </div>
                        @endif

                        <div id="process-error" class="mt-6 hidden rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert"></div>

                        <div class="mt-8 border-t border-slate-200 pt-6">
                            <button type="submit"
                                    class="inline-flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-electric-600 to-electric-500 px-6 text-base font-semibold text-white shadow-glow-blue transition-all duration-200 hover:from-electric-700 hover:to-electric-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-600 disabled:cursor-wait disabled:opacity-70">
                                <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-submit-spinner aria-hidden="true"></span>
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>
                                </svg>
                                <span data-download-label>{{ __('Prepare download') }}</span>
                            </button>
                            <p class="mt-3 text-center text-sm text-slate-400">{{ __('Large files may take a few minutes to prepare.') }}</p>
                        </div>
                    </form>
                </div>
            </div>

            {{-- INLINE STATUS --}}
            @include('partials.status-progress')
        </div>
    </div>
</section>

{{-- PREVIEW MODAL --}}
@if($hasPreview)
<div id="preview-modal" role="dialog" aria-modal="true" aria-labelledby="preview-title"
     class="invisible fixed inset-0 z-50 flex items-center justify-center bg-navy-950/80 p-4 opacity-0 backdrop-blur-sm transition-all duration-200">
    <div class="relative w-full max-w-4xl rounded-2xl bg-navy-950 shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4">
            <h3 id="preview-title" class="text-sm font-semibold text-slate-300">{{ __('Media preview') }}</h3>
            <button id="preview-close" type="button" aria-label="{{ __('Close preview') }}"
                    class="inline-flex size-9 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-white/10 hover:text-white">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="aspect-video w-full overflow-hidden bg-black">
            @if($platform === 'youtube' && $ytId)
                <iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/{{ $ytId }}?autoplay=1" title="{{ __('Media preview') }}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            @else
                <video class="h-full w-full" src="{{ $originalUrl }}" controls autoplay playsinline></video>
            @endif
        </div>
        <div class="flex justify-end px-5 py-4">
            <a href="{{ $originalUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-electric-300 transition-colors hover:text-electric-200">
                {{ __('Open original') }}
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
            </a>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    const platform = @json($platform);
    const prefsKey = `downloadin.prefs.${platform}`;
    const formatInputs = document.querySelectorAll('input[name="format"]');
    const mp4Options = document.getElementById('mp4-options');
    const mp3Options = document.getElementById('mp3-options');
    const videoQuality = document.getElementById('video-quality');
    const audioBitrate = document.getElementById('audio-bitrate');
    const downloadLabel = document.querySelector('[data-download-label]');
    const settingsColumn = document.getElementById('settings-column');
    const processError = document.getElementById('process-error');
    const labels = {
        mp3: @json(__('Download MP3')),
        mp4: @json(__('Download MP4')),
        file: @json(__('Download file')),
    };

    function readPrefs() {
        try { return JSON.parse(localStorage.getItem(prefsKey) || '{}'); } catch { return {}; }
    }

    function writePrefs(prefs) {
        try { localStorage.setItem(prefsKey, JSON.stringify(prefs)); } catch { /* storage unavailable */ }
    }

    function savePrefs() {
        const prefs = { format: document.querySelector('input[name="format"]:checked')?.value || null };
        if (videoQuality) prefs.quality = videoQuality.value;
        if (audioBitrate) prefs.bitrate = audioBitrate.value;
        writePrefs(prefs);
    }

    function applyPrefs() {
        const prefs = readPrefs();
        if (prefs.format) {
            const match = [...formatInputs].find((i) => i.value === prefs.format);
            if (match) match.checked = true;
        }
        if (prefs.quality && videoQuality) {
            if ([...videoQuality.options].some((o) => o.value === prefs.quality)) videoQuality.value = prefs.quality;
        }
        if (prefs.bitrate && audioBitrate) {
            if ([...audioBitrate.options].some((o) => o.value === prefs.bitrate)) audioBitrate.value = prefs.bitrate;
        }
    }

    function updateFormatOptions() {
        const format = document.querySelector('input[name="format"]:checked')?.value;
        mp4Options?.classList.toggle('hidden', format !== 'mp4');
        mp3Options?.classList.toggle('hidden', format !== 'mp3');
        if (downloadLabel) downloadLabel.textContent = labels[format] || labels.file;
    }

    formatInputs.forEach((input) => {
        input.addEventListener('change', () => { updateFormatOptions(); savePrefs(); });
    });
    [videoQuality, audioBitrate].forEach((el) => el?.addEventListener('change', savePrefs));
    applyPrefs();
    updateFormatOptions();

    document.getElementById('process-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const button = this.querySelector('button[type="submit"]');
        const spinner = button.querySelector('[data-submit-spinner]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        spinner.classList.remove('hidden');
        processError.classList.add('hidden');

        try {
            const response = await fetch(this.action, {
                method: 'POST',
                body: new FormData(this),
                headers: { Accept: 'application/json' },
            });
            const data = await response.json();

            if (!response.ok) {
                const message = data.errors
                    ? Object.values(data.errors)[0]?.[0]
                    : (data.message || '{{ __('Could not start the download.') }}');
                throw new Error(message);
            }

            settingsColumn.classList.add('hidden');
            window.startDownloadPolling(data.uuid, {
                title: @json($metadata['title']),
            });
        } catch (error) {
            processError.textContent = error.message || '{{ __('Could not start the download.') }}';
            processError.classList.remove('hidden');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            spinner.classList.add('hidden');
        }
    });

    const previewOpen = document.getElementById('preview-open');
    const previewModal = document.getElementById('preview-modal');
    const previewClose = document.getElementById('preview-close');
    if (previewOpen && previewModal) {
        previewOpen.addEventListener('click', () => {
            previewModal.classList.remove('invisible', 'opacity-0');
        });
        previewClose.addEventListener('click', () => {
            previewModal.classList.add('invisible', 'opacity-0');
        });
        previewModal.addEventListener('click', (event) => {
            if (event.target === previewModal) previewModal.classList.add('invisible', 'opacity-0');
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') previewModal.classList.add('invisible', 'opacity-0');
        });
    }
</script>
@endpush
