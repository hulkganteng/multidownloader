<?php

namespace App\Downloaders;

class FacebookDownloader extends YtDlpDownloader
{
    protected function platform(): string
    {
        return 'facebook';
    }
}
