<?php

namespace App\Downloaders;

use App\Models\DownloadTask;
use App\Services\TikTokEmbedExtractor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TikTokDownloader extends YtDlpDownloader
{
    public function analyze(string $url): array
    {
        try {
            return parent::analyze($url);
        } catch (Throwable $primaryException) {
            try {
                $media = $this->embedExtractor()->extract($url);

                return [
                    'platform' => 'tiktok',
                    'title' => $media['title'],
                    'thumbnail_url' => $media['thumbnail_url'],
                    'duration_seconds' => $media['duration_seconds'],
                    'size_bytes' => null,
                    'formats' => [
                        'mp4' => [$media['quality']],
                        'mp3' => ['128k', '192k', '320k'],
                    ],
                ];
            } catch (Throwable $fallbackException) {
                Log::warning('TikTok embed analysis fallback failed', [
                    'url' => $url,
                    'primary_error' => $primaryException->getMessage(),
                    'fallback_error' => $fallbackException->getMessage(),
                ]);

                throw new RuntimeException('Could not read this TikTok video. It may be private, removed, or restricted.', previous: $fallbackException);
            }
        }
    }

    public function resolveRemoteStreams(DownloadTask $task): ?array
    {
        if (! in_array($task->format, ['mp4', 'mp3', 'original'], true)
            || ! config('downloads.tiktok.remote_streaming', false)) {
            return null;
        }

        if ($task->format === 'mp4') {
            try {
                $primary = parent::resolveRemoteStreams($task);
                if ($primary !== null) {
                    return $primary;
                }
            } catch (Throwable $exception) {
                Log::info('TikTok yt-dlp stream resolution failed; using embed fallback', [
                    'task_uuid' => $task->uuid,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $media = $this->embedExtractor()->extract($task->source_url);
        $safeTitle = Str::slug($task->title ?: $media['title'], '_') ?: 'tiktok';
        $isAudio = $task->format === 'mp3';

        return [
            'streams' => [[
                'url' => $media['stream_url'],
                'headers' => $media['headers'],
                'vcodec' => 'unknown',
                'acodec' => 'unknown',
            ]],
            'filename' => $safeTitle.($isAudio ? '.mp3' : '.mp4'),
            'mime' => $isAudio ? 'audio/mpeg' : 'video/mp4',
            'size_bytes' => null,
        ];
    }

    protected function platform(): string
    {
        return 'tiktok';
    }

    private function embedExtractor(): TikTokEmbedExtractor
    {
        return app(TikTokEmbedExtractor::class);
    }
}
