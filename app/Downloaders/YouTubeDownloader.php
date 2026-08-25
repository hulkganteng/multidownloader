<?php

namespace App\Downloaders;

class YouTubeDownloader extends YtDlpDownloader
{
    protected function platform(): string
    {
        return 'youtube';
    }
}
