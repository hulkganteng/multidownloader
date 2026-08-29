<?php

namespace Tests\Feature;

use App\Jobs\ProcessDownloadJob;
use App\Models\DownloadTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_download_ttl_config_is_an_integer(): void
    {
        $this->assertIsInt(config('downloads.ttl_hours'));
        $this->assertSame(1, config('downloads.ttl_hours'));
    }

    public function test_api_routes_are_registered(): void
    {
        $this->postJson('/api/analyze', [])->assertStatus(422);
        $this->getJson('/api/tasks/not-a-uuid')->assertStatus(404);
    }

    public function test_home_blog_and_about_pages_are_available_in_indonesian(): void
    {
        $this->withSession(['locale' => 'id'])
            ->get('/')
            ->assertOk()
            ->assertSee('Simpan media yang Anda butuhkan');

        $this->withSession(['locale' => 'id'])
            ->get('/blog')
            ->assertOk()
            ->assertSee('Cara mengunduh video TikTok publik');

        $this->withSession(['locale' => 'id'])
            ->get('/tentang')
            ->assertOk()
            ->assertSee('Tentang DownloadIn');
    }

    public function test_download_api_accepts_missing_optional_fields_and_queues_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/download', [
            'url' => 'https://example.com/video.mp4',
            'format' => 'original',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'queued']);
        $uuid = $response->json('uuid');
        $task = DownloadTask::find($uuid);
        $this->assertNotNull($task);
        $this->assertNull($task->quality);
        $this->assertNull($task->bitrate);
        Queue::assertPushed(ProcessDownloadJob::class, fn ($job) => $job->task->uuid === $task->uuid);
    }

    public function test_download_api_accepts_tiktok_or_instagram_mp3_format(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/download', [
            'url' => 'https://www.tiktok.com/@test/video/1234567890',
            'format' => 'mp3',
            'bitrate' => '320k',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'queued']);
        $uuid = $response->json('uuid');
        $task = DownloadTask::find($uuid);
        $this->assertNotNull($task);
        $this->assertSame('tiktok', $task->platform);
        $this->assertSame('mp3', $task->format);
        $this->assertSame('320k', $task->bitrate);
    }

    public function test_download_api_rejects_non_http_urls_without_creating_task(): void
    {
        Queue::fake();

        $this->postJson('/api/download', [
            'url' => 'ftp://example.com/video.mp4',
            'format' => 'mp4',
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_status_api_returns_a_temporary_signed_download_url(): void
    {
        $task = DownloadTask::create([
            'source_url' => 'https://example.com/video.mp4',
            'platform' => 'direct',
            'format' => 'original',
            'title' => 'Video',
            'status' => 'finished',
            'progress' => 100,
            'output_filename' => 'video.mp4',
            'expires_at' => now()->addHour(),
        ]);

        $this->getJson('/api/tasks/'.$task->uuid)
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonStructure(['download_url']);
    }

    public function test_remote_stream_download_does_not_create_a_local_media_file(): void
    {
        $task = DownloadTask::create([
            'source_url' => 'https://www.instagram.com/reel/example/',
            'platform' => 'instagram',
            'format' => 'mp4',
            'title' => 'Remote video',
            'status' => 'finished',
            'progress' => 100,
            'delivery_method' => 'stream',
            'remote_streams' => [[
                'url' => 'https://cdn.example.com/video.mp4?signature=test',
                'headers' => ['Referer' => 'https://www.instagram.com/'],
                'vcodec' => 'h264',
                'acodec' => 'aac',
            ]],
            'output_filename' => 'remote_video.mp4',
            'output_mime' => 'video/mp4',
            'expires_at' => now()->addHour(),
        ]);

        $url = URL::temporarySignedRoute('download.file', now()->addHour(), ['uuid' => $task->uuid]);

        $this->get($url)->assertOk()->assertDownload('remote_video.mp4');

        $this->assertSame([], File::glob(DownloadTask::storageDir($task->uuid).'/*.mp4'));
        $this->assertSame('stream', DownloadTask::findOrFail($task->uuid)->delivery_method);
    }

    public function test_temporary_file_is_deleted_after_it_is_sent(): void
    {
        $task = DownloadTask::create([
            'source_url' => 'https://example.com/video.mp4',
            'platform' => 'direct',
            'format' => 'original',
            'title' => 'Temporary video',
            'status' => 'finished',
            'progress' => 100,
            'output_filename' => 'temporary_video.mp4',
            'output_mime' => 'video/mp4',
            'expires_at' => now()->addHour(),
        ]);
        $path = DownloadTask::storageDir($task->uuid).'/temporary_video.mp4';
        File::put($path, 'temporary video');
        $url = URL::temporarySignedRoute('download.file', now()->addHour(), ['uuid' => $task->uuid]);

        $response = $this->get($url);
        $response->assertOk()->assertDownload('temporary_video.mp4');

        ob_start();
        try {
            $response->baseResponse->sendContent();
        } finally {
            ob_end_clean();
        }

        $this->assertFileDoesNotExist($path);
    }

    public function test_status_page_builds_the_task_api_url_behind_an_https_tunnel(): void
    {
        $task = DownloadTask::create([
            'source_url' => 'https://example.com/video.mp4',
            'platform' => 'direct',
            'format' => 'original',
            'title' => 'Video',
            'status' => 'queued',
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'Host' => 'demo-tunnel.example',
                'X-Forwarded-Host' => 'demo-tunnel.example',
                'X-Forwarded-Proto' => 'https',
            ])
            ->get('/task/'.$task->uuid)
            ->assertOk()
            ->assertSee('https:\/\/demo-tunnel.example\/api\/tasks\/'.$task->uuid, false);
    }

    public function test_invalid_web_process_request_returns_home_with_errors(): void
    {
        $this->from('/')->post('/process', [
            'url' => 'https://example.com/video.mp4',
            'format' => 'executable',
        ])->assertRedirect('/')->assertSessionHasErrors('format');
    }

    public function test_cleanup_removes_only_expired_task_directory_and_record(): void
    {
        $uuid = (string) Str::uuid();
        $directory = storage_path('app/downloads/'.$uuid);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/media.mp4', 'test');

        DownloadTask::create([
            'uuid' => $uuid,
            'source_url' => 'https://example.com/video.mp4',
            'platform' => 'direct',
            'format' => 'original',
            'status' => 'finished',
            'output_path' => $directory.'/media.mp4',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('downloads:cleanup')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($directory);
        $this->assertNull(DownloadTask::find($uuid));
    }

    public function test_cleanup_removes_a_stalled_processing_task_but_keeps_an_active_one(): void
    {
        config(['downloads.process_timeout' => 1800]);
        $stalled = DownloadTask::create([
            'platform' => 'instagram',
            'format' => 'mp4',
            'status' => 'processing',
        ]);
        $stalled->updated_at = now()->subMinutes(41);
        File::put(DownloadTask::metaPath($stalled->uuid), $stalled->toJson(JSON_PRETTY_PRINT));

        $active = DownloadTask::create([
            'platform' => 'instagram',
            'format' => 'mp4',
            'status' => 'processing',
        ]);

        $this->artisan('downloads:cleanup')->assertSuccessful();

        $this->assertNull(DownloadTask::find($stalled->uuid));
        $this->assertNotNull(DownloadTask::find($active->uuid));
    }
}
