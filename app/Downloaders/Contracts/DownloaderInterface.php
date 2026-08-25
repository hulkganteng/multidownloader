<?php

namespace App\Downloaders\Contracts;

use App\Models\DownloadTask;

interface DownloaderInterface
{
    /**
     * Analyze the URL and return metadata.
     *
     * @return array {
     *               title: string,
     *               thumbnail_url: string,
     *               duration_seconds: int,
     *               platform: string,
     *               formats: array
     *               }
     */
    public function analyze(string $url): array;

    /**
     * Perform the download logic.
     * Should throw exception on failure.
     * Returns path to the downloaded file.
     */
    public function download(DownloadTask $task): string;
}
