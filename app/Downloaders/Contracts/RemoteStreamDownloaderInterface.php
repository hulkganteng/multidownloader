<?php

namespace App\Downloaders\Contracts;

use App\Models\DownloadTask;

interface RemoteStreamDownloaderInterface
{
    /**
     * Resolve media inputs that can be remuxed directly to the user's response.
     *
     * @return array{streams: list<array{url: string, headers: array<string, string>, vcodec: string, acodec: string}>, filename: string, mime: string, size_bytes: int|null}|null
     */
    public function resolveRemoteStreams(DownloadTask $task): ?array;
}
