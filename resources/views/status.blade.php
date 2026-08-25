@extends('layouts.app')

@section('title', __('Preparing your download') . ' · DownloadIn')

@section('content')
<section class="soft-bg py-12 sm:py-20">
    <div class="mx-auto max-w-2xl px-5 sm:px-8">
        <div class="rounded-3xl border border-slate-200/80 bg-white p-7 shadow-soft-lg sm:p-10">
            @include('partials.status-progress')
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    window.startDownloadPolling(@json($task->uuid), {
        title: @json($task->title),
        apiUrl: @json(route('api.tasks.show', ['task' => $task])),
    });
</script>
@endpush
