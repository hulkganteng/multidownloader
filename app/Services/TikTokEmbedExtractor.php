<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TikTokEmbedExtractor
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36';

    /**
     * @return array{id: string, title: string, author: string, thumbnail_url: ?string, duration_seconds: int, quality: string, stream_url: string, headers: array<string, string>}
     */
    public function extract(string $url): array
    {
        $id = $this->resolveVideoId($url);
        $embedUrl = "https://www.tiktok.com/embed/v2/{$id}";
        $response = Http::timeout(30)
            ->retry(2, 300)
            ->withUserAgent(self::USER_AGENT)
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
            ->get($embedUrl);

        if (! $response->successful()) {
            throw new RuntimeException("TikTok embed returned HTTP {$response->status()}.");
        }

        if (! preg_match('~<script id="__FRONTITY_CONNECT_STATE__" type="application/json">(.*?)</script>~s', $response->body(), $matches)) {
            throw new RuntimeException('TikTok embed metadata was not found.');
        }

        try {
            $state = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('TikTok embed metadata was invalid.', previous: $exception);
        }

        $route = "/embed/v2/{$id}";
        $entry = $state['source']['data'][$route] ?? $state['source']['data'][$route.'/'] ?? null;
        $videoData = is_array($entry) ? ($entry['videoData'] ?? null) : null;
        $info = is_array($videoData) ? ($videoData['itemInfos'] ?? null) : null;
        $video = is_array($info) ? ($info['video'] ?? null) : null;
        $streamUrl = is_array($video) ? collect($video['urls'] ?? [])->first(fn ($candidate) => $this->isHttpUrl($candidate)) : null;

        if (! is_array($info) || ! is_string($streamUrl)) {
            throw new RuntimeException('TikTok did not provide a playable public video.');
        }

        $meta = is_array($video['videoMeta'] ?? null) ? $video['videoMeta'] : [];
        $width = max(0, (int) ($meta['width'] ?? 0));
        $height = max(0, (int) ($meta['height'] ?? 0));
        $quality = min(array_filter([$width, $height]) ?: [0]);
        $author = (string) ($videoData['authorInfos']['uniqueId'] ?? 'tiktok');
        $title = trim((string) ($info['text'] ?? ''));
        $thumbnail = collect($info['coversOrigin'] ?? $info['covers'] ?? [])->first(fn ($candidate) => $this->isHttpUrl($candidate));
        $cookieHeader = collect($response->cookies()->toArray())
            ->filter(fn (array $cookie) => isset($cookie['Name'], $cookie['Value']))
            ->map(fn (array $cookie) => $cookie['Name'].'='.$cookie['Value'])
            ->implode('; ');
        $headers = [
            'User-Agent' => self::USER_AGENT,
            'Referer' => $embedUrl,
        ];
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }

        return [
            'id' => $id,
            'title' => Str::limit($title !== '' ? $title : "TikTok by {$author}", 255, ''),
            'author' => $author,
            'thumbnail_url' => is_string($thumbnail) ? $thumbnail : null,
            'duration_seconds' => max(0, (int) ($meta['duration'] ?? 0)),
            'quality' => (string) ($quality > 0 ? $quality : 'default'),
            'stream_url' => $streamUrl,
            'headers' => $headers,
        ];
    }

    private function resolveVideoId(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'tiktok.com' && ! str_ends_with($host, '.tiktok.com')) {
            throw new RuntimeException('The provided URL is not a TikTok link.');
        }

        if (preg_match('~/(?:video|v)/(\d{10,25})(?:[/?#]|$)~', $url, $matches)) {
            return $matches[1];
        }

        $response = Http::timeout(20)
            ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
            ->withUserAgent(self::USER_AGENT)
            ->get($url);
        $effectiveUrl = (string) $response->effectiveUri();

        if (preg_match('~/(?:video|v)/(\d{10,25})(?:[/?#]|$)~', $effectiveUrl, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException('Could not resolve the TikTok video ID from this link.');
    }

    private function isHttpUrl(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
