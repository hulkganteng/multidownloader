<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDownloadJob;
use App\Models\DownloadTask;
use App\Services\DownloadService;
use App\Services\PlatformDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WebDownloadController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function analyze(Request $request, PlatformDetector $detector, DownloadService $service)
    {
        $request->validate(['url' => 'required|url']);

        $platform = $detector->detect($request->url);

        if (! $platform) {
            return back()->withInput()->with('error', 'This link is not supported. Paste a TikTok, Instagram, YouTube, or direct media link.');
        }

        try {
            $downloader = $service->getDownloader($platform);
            $metadata = $downloader->analyze($request->url);

            // Pass metadata including origin URL to the view
            $metadata['original_url'] = $request->url;

            return view('analyze', compact('metadata'));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('error', $this->friendlyErrorMessage($e));
        }
    }

    public function process(Request $request, PlatformDetector $detector)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'format' => ['required', Rule::in(['mp3', 'mp4', 'original'])],
            'quality' => ['nullable', 'string', 'regex:/^(default|\d{2,4})$/'],
            'bitrate' => ['nullable', Rule::in(['128k', '192k', '256k', '320k'])],
            'title' => 'nullable|string|max:255',
            'thumbnail_url' => 'nullable|url|max:2048',
            'duration_seconds' => 'nullable|integer|min:0|max:86400',
        ]);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            return redirect()->route('home')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $platform = $detector->detect($data['url']);

        if (! $platform) {
            $message = 'This link is not supported. Only public HTTP or HTTPS media URLs work here.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('home')->withInput()->withErrors(['url' => $message]);
        }

        $allowedFormats = $platform === 'youtube' ? ['mp3', 'mp4'] : ['original'];
        if (! in_array($data['format'], $allowedFormats, true)) {
            $message = 'The selected format is not available for this platform.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->route('home')->withInput()->withErrors(['format' => $message]);
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

        if ($request->wantsJson()) {
            return response()->json([
                'uuid' => $task->uuid,
                'status' => 'queued',
            ]);
        }

        return redirect()->route('task.show', ['uuid' => $task->uuid]);
    }

    public function show($uuid)
    {
        $task = DownloadTask::where('uuid', $uuid)->firstOrFail();

        return view('status', compact('task'));
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
