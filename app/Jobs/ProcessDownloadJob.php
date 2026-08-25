<?php

namespace App\Jobs;

use App\Models\DownloadTask;
use App\Services\DownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDownloadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // Increased to 30 minutes

    public $tries = 3;

    public function __construct(public DownloadTask $task) {}

    public function handle(DownloadService $downloadService): void
    {
        Log::info('Job started for task: '.$this->task->uuid);
        $this->task->update(['status' => 'processing', 'progress' => 0, 'error_message' => null]);

        $downloader = $downloadService->getDownloader($this->task->platform);

        // Simulating analysis/start progress
        $this->task->update(['progress' => 10]);

        $filePath = $downloader->download($this->task);

        if (! file_exists($filePath)) {
            throw new \Exception('File was not created at expected path');
        }

        $this->task->update([
            'status' => 'finished',
            'progress' => 100,
            'output_path' => $filePath,
            'output_filename' => basename($filePath),
            'output_size_bytes' => filesize($filePath),
            'output_mime' => mime_content_type($filePath) ?: 'application/octet-stream',
            'finished_at' => now(),
            'expires_at' => now()->addHours(config('downloads.ttl_hours', 24)),
        ]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error("Download permanently failed for task {$this->task->uuid}", [
            'exception' => $exception,
        ]);

        $detail = $exception?->getPrevious()?->getMessage() ?: $exception?->getMessage();
        $message = is_string($detail) && $detail !== ''
            ? $detail
            : 'The download could not be completed. The source may be unavailable or restricted.';

        $this->task->update([
            'status' => 'failed',
            'error_message' => $message,
            'progress' => 0,
            'expires_at' => now()->addHours(config('downloads.ttl_hours', 24)),
        ]);
    }
}
