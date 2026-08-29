@extends('layouts.app')

@section('title', __('Download Videos & Music From Your Favorite Platforms') . ' · DownloadIn')
@section('meta_description', __('Save supported public videos and audio from TikTok, Instagram, and YouTube in MP4 or MP3.'))

@section('content')

{{-- HERO --}}
<section id="downloader" class="soft-bg relative overflow-hidden">
    <div class="pointer-events-none absolute -top-40 left-1/2 h-[480px] w-[900px] -translate-x-1/2 rounded-full bg-electric-200/40 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute right-[-120px] top-24 h-72 w-72 rounded-full bg-sky-200/50 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute left-[-120px] top-96 h-72 w-72 rounded-full bg-electric-100/60 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-7xl px-5 pb-20 pt-16 sm:px-8 sm:pb-24 sm:pt-20">
        <div class="mx-auto max-w-3xl text-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-electric-200/70 bg-white/80 px-4 py-1.5 text-sm font-medium text-electric-700 shadow-card backdrop-blur">
                <span class="inline-flex h-2 w-2 rounded-full bg-electric-500" aria-hidden="true"></span>
                TikTok <span class="text-slate-300">•</span> Instagram <span class="text-slate-300">•</span> {{ __('YouTube') }} {{ __('Downloader') }}
            </span>

            <h1 class="mt-6 text-balance text-4xl font-extrabold leading-[1.1] tracking-tight text-navy-900 sm:text-5xl md:text-6xl">
                {{ __('Keep the media you need') }}<br class="hidden sm:block">
                <span class="gradient-text">{{ __('ready on your device') }}</span>
            </h1>

            <p class="mx-auto mt-6 max-w-xl text-pretty text-base text-slate-500 sm:text-lg">
                {{ __('Paste a public TikTok, Instagram, or YouTube link. Choose MP4 or MP3, then save the available media without creating an account.') }}
            </p>
        </div>

        {{-- DOWNLOADER BOX --}}
        <div class="relative mx-auto mt-12 max-w-3xl">
            <form id="analyze-form" action="{{ route('analyze') }}" method="POST"
                  class="rounded-3xl border border-slate-200/80 bg-white p-3 shadow-soft-lg sm:p-4">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label for="media-url" class="sr-only">{{ __('Paste a TikTok, Instagram, or YouTube link') }}</label>
                    <div class="relative min-w-0 flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400" aria-hidden="true">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/>
                                <path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>
                            </svg>
                        </span>
                        <input
                            id="media-url"
                            type="url"
                            name="url"
                            required
                            value="{{ old('url') }}"
                            autocomplete="url"
                            inputmode="url"
                            aria-describedby="url-help @error('url') url-error @enderror"
                            @error('url') aria-invalid="true" @enderror
                            placeholder="{{ __('Paste TikTok, Instagram, or YouTube link here...') }}"
                            class="min-w-0 w-full rounded-2xl border border-slate-200 bg-slate-50/60 py-4 pl-12 pr-4 text-base text-navy-900 outline-none transition-all placeholder:text-slate-400 focus:border-electric-400 focus:bg-white focus:ring-4 focus:ring-electric-100"
                        >
                    </div>
                    <button type="submit"
                            class="inline-flex min-h-14 shrink-0 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-electric-600 to-electric-500 px-6 text-base font-semibold text-white shadow-glow-blue transition-all duration-200 hover:from-electric-700 hover:to-electric-600 hover:shadow-soft-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-600 disabled:cursor-wait disabled:opacity-70">
                        <span class="hidden size-4 animate-spin rounded-full border-2 border-white/40 border-t-white" data-submit-spinner aria-hidden="true"></span>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12"/>
                            <path d="m7 10 5 5 5-5"/>
                            <path d="M5 21h14"/>
                        </svg>
                        <span>{{ __('Download') }}</span>
                    </button>
                </div>
            </form>
            <p id="url-help" class="mt-4 text-center text-sm text-slate-400">{{ __('Paste the link, choose the format and quality, then download.') }}</p>

            <p id="platform-hint" class="invisible mt-3 flex items-center justify-center gap-2 text-sm font-medium text-electric-600" aria-live="polite"></p>

            @if(session('error'))
                <div role="alert" class="mt-5 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-semibold">{{ __('Could not analyze this link.') }}</p>
                    <p class="mt-0.5">{{ session('error') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div id="url-error" role="alert" class="mt-5 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul role="list" class="list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- TRUST ROW --}}
        <div class="relative mx-auto mt-14 flex max-w-2xl flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-slate-500">
            <span class="inline-flex items-center gap-2">
                <svg class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                {{ __('Free to use') }}
            </span>
            <span class="inline-flex items-center gap-2">
                <svg class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                {{ __('High quality') }}
            </span>
            <span class="inline-flex items-center gap-2">
                <svg class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                {{ __('No sign up needed') }}
            </span>
            <span class="inline-flex items-center gap-2">
                <svg class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                {{ __('Fast processing') }}
            </span>
        </div>
    </div>
