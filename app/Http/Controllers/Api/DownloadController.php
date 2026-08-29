<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDownloadJob;
use App\Models\DownloadTask;
use App\Services\DownloadService;
use App\Services\PlatformDetector;
use App\Services\QueueRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class DownloadController extends Controller
{
    public function analyze(Request $request, PlatformDetector $detector, DownloadService $service)
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $platform = $detector->detect($request->url);

        if (! $platform) {
            return response()->json(['message' => 'Platform not supported or invalid URL.'], 422);
        }

        try {
            $downloader = $service->getDownloader($platform);
            $metadata = $downloader->analyze($request->url);

            return response()->json($metadata);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => $this->friendlyErrorMessage($e)], 422);
        }
    }

    public function download(Request $request, PlatformDetector $detector)
    {
        $data = $request->validate([
            'url' => 'required|url',
            'format' => ['required', Rule::in(['mp3', 'mp4', 'original'])],
            'quality' => ['nullable', 'string', 'regex:/^(default|\d{2,4})$/'],
            'bitrate' => ['nullable', Rule::in(['128k', '192k', '256k', '320k'])],
            'title' => 'nullable|string|max:255',
            'thumbnail_url' => 'nullable|url|max:2048',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        $platform = $detector->detect($data['url']);

        if (! $platform) {
            return response()->json(['message' => 'Only public HTTP or HTTPS media URLs are supported.'], 422);
        }

        $allowedFormats = in_array($platform, ['youtube', 'tiktok', 'instagram', 'facebook', 'twitter'], true)
            ? ['mp3', 'mp4', 'original']
            : ['original'];
        if (! in_array($data['format'], $allowedFormats, true)) {
            return response()->json(['message' => 'The selected format is not available for this platform.'], 422);
        }

        $task = DownloadTask::create([
            'source_url' => $data['url'],
            'platform' => $platform,
            'format' => $data['format'],
            'quality' => $data['quality'] ?? null,
            'bitrate' => $data['bitrate'] ?? null,
            'title' => $data['title'] ?? 'Unknown',
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'status' => 'queued',
        ]);

        ProcessDownloadJob::dispatch($task);
        QueueRunner::trigger();

        return response()->json([
            'uuid' => $task->uuid,
            'status' => 'queued',
        ]);
    }

    public function status(DownloadTask|string $task)
    {
        $taskModel = $task instanceof DownloadTask ? $task : DownloadTask::findOrFail((string) $task);

        $response = [
            'uuid' => $taskModel->uuid,
            'status' => $taskModel->status,
            'progress' => $taskModel->progress,
            'title' => $taskModel->title,
            'platform' => $taskModel->platform,
            'error_message' => $taskModel->error_message,
        ];

        if ($taskModel->status === 'finished') {
            // Generate signed URL
            $expiresAt = $taskModel->expires_at ?? now()->addHours((int) config('downloads.ttl_hours', 1));
            $response['download_url'] = URL::temporarySignedRoute('download.file', $expiresAt, ['uuid' => $taskModel->uuid]);
            $response['filename'] = $taskModel->output_filename;
            $response['size_bytes'] = $taskModel->output_size_bytes;
        }

        return response()->json($response);
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
        $raw = $e->getMessage();
        $message = mb_strtolower($raw);

        $patterns = [
            'disabled' => 'This downloader is temporarily disabled. Please try again later.',
            'sign in to confirm' => 'This content is age-restricted or protected by a bot check.',
            'login required' => 'This content requires authentication to view or download.',
            'is a private video' => 'This media is private and cannot be accessed.',
            'this video is private' => 'This media is private and cannot be accessed.',
            'private account' => 'This account is private and its media cannot be accessed.',
            'account is private' => 'This account is private and its media cannot be accessed.',
            'only followers' => 'This content is private and only available to approved followers.',
            'not available in your country' => 'This media is not available in your region.',
            "isn't available in your country" => 'This media is not available in your region.',
            'georestricted' => 'This media is geographically restricted.',
            'geo-restricted' => 'This media is geographically restricted.',
            'video unavailable' => 'This video is unavailable or has been removed.',
            'this video has been removed' => 'This video has been removed.',
            'post has been removed' => 'This post has been removed.',
            'copyright' => 'This content has been removed due to copyright.',
            'maximum allowed size' => 'This file is too large to download.',
            'too large' => 'This file is too large to download.',
            'returned http 404' => 'The requested file was not found on the source.',
            'returned http 403' => 'The source blocked this request. The media may be protected or restricted.',
            'redirected too many times' => 'The media URL redirected too many times.',
            'could not be resolved' => 'The media host could not be resolved. Please check the link.',
            'points to a web page' => 'The provided link points to a web page, not a direct media file.',
            'reserved networks' => 'Downloads from local or private networks are not allowed.',
        ];

        foreach ($patterns as $needle => $friendly) {
            if (str_contains($message, $needle)) {
                return $friendly;
            }
        }

        // If the exception was a clean custom RuntimeException message without stack traces or path strings
        if ($e instanceof \RuntimeException && ! str_contains($raw, '\\') && ! str_contains($raw, '/') && strlen($raw) < 180) {
            return $raw;
        }

        return 'Could not analyze this link. Please ensure it is publicly accessible and try again.';
    }
}
