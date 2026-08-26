<?php

namespace App\Downloaders;

class InstagramDownloader extends YtDlpDownloader
{
    protected function platform(): string
    {
        return 'instagram';
    }
}