</section>

{{-- SUPPORTED PLATFORMS --}}
<section class="py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ __('Supported Platforms') }}</span>
            <h2 class="mt-3 text-balance text-3xl font-bold tracking-tight text-navy-900 sm:text-4xl">
                {{ __('Download From Your Favorite Platforms') }}
            </h2>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @php
                $platforms = [
                    ['name' => 'TikTok', 'desc' => __('Download TikTok videos quickly in available video quality.'), 'bg' => 'from-slate-900 to-slate-700'],
                    ['name' => 'Instagram', 'desc' => __('Download Instagram Reels, videos, and supported media.'), 'bg' => 'from-pink-500 to-amber-400'],
                    ['name' => 'YouTube', 'desc' => __('Download YouTube videos or convert supported content to MP3.'), 'bg' => 'from-red-500 to-rose-600'],
                ];
            @endphp
            @foreach($platforms as $i => $p)
                <a href="#downloader" class="group card-surface p-8 transition-all duration-300 hover:-translate-y-1 hover:border-electric-200 hover:shadow-soft-lg">
                    <span class="inline-flex size-14 items-center justify-center rounded-2xl bg-gradient-to-br {{ $p['bg'] }} text-white shadow-card">
                        <svg class="size-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            @if($i === 0)
                                <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.9 2.9 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
                            @elseif($i === 1)
                                <path d="M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zm0 3.68a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32zm0 2.16a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm6.4-3.85a1.44 1.44 0 1 0 0 2.88 1.44 1.44 0 0 0 0-2.88z"/>
                            @else
                                <path d="M21.6 7.2a2.68 2.68 0 0 0-1.89-1.9C18.1 5 12 5 12 5s-6.1 0-7.71.3A2.68 2.68 0 0 0 2.4 7.2 27.9 27.9 0 0 0 2.1 12a27.9 27.9 0 0 0 .3 4.8 2.68 2.68 0 0 0 1.89 1.9C5.9 19 12 19 12 19s6.1 0 7.71-.3a2.68 2.68 0 0 0 1.89-1.9 27.9 27.9 0 0 0 .3-4.8 27.9 27.9 0 0 0-.3-4.8zM10 15V9l5.2 3z"/>
                            @endif
                        </svg>
                    </span>
                    <h3 class="mt-6 text-xl font-semibold text-navy-900 group-hover:text-electric-600">{{ $p['name'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $p['desc'] }}</p>
                    <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-electric-600">
                        {{ __('Download now') }}
                        <svg class="size-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- FEATURES --}}
<section class="bg-slate-50/70 py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ __('Features') }}</span>
            <h2 class="mt-3 text-balance text-3xl font-bold tracking-tight text-navy-900 sm:text-4xl">{{ __('Everything You Need') }}</h2>
            <p class="mt-4 text-slate-500">{{ __('A clean, fast downloader built for the platforms you use every day.') }}</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $features = [
                    ['title' => __('Multiple Platforms'), 'desc' => __('TikTok, Instagram & YouTube support.'), 'icon' => 'stack'],
                    ['title' => __('MP4 Video Download'), 'desc' => __('Save videos in available resolutions.'), 'icon' => 'video'],
                    ['title' => __('MP3 Audio Download'), 'desc' => __('Extract and download audio easily.'), 'icon' => 'music'],
                    ['title' => __('Choose Video Quality'), 'desc' => __('Select 360p, 480p, 720p, 1080p, 2K, or 4K when available.'), 'icon' => 'settings'],
                    ['title' => __('Fast Processing'), 'desc' => __('Quick link processing and download preparation.'), 'icon' => 'bolt'],
                    ['title' => __('Clear Download Flow'), 'desc' => __('Paste the link, choose the available format, and save the result.'), 'icon' => 'sparkles'],
                ];
            @endphp
            @foreach($features as $f)
                <div class="card-surface group p-7 transition-all duration-300 hover:-translate-y-1 hover:shadow-soft">
                    <span class="inline-flex size-12 items-center justify-center rounded-xl bg-electric-50 text-electric-600 transition-colors duration-300 group-hover:bg-electric-600 group-hover:text-white">
                        @if($f['icon'] === 'stack')
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
                        @elseif($f['icon'] === 'video')
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="13" height="12" rx="2"/><path d="m15 10 6-3v10l-6-3z"/></svg>
                        @elseif($f['icon'] === 'music')
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
                        @elseif($f['icon'] === 'settings')
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="9" cy="18" r="2" fill="currentColor"/></svg>
                        @elseif($f['icon'] === 'bolt')
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
                        @else
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3.5"/></svg>
                        @endif
                    </span>
                    <h3 class="mt-5 text-lg font-semibold text-navy-900">{{ $f['title'] }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- HOW IT WORKS --}}
