<?php

use App\Http\Controllers\DownloadFileController;
use App\Http\Controllers\WebDownloadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Signed URL Route for File Download (must be accessible)
Route::get('/dl/{uuid}', DownloadFileController::class)
    ->name('download.file')
    ->middleware('throttle:60,1');

// Main Web Routes
Route::get('/', [WebDownloadController::class, 'index'])->name('home');
Route::get('/blog', [WebDownloadController::class, 'blog'])->name('blog');
Route::get('/tentang', [WebDownloadController::class, 'about'])->name('about');
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
