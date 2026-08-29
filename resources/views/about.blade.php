@extends('layouts.app')

@section('title', __('About DownloadIn') . ' · DownloadIn')
@section('meta_description', __('Learn why DownloadIn was built, how temporary downloads are handled, and the principles behind the service.'))

@section('content')
@php($isId = app()->getLocale() === 'id')

<section class="soft-bg border-b border-slate-100 py-20 sm:py-28">
    <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
        <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ $isId ? 'Tentang DownloadIn' : 'About DownloadIn' }}</span>
        <h1 class="mt-4 text-balance text-4xl font-extrabold tracking-tight text-navy-900 sm:text-5xl">
            {{ $isId ? 'Media publik Anda, siap digunakan saat dibutuhkan' : 'Your public media, ready when you need it' }}
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-pretty text-base leading-relaxed text-slate-600 sm:text-lg">
            {{ $isId ? 'DownloadIn membantu Anda menyimpan media publik yang didukung ke perangkat sendiri tanpa akun tambahan dan tanpa menyimpan salinan permanen di server.' : 'DownloadIn helps you save supported public media to your own device without another account and without keeping a permanent server copy.' }}
        </p>
    </div>
</section>

<section class="py-20 sm:py-24">
    <div class="mx-auto grid max-w-6xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:items-center">
        <div>
            <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ $isId ? 'Mengapa dibuat' : 'Why it exists' }}</span>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ $isId ? 'Satu alur untuk beberapa platform' : 'One flow across several platforms' }}</h2>
            <div class="mt-5 space-y-4 leading-relaxed text-slate-600">
                <p>{{ $isId ? 'Menyimpan video publik sering berarti berpindah alat, menghadapi tombol yang membingungkan, atau menerima file yang tidak sesuai kebutuhan.' : 'Saving public videos often means switching tools, dealing with confusing buttons, or receiving a file that does not match what you need.' }}</p>
                <p>{{ $isId ? 'DownloadIn dibuat untuk memusatkan proses itu: tempel tautan, lihat pilihan yang benar-benar tersedia, pilih format, lalu simpan hasilnya.' : 'DownloadIn brings that process into one place: paste a link, see the options that are actually available, choose a format, and save the result.' }}</p>
            </div>
        </div>
        <div class="rounded-3xl border border-electric-100 bg-electric-50/60 p-7 sm:p-9">
            <h3 class="text-xl font-semibold text-navy-900">{{ $isId ? 'Apa artinya bagi Anda' : 'What this means for you' }}</h3>
            <ul class="mt-6 space-y-5">
                @foreach(($isId ? [
                    'Tidak perlu membuat akun untuk memeriksa tautan publik.',
                    'Pilihan format mengikuti media yang tersedia di sumber.',
                    'File sementara dihapus setelah dikirim dan cleanup satu jam menjadi cadangan.',
                ] : [
                    'No account is required to check a public link.',
                    'Format choices follow the media available at the source.',
                    'Temporary files are deleted after delivery, with a one-hour cleanup as backup.',
                ]) as $item)
                    <li class="flex gap-3 text-sm leading-relaxed text-slate-700">
                        <svg class="mt-0.5 size-5 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        {{ $item }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

<section class="bg-slate-50/70 py-20 sm:py-24">
    <div class="mx-auto max-w-6xl px-5 sm:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wider text-electric-600">{{ $isId ? 'Prinsip layanan' : 'Service principles' }}</span>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ $isId ? 'Batas yang jelas membangun kepercayaan' : 'Clear boundaries build trust' }}</h2>
        </div>
        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach(($isId ? [
                ['Akses publik', 'Kami hanya memproses media yang dapat diakses secara publik oleh server. Konten privat atau yang memerlukan login tidak dapat diunduh.'],
                ['Penyimpanan sementara', 'Media hanya berada di server selama diperlukan untuk memproses dan mengirim unduhan, lalu dihapus otomatis.'],
                ['Hak pemilik konten', 'Gunakan layanan untuk konten milik Anda, berizin, atau diizinkan untuk disimpan sesuai hukum dan aturan platform.'],
            ] : [
                ['Public access', 'We only process media that the server can access publicly. Private or login-protected content cannot be downloaded.'],
                ['Temporary storage', 'Media stays on the server only while needed for processing and delivery, then it is removed automatically.'],
                ['Creator rights', 'Use the service for content you own, have permission to save, or may use under applicable law and platform rules.'],
            ]) as [$title, $description])
                <div class="card-surface p-7">
                    <h3 class="text-lg font-semibold text-navy-900">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-500">{{ $description }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-20 text-center sm:py-24">
    <div class="mx-auto max-w-3xl px-5 sm:px-8">
        <h2 class="text-3xl font-bold text-navy-900">{{ $isId ? 'Simpan media yang memang boleh Anda gunakan' : 'Save media you are allowed to use' }}</h2>
        <p class="mt-4 text-slate-500">{{ $isId ? 'Mulai dengan tautan publik dari platform yang didukung.' : 'Start with a public link from a supported platform.' }}</p>
        <a href="{{ route('home') }}#downloader" class="mt-7 inline-flex min-h-14 items-center justify-center rounded-2xl bg-gradient-to-r from-electric-600 to-electric-500 px-8 font-semibold text-white shadow-glow-blue transition-colors hover:from-electric-700 hover:to-electric-600">
            {{ $isId ? 'Siapkan unduhan' : 'Prepare a download' }}
        </a>
    </div>
</section>
@endsection
