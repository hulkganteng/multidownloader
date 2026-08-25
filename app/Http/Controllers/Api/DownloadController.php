<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDownloadJob;
use App\Models\DownloadTask;
use App\Services\DownloadService;
use App\Services\PlatformDetector;
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

        $allowedFormats = $platform === 'youtube' ? ['mp3', 'mp4'] : ['original'];
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

        return response()->json([
            'uuid' => $task->uuid,
            'status' => 'queued',
        ]);
    }

    public function status(DownloadTask $task)
    {
        $response = [
            'uuid' => $task->uuid,
            'status' => $task->status,
            'progress' => $task->progress,
            'title' => $task->title,
            'platform' => $task->platform,
            'error_message' => $task->error_message,
        ];

        if ($task->status === 'finished') {
            // Generate signed URL
            $expiresAt = $task->expires_at ?? now()->addHours(config('downloads.ttl_hours', 24));
            $response['download_url'] = URL::temporarySignedRoute('download.file', $expiresAt, ['uuid' => $task->uuid]);
            $response['filename'] = $task->output_filename;
            $response['size_bytes'] = $task->output_size_bytes;
        }

        return response()->json($response);
    }

    private function friendlyErrorMessage(\Throwable $e): string
    {
        $message = mb_strtolower($e->getMessage());
        $patterns = [
            'disabled' => 'This downloader is temporarily disabled. Please try again later.',
            'private' => 'This media is private and cannot be accessed.',
            'not available in your country' => 'This media is not available in your region.',
            "isn't available in your country" => 'This media is not available in your region.',
            'georestricted' => 'This media is geographically restricted.',
            'geo-restricted' => 'This media is geographically restricted.',
            'video unavailable' => 'This video is unavailable or has been removed.',
            'sign in to confirm' => 'This content is age-restricted or protected by a bot check.',
            'copyright' => 'This content has been removed due to copyright.',
            'maximum allowed size' => 'This file is too large to download.',
            'too large' => 'This file is too large to download.',
            'returned http 404' => 'The requested file was not found on the source.',
            'returned http 403' => 'The source blocked this request. The media may be protected.',
            'redirected too many times' => 'The media URL redirected too many times.',
            'could not be resolved' => 'The media host could not be resolved.',
            'could not be read' => 'The media could not be read. It may be private, restricted, or unavailable.',
        ];

        foreach ($patterns as $needle => $friendly) {
            if (str_contains($message, $needle)) {
                return $friendly;
            }
        }

        return 'We could not analyze that link. Check that it is public and try again.';
    }
}
