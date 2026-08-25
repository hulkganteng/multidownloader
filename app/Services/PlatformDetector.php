<?php

namespace App\Services;

class PlatformDetector
{
    public function detect(string $url): ?string
    {
        $parsed = parse_url($url);
        $host = strtolower($parsed['host'] ?? '');

        if ($this->isYouTube($host)) {
            return 'youtube';
        }

        if ($this->isTikTok($host)) {
            return 'tiktok';
        }

        // Basic check for direct HTTP/HTTPS
        if (filter_var($url, FILTER_VALIDATE_URL) && in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return 'direct';
        }

        return null;
    }

    private function isYouTube(string $host): bool
    {
        return $host === 'youtu.be'
            || $host === 'youtube.com'
            || str_ends_with($host, '.youtube.com')
            || $host === 'youtube-nocookie.com'
            || str_ends_with($host, '.youtube-nocookie.com');
    }

    private function isTikTok(string $host): bool
    {
        return $host === 'tiktok.com' || str_ends_with($host, '.tiktok.com');
    }
}
