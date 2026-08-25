<?php

namespace App\Services;

use App\Downloaders\Contracts\DownloaderInterface;
use App\Downloaders\DirectMediaDownloader;
use App\Downloaders\TikTokDownloader;
use App\Downloaders\YouTubeDownloader;
use InvalidArgumentException;

class DownloadService
{
    public function getDownloader(string $platform): DownloaderInterface
    {
        return match ($platform) {
            'youtube' => new YouTubeDownloader,
            'tiktok' => new TikTokDownloader,
            'direct' => new DirectMediaDownloader,
            default => throw new InvalidArgumentException("Unsupported platform: $platform"),
        };
    }
}
