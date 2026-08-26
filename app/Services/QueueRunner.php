<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class QueueRunner
{
    /**
     * Trigger a background worker to instantly pick up queued download jobs
     * without blocking the HTTP response.
     */
    public static function trigger(): void
    {
        if (config('queue.default') !== 'database') {
            return;
        }

        try {
            $phpBinary = PHP_BINARY;
            if (! is_string($phpBinary) || $phpBinary === '' || ! file_exists($phpBinary)) {
                $phpBinary = 'php';
            }

            $process = new Process([
                $phpBinary,
                base_path('artisan'),
                'queue:work',
                '--once',
                '--stop-when-empty',
                '--tries=3',
                '--timeout=1800',
            ], base_path());

            // Start asynchronous process in background
            $process->start();
        } catch (\Throwable $e) {
            // Background worker fallback; silent fail so request completes normally
        }
    }
}
