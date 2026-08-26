<?php

namespace App\Downloaders;

class TwitterDownloader extends YtDlpDownloader
{
    protected function platform(): string
    {
        return 'twitter';
    }
}
