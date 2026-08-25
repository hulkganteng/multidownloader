<?php

use App\Http\Controllers\Api\DownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->post('/analyze', [DownloadController::class, 'analyze']);
Route::middleware(['throttle:5,1'])->post('/download', [DownloadController::class, 'download']);
Route::middleware(['throttle:90,1'])
    ->get('/tasks/{task}', [DownloadController::class, 'status'])
    ->name('api.tasks.show');
