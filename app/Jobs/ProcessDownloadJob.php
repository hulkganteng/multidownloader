<?php

namespace App\Jobs;

use App\Downloaders\Contracts\RemoteStreamDownloaderInterface;
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

        if ($downloader instanceof RemoteStreamDownloaderInterface) {
            try {
                $remote = $downloader->resolveRemoteStreams($this->task);

                if ($remote !== null) {
                    $this->task->update([
                        'status' => 'finished',
                        'progress' => 100,
                        'delivery_method' => 'stream',
                        'remote_streams' => $remote['streams'],
                        'output_path' => null,
                        'output_filename' => $remote['filename'],
                        'output_size_bytes' => $remote['size_bytes'],
                        'output_mime' => $remote['mime'],
                        'finished_at' => now(),
                        'expires_at' => now()->addHours((int) config('downloads.ttl_hours', 1)),
                    ]);

                    return;
                }
            } catch (Throwable $exception) {
                Log::warning('Remote streaming unavailable; falling back to a temporary file', [
                    'task_uuid' => $this->task->uuid,
                    'platform' => $this->task->platform,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

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
            'delivery_method' => 'file',
            'remote_streams' => [],
            'finished_at' => now(),
            'expires_at' => now()->addHours((int) config('downloads.ttl_hours', 1)),
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

        $detail = $exception?->getMessage();
        $message = is_string($detail) && $detail !== ''
            ? $detail
            : 'The download could not be completed. The source may be unavailable or restricted.';

        $this->task->update([
            'status' => 'failed',
            'error_message' => $message,
            'progress' => 0,
            'expires_at' => now()->addHours((int) config('downloads.ttl_hours', 1)),
        ]);
    }
}
