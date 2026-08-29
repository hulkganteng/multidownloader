<?php

namespace Tests\Feature;

use App\Downloaders\TikTokDownloader;
use App\Models\DownloadTask;
use App\Services\TikTokEmbedExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TikTokFallbackTest extends TestCase
{
    public function test_embed_extractor_reads_public_tiktok_metadata(): void
    {
        Http::fake([
            'www.tiktok.com/embed/v2/*' => Http::response($this->embedHtml(), 200, [
                'Set-Cookie' => 'ttwid=test-session; Domain=.tiktok.com; Path=/',
            ]),
        ]);

        $media = app(TikTokEmbedExtractor::class)
            ->extract('https://www.tiktok.com/@creator/video/7679447169220300052');

        $this->assertSame('7679447169220300052', $media['id']);
        $this->assertSame('Public TikTok video', $media['title']);
        $this->assertSame('creator', $media['author']);
        $this->assertSame(33, $media['duration_seconds']);
        $this->assertSame('576', $media['quality']);
        $this->assertSame('https://cdn.example.com/video.mp4?token=test', $media['stream_url']);
        $this->assertSame('ttwid=test-session', $media['headers']['Cookie']);
    }

    public function test_analyze_falls_back_to_embed_when_ytdlp_fails(): void
    {
        config(['downloads.tiktok.bin_path' => 'tools/bin/missing-yt-dlp.exe']);
        Http::fake([
            'www.tiktok.com/embed/v2/*' => Http::response($this->embedHtml(), 200, [
                'Set-Cookie' => 'ttwid=test-session; Domain=.tiktok.com; Path=/',
            ]),
        ]);

        $metadata = (new TikTokDownloader)
            ->analyze('https://www.tiktok.com/@creator/video/7679447169220300052');

        $this->assertSame('tiktok', $metadata['platform']);
        $this->assertSame(['576'], $metadata['formats']['mp4']);
        $this->assertSame(['128k', '192k', '320k'], $metadata['formats']['mp3']);
    }

    public function test_mp3_uses_remote_embed_stream_without_a_local_media_file(): void
    {
        config(['downloads.tiktok.remote_streaming' => true]);
        Http::fake([
            'www.tiktok.com/embed/v2/*' => Http::response($this->embedHtml(), 200, [
                'Set-Cookie' => 'ttwid=test-session; Domain=.tiktok.com; Path=/',
            ]),
        ]);
        $task = new DownloadTask([
            'platform' => 'tiktok',
            'source_url' => 'https://www.tiktok.com/@creator/video/7679447169220300052',
            'format' => 'mp3',
            'bitrate' => '192k',
            'title' => 'Public TikTok video',
        ]);

        $result = (new TikTokDownloader)->resolveRemoteStreams($task);

        $this->assertNotNull($result);
        $this->assertSame('audio/mpeg', $result['mime']);
        $this->assertStringEndsWith('.mp3', $result['filename']);
        $this->assertCount(1, $result['streams']);
        $this->assertFileDoesNotExist(DownloadTask::storageDir($task->uuid).'/public_tiktok_video.mp3');
    }

    public function test_tiktok_delivery_failure_returns_http_error_before_streaming_and_cleans_temp_files(): void
    {
        Http::fake([
            'www.tiktok.com/embed/v2/*' => Http::response('unavailable', 503),
        ]);
        $task = DownloadTask::create([
            'platform' => 'tiktok',
            'source_url' => 'https://www.tiktok.com/@creator/video/7679447169220300052',
            'format' => 'mp4',
            'status' => 'finished',
            'delivery_method' => 'stream',
            'remote_streams' => [[
                'url' => 'https://expired.example/video.mp4',
                'headers' => [],
                'vcodec' => 'unknown',
                'acodec' => 'unknown',
            ]],
            'output_filename' => 'tiktok.mp4',
            'output_mime' => 'video/mp4',
            'expires_at' => now()->addHour(),
        ]);
        $url = URL::temporarySignedRoute('download.file', now()->addHour(), ['uuid' => $task->uuid]);

        $this->get($url)->assertStatus(502);

        $this->assertSame([], File::glob(DownloadTask::storageDir($task->uuid).'/.delivery-*'));
    }

    private function embedHtml(): string
    {
        $state = [
            'source' => [
                'data' => [
                    '/embed/v2/7679447169220300052' => [
                        'videoData' => [
                            'itemInfos' => [
                                'id' => '7679447169220300052',
                                'text' => 'Public TikTok video',
                                'coversOrigin' => ['https://cdn.example.com/cover.jpg'],
                                'video' => [
                                    'urls' => ['https://cdn.example.com/video.mp4?token=test'],
                                    'videoMeta' => [
                                        'width' => 576,
                                        'height' => 768,
                                        'duration' => 33,
                                    ],
                                ],
                            ],
                            'authorInfos' => ['uniqueId' => 'creator'],
                        ],
                    ],
                ],
            ],
        ];

        return '<html><script id="__FRONTITY_CONNECT_STATE__" type="application/json">'
            .json_encode($state, JSON_THROW_ON_ERROR)
            .'</script></html>';
    }
}
