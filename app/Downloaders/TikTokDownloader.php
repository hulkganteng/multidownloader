<?php

namespace App\Downloaders;

class TikTokDownloader extends YtDlpDownloader
{
    protected function platform(): string
    {
        return 'tiktok';
    }
}
