<?php

use App\Http\Controllers\WebDownloadController;
use App\Models\DownloadTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Signed URL Route for File Download (must be accessible)
Route::get('/dl/{uuid}', function (Request $request, $uuid) {
    if (! $request->hasValidSignature()) {
        abort(403, 'Invalid or expired signature.');
    }

    $task = DownloadTask::where('uuid', $uuid)->firstOrFail();
    $path = storage_path('app/'.config('downloads.storage_path', 'downloads').'/'.$task->uuid.'/'.$task->output_filename);

    if (! file_exists($path)) {
        abort(404, 'File not found on server.');
    }

    return response()->download($path, $task->output_filename);
})->name('download.file')->middleware('throttle:20,1');

// Main Web Routes
Route::get('/', [WebDownloadController::class, 'index'])->name('home');
Route::post('/analyze', [WebDownloadController::class, 'analyze'])->name('analyze');
Route::post('/process', [WebDownloadController::class, 'process'])->name('process');
Route::get('/task/{uuid}', [WebDownloadController::class, 'show'])->name('task.show');

// Locale switching
Route::get('/language/{locale}', function (Request $request, string $locale) {
    if (! in_array($locale, ['en', 'id'], true)) {
        abort(404);
    }

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('language.switch');