<section class="py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ __('How It Works') }}</span>
            <h2 class="mt-3 text-balance text-3xl font-bold tracking-tight text-navy-900 sm:text-4xl">{{ __('Download in 3 steps') }}</h2>
        </div>

        <div class="relative mt-12 grid gap-10 md:grid-cols-3 md:gap-8">
            <div class="pointer-events-none absolute left-[16%] right-[16%] top-8 hidden border-t-2 border-dashed border-electric-200 md:block" aria-hidden="true"></div>
            @php
                $steps = [
                    ['num' => '01', 'title' => __('Paste Link'), 'desc' => __('Copy a TikTok, Instagram, or YouTube URL and paste it into the downloader.')],
                    ['num' => '02', 'title' => __('Choose Format & Quality'), 'desc' => __('Select MP4 or MP3 and choose the available video resolution or audio quality.')],
                    ['num' => '03', 'title' => __('Download'), 'desc' => __('Press the download button and save the media to your device.')],
                ];
            @endphp
            @foreach($steps as $step)
                <div class="relative text-center">
                    <span class="relative z-10 mx-auto flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-electric-500 to-electric-700 text-lg font-bold text-white shadow-glow-blue">
                        {{ $step['num'] }}
                    </span>
                    <h3 class="mt-6 text-xl font-semibold text-navy-900">{{ $step['title'] }}</h3>
                    <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-slate-500">{{ $step['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-16 text-center">
            <a href="#downloader" class="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-electric-600 to-electric-500 px-8 text-base font-semibold text-white shadow-glow-blue transition-all duration-200 hover:from-electric-700 hover:to-electric-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-600">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>
                </svg>
                {{ __('Start Downloading') }}
            </a>
        </div>
    </div>
</section>

{{-- BLOG PREVIEW --}}
<section class="bg-slate-50/70 py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ __('Guides') }}</span>
                <h2 class="mt-3 text-balance text-3xl font-bold tracking-tight text-navy-900 sm:text-4xl">{{ __('Download with fewer failed links') }}</h2>
                <p class="mt-4 text-slate-500">{{ __('Learn which links work, how formats differ, and what to check when media cannot be read.') }}</p>
            </div>
            <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-electric-600 hover:text-electric-700">
                {{ __('Read all guides') }}
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach([
                [__('How to download a public TikTok video'), __('Check link access, choose MP4, and save the available video to your device.')],
                [__('MP4 or MP3: which format should you choose?'), __('Use MP4 when you need the picture and MP3 when you only need the audio.')],
                [__('Why a media link cannot be processed'), __('Private posts, removed content, regional limits, and expired links are common causes.')],
            ] as [$title, $summary])
                <article class="card-surface p-7">
                    <span class="text-xs font-semibold uppercase tracking-wider text-electric-600">{{ __('Guide') }}</span>
                    <h3 class="mt-3 text-lg font-semibold leading-snug text-navy-900">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $summary }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
    document.getElementById('analyze-form').addEventListener('submit', function () {
        const button = this.querySelector('button[type="submit"]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.querySelector('[data-submit-spinner]').classList.remove('hidden');
    });

    (function () {
        const input = document.getElementById('media-url');
        const hint = document.getElementById('platform-hint');
        if (!input || !hint) return;

        const platforms = {
            tiktok: { label: 'TikTok', icon: '🎵' },
            instagram: { label: 'Instagram', icon: '📸' },
            youtube: { label: 'YouTube', icon: '▶' },
            direct: { label: @json(__('Direct media')), icon: '📁' },
        };

        function detect(value) {
            let host;
            try { host = new URL(value).hostname.toLowerCase(); } catch { return null; }
            if (host === 'youtu.be' || host.endsWith('.youtube.com') || host === 'youtube-nocookie.com') return 'youtube';
            if (host === 'tiktok.com' || host.endsWith('.tiktok.com')) return 'tiktok';
            if (host === 'instagram.com' || host.endsWith('.instagram.com')) return 'instagram';
            if (/^https?:$/.test(new URL(value).protocol)) return 'direct';
            return null;
        }

        input.addEventListener('input', () => {
            const platform = detect(input.value);
            if (!platform) {
                hint.className = 'invisible mt-3 flex items-center justify-center gap-2 text-sm font-medium text-electric-600';
                hint.textContent = '';
                return;
            }
            const p = platforms[platform];
            hint.textContent = `${p.icon} ${p.label}`;
            hint.className = 'visible mt-3 flex items-center justify-center gap-2 text-sm font-medium text-electric-600';
        });
    })();
</script>
@endpush
