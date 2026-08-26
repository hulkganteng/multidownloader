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

        if ($this->isInstagram($host)) {
            return 'instagram';
        }

        if ($this->isFacebook($host)) {
            return 'facebook';
        }

        if ($this->isTwitter($host)) {
            return 'twitter';
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

    private function isInstagram(string $host): bool
    {
        return $host === 'instagram.com'
            || str_ends_with($host, '.instagram.com')
            || $host === 'instagr.am'
            || str_ends_with($host, '.instagr.am');
    }

    private function isFacebook(string $host): bool
    {
        return $host === 'facebook.com'
            || str_ends_with($host, '.facebook.com')
            || $host === 'fb.watch'
            || str_ends_with($host, '.fb.watch')
            || $host === 'fb.com'
            || str_ends_with($host, '.fb.com');
    }

    private function isTwitter(string $host): bool
    {
        return $host === 'twitter.com'
            || str_ends_with($host, '.twitter.com')
            || $host === 'x.com'
            || str_ends_with($host, '.x.com');
    }
}
