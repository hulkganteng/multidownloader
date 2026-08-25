@php
    $navLinks = [
        ['label' => __('Home'), 'href' => route('home')],
        ['label' => __('Downloader'), 'href' => '#downloader'],
        ['label' => __('Blog'), 'href' => '#'],
        ['label' => __('About'), 'href' => '#'],
    ];

    $currentLocale = app()->getLocale();
    $currentLang = $currentLocale === 'id' ? __('Bahasa Indonesia') : __('English');
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale }}" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('Download Videos & Music From Your Favorite Platforms') . ' · DownloadIn')</title>
    <meta name="description" content="{{ __('Download videos and audio from TikTok, Instagram, and YouTube in high quality. Fast, simple, and easy to use.') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh flex-col bg-white font-sans text-navy-900 selection:bg-electric-100">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-electric-600 focus:px-4 focus:py-2 focus:text-white">
        {{ __('Skip to content') }}
    </a>

    {{-- NAVBAR --}}
    <header id="navbar" class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur-md transition-shadow duration-300">
        <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8" aria-label="Primary">
            <a href="{{ route('home') }}" aria-label="DownloadIn home" class="flex items-center gap-2.5">
                <span class="inline-flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-electric-500 to-electric-700 text-white shadow-glow-blue">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12"/>
                        <path d="m7 10 5 5 5-5"/>
                        <path d="M5 21h14"/>
                    </svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-navy-900">Download<span class="gradient-text">In</span></span>
            </a>

            <ul class="hidden items-center gap-8 md:flex">
                @foreach($navLinks as $link)
                    <li>
                        <a href="{{ $link['href'] }}"
                           class="text-sm font-medium text-slate-600 transition-colors duration-200 hover:text-electric-600">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="flex items-center gap-3">
                <div class="relative hidden sm:block">
                    <button id="lang-button" type="button" aria-haspopup="true" aria-expanded="false"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-electric-200 hover:text-electric-600">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/>
                            <path d="M3.5 12h17"/>
                            <path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18"/>
                        </svg>
                        <span id="lang-label">{{ $currentLang }}</span>
                        <svg class="size-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <div id="lang-menu" class="invisible absolute right-0 top-full mt-2 w-48 rounded-xl border border-slate-200 bg-white p-1.5 opacity-0 shadow-soft transition-all duration-200">
                        <a href="{{ route('language.switch', ['locale' => 'en']) }}" data-lang="English"
                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $currentLocale === 'en' ? 'bg-electric-50 font-semibold text-electric-700' : 'text-slate-700 hover:bg-electric-50 hover:text-electric-700' }}">
                            English
                            @if($currentLocale === 'en')
                                <svg class="size-4 text-electric-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </a>
                        <a href="{{ route('language.switch', ['locale' => 'id']) }}" data-lang="Bahasa Indonesia"
                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $currentLocale === 'id' ? 'bg-electric-50 font-semibold text-electric-700' : 'text-slate-700 hover:bg-electric-50 hover:text-electric-700' }}">
                            Bahasa Indonesia
                            @if($currentLocale === 'id')
                                <svg class="size-4 text-electric-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            @endif
                        </a>
                    </div>
                </div>

                <button id="menu-toggle" type="button" aria-label="{{ __('Toggle navigation') }}" aria-expanded="false"
                        class="inline-flex size-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 md:hidden">
                    <svg id="icon-open" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                    <svg id="icon-close" class="hidden size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>
        </nav>

        <div id="mobile-menu" class="hidden border-t border-slate-200 bg-white px-5 pb-5 pt-3 md:hidden">
            <ul class="space-y-1">
                @foreach($navLinks as $link)
                    <li>
                        <a href="{{ $link['href'] }}" class="block rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 hover:bg-electric-50 hover:text-electric-700">
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </header>

    <main id="main-content" class="isolate flex-1">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="bg-navy-950 text-slate-300">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8">
            <div class="grid gap-12 md:grid-cols-4 md:gap-8">
                <div class="md:col-span-2 md:max-w-sm">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <span class="inline-flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-electric-500 to-electric-700 text-white">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12"/>
                                <path d="m7 10 5 5 5-5"/>
                                <path d="M5 21h14"/>
                            </svg>
                        </span>
                        <span class="text-lg font-bold tracking-tight text-white">Download<span class="text-electric-400">In</span></span>
                    </a>
                    <p class="mt-5 text-sm leading-relaxed text-slate-400">
                        {{ __('Download supported media from TikTok, Instagram, and YouTube in MP4 or MP3 format.') }}
                    </p>
                    <a href="mailto:hello@downloadin.id" class="mt-5 inline-flex items-center gap-2 text-sm font-medium text-white transition-colors hover:text-electric-400">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="m3 7 9 6 9-6"/>
                        </svg>
                        hello@downloadin.id
                    </a>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-white">{{ __('Quick Links') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach(['About Us', 'Contact Us', 'Privacy Policy', 'Terms of Service', 'DMCA'] as $link)
                            <li><a href="#" class="text-slate-400 transition-colors hover:text-electric-400">{{ __($link) }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wider text-white">{{ __('Follow Us') }}</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach(['Facebook', 'Instagram', 'YouTube', 'X / Twitter'] as $social)
                            <li><a href="#" class="inline-flex items-center gap-2 text-slate-400 transition-colors hover:text-electric-400">{{ __($social) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-14 border-t border-white/10 pt-6 text-center text-sm text-slate-500">
                <p>{{ __('© 2026 DownloadIn. All rights reserved.') }}</p>
            </div>
        </div>
    </footer>

    <button id="scroll-top" type="button" aria-label="{{ __('Scroll to top') }}"
            class="invisible fixed bottom-6 right-6 z-40 inline-flex size-11 items-center justify-center rounded-full bg-electric-600 text-white opacity-0 shadow-glow-blue transition-all duration-300 hover:bg-electric-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric-600">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 19V5"/>
            <path d="m5 12 7-7 7 7"/>
        </svg>
    </button>

    @stack('scripts')

    <script>
        (function () {
            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const iconOpen = document.getElementById('icon-open');
            const iconClose = document.getElementById('icon-close');

            if (menuToggle && mobileMenu) {
                menuToggle.addEventListener('click', () => {
                    const expanded = menuToggle.getAttribute('aria-expanded') === 'true';
                    menuToggle.setAttribute('aria-expanded', String(!expanded));
                    mobileMenu.classList.toggle('hidden', expanded);
                    iconOpen.classList.toggle('hidden', !expanded);
                    iconClose.classList.toggle('hidden', expanded);
                });
            }

            const langButton = document.getElementById('lang-button');
            const langMenu = document.getElementById('lang-menu');
            const langLabel = document.getElementById('lang-label');

            if (langButton && langMenu) {
                langButton.addEventListener('click', () => {
                    const expanded = langButton.getAttribute('aria-expanded') === 'true';
                    langButton.setAttribute('aria-expanded', String(!expanded));
                    langMenu.classList.toggle('invisible', expanded);
                    langMenu.classList.toggle('opacity-0', expanded);
                });
                langMenu.querySelectorAll('[data-lang]').forEach((option) => {
                    option.addEventListener('click', () => {
                        langButton.setAttribute('aria-expanded', 'false');
                        langMenu.classList.add('invisible', 'opacity-0');
                    });
                });
            }

            const scrollTop = document.getElementById('scroll-top');
            if (scrollTop) {
                const onScroll = () => {
                    const show = window.scrollY > 400;
                    scrollTop.classList.toggle('invisible', !show);
                    scrollTop.classList.toggle('opacity-0', !show);
                };
                window.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
                scrollTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
            }

            const navbar = document.getElementById('navbar');
            if (navbar) {
                const onScrollNav = () => {
                    navbar.classList.toggle('shadow-soft', window.scrollY > 8);
                };
                window.addEventListener('scroll', onScrollNav, { passive: true });
                onScrollNav();
            }
        })();
    </script>
</body>
</html>
