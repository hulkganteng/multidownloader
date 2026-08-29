@extends('layouts.app')

@section('title', __('Download Guides and Tips') . ' · DownloadIn')
@section('meta_description', __('Practical guides for downloading supported public media, choosing MP4 or MP3, and fixing links that cannot be processed.'))

@section('content')
@php
    $isId = app()->getLocale() === 'id';
    $articles = $isId ? [
        [
            'id' => 'tiktok-publik',
            'category' => 'TikTok',
            'title' => 'Cara mengunduh video TikTok publik',
            'intro' => 'Tautan yang tepat membuat proses lebih lancar. Gunakan alamat video asli dari menu Bagikan, bukan tautan profil atau hasil pencarian.',
            'steps' => [
                'Buka video TikTok yang dapat ditonton tanpa masuk ke akun.',
                'Pilih Bagikan, lalu salin tautan videonya.',
                'Tempel tautan ke DownloadIn dan pilih MP4.',
                'Pilih kualitas yang tersedia, lalu siapkan unduhan.',
            ],
            'note' => 'Video privat, video yang sudah dihapus, atau konten yang dibatasi wilayah tidak dapat diproses.',
        ],
        [
            'id' => 'mp4-atau-mp3',
            'category' => 'Format',
            'title' => 'MP4 atau MP3: pilih format yang tepat',
            'intro' => 'Pilihan format bergantung pada hasil yang ingin Anda simpan. Tidak perlu mengunduh video penuh jika yang dibutuhkan hanya audionya.',
            'steps' => [
                'Pilih MP4 untuk menyimpan gambar dan suara dalam satu file.',
                'Pilih MP3 untuk musik, rekaman suara, atau bahan dengar pribadi.',
                'Gunakan bitrate lebih tinggi jika sumbernya mendukung dan ruang penyimpanan mencukupi.',
                'Jika pilihan kualitas tidak muncul, gunakan kualitas terbaik yang tersedia.',
            ],
            'note' => 'Kualitas hasil tidak dapat melebihi kualitas media asli yang tersedia di platform sumber.',
        ],
        [
            'id' => 'tautan-gagal',
            'category' => 'Pemecahan Masalah',
            'title' => 'Mengapa tautan media tidak dapat diproses?',
            'intro' => 'Pesan gagal tidak selalu berarti situs sedang bermasalah. Sumber media dapat menolak akses atau mengubah alamat file sewaktu-waktu.',
            'steps' => [
                'Pastikan tautan dapat dibuka di jendela privat tanpa login.',
                'Periksa apakah postingan masih tersedia dan bukan milik akun privat.',
                'Salin ulang tautan dari aplikasi atau situs sumber.',
                'Coba lagi beberapa saat kemudian jika platform sedang membatasi permintaan.',
            ],
            'note' => 'DownloadIn hanya memproses tautan publik yang dapat diakses oleh server.',
        ],
    ] : [
        [
            'id' => 'public-tiktok',
            'category' => 'TikTok',
            'title' => 'How to download a public TikTok video',
            'intro' => 'The right link keeps the process moving. Use the original video address from the Share menu, not a profile or search result.',
            'steps' => [
                'Open a TikTok video that can be watched without signing in.',
                'Choose Share, then copy the video link.',
                'Paste the link into DownloadIn and choose MP4.',
                'Select an available quality, then prepare the download.',
            ],
            'note' => 'Private, removed, or region-restricted videos cannot be processed.',
        ],
        [
            'id' => 'mp4-or-mp3',
            'category' => 'Formats',
            'title' => 'MP4 or MP3: choose the right format',
            'intro' => 'Your format depends on what you want to keep. There is no need to download a full video when you only need its audio.',
            'steps' => [
                'Choose MP4 to keep the picture and sound in one file.',
                'Choose MP3 for music, spoken audio, or personal listening.',
                'Use a higher bitrate when the source supports it and storage space allows.',
                'If quality options are unavailable, use the best available source.',
            ],
            'note' => 'The output cannot exceed the quality provided by the original platform.',
        ],
        [
            'id' => 'failed-link',
            'category' => 'Troubleshooting',
            'title' => 'Why a media link cannot be processed',
            'intro' => 'A failed message does not always mean the site is down. The media source may block access or change its file address at any time.',
            'steps' => [
                'Confirm the link opens in a private window without signing in.',
                'Check that the post still exists and does not belong to a private account.',
                'Copy the link again from the source app or website.',
                'Try again later if the platform is temporarily limiting requests.',
            ],
            'note' => 'DownloadIn can only process public links that its server can access.',
        ],
    ];
@endphp

<section class="soft-bg border-b border-slate-100 py-20 sm:py-24">
    <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
        <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ $isId ? 'Panduan DownloadIn' : 'DownloadIn Guides' }}</span>
        <h1 class="mt-4 text-balance text-4xl font-extrabold tracking-tight text-navy-900 sm:text-5xl">
            {{ $isId ? 'Simpan media dengan langkah yang tepat' : 'Save media with the right steps' }}
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-pretty text-base leading-relaxed text-slate-500 sm:text-lg">
            {{ $isId ? 'Pelajari jenis tautan yang dapat diproses, perbedaan MP4 dan MP3, serta cara memeriksa sumber saat unduhan gagal.' : 'Learn which links can be processed, how MP4 differs from MP3, and what to check when a download fails.' }}
        </p>
    </div>
</section>

<section class="py-20 sm:py-24">
    <div class="mx-auto max-w-5xl space-y-10 px-5 sm:px-8">
        @foreach($articles as $article)
            <article id="{{ $article['id'] }}" class="scroll-mt-24 rounded-3xl border border-slate-200 bg-white p-6 shadow-card sm:p-10">
                <span class="text-xs font-semibold uppercase tracking-wider text-electric-600">{{ $article['category'] }}</span>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-navy-900 sm:text-3xl">{{ $article['title'] }}</h2>
                <p class="mt-4 leading-relaxed text-slate-600">{{ $article['intro'] }}</p>

                <ol class="mt-7 space-y-4">
                    @foreach($article['steps'] as $index => $step)
                        <li class="flex gap-4">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-electric-50 text-sm font-bold text-electric-700">{{ $index + 1 }}</span>
                            <p class="pt-1 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-7 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-relaxed text-amber-900">
                    <strong>{{ $isId ? 'Perlu diketahui:' : 'Keep in mind:' }}</strong> {{ $article['note'] }}
                </div>
            </article>
        @endforeach
    </div>
</section>

<section class="bg-navy-950 py-16 text-center text-white">
    <div class="mx-auto max-w-3xl px-5 sm:px-8">
        <h2 class="text-3xl font-bold">{{ $isId ? 'Sudah punya tautannya?' : 'Have your link ready?' }}</h2>
        <p class="mt-3 text-slate-400">{{ $isId ? 'Tempel tautan publik dan lihat format yang tersedia.' : 'Paste a public link and see which formats are available.' }}</p>
        <a href="{{ route('home') }}#downloader" class="mt-7 inline-flex min-h-12 items-center justify-center rounded-xl bg-electric-500 px-7 font-semibold text-white transition-colors hover:bg-electric-400">
            {{ $isId ? 'Periksa tautan saya' : 'Check my link' }}
        </a>
    </div>
</section>
@endsection
