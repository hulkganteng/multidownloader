<?php

namespace App\Http\Controllers;

use App\Models\DownloadTask;
use App\Services\TikTokEmbedExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Throwable;

class DownloadFileController extends Controller
{
    public function __invoke(Request $request, string $uuid): BinaryFileResponse|StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $task = DownloadTask::findOrFail($uuid);

        if ($task->delivery_method === 'stream' && $task->remote_streams !== []) {
            if ($task->platform === 'tiktok') {
                return $this->prepareTikTokDownload($task);
            }

            return $this->streamRemoteMedia($task);
        }

        $path = DownloadTask::storageDir($task->uuid).DIRECTORY_SEPARATOR.$task->output_filename;

        if (! is_file($path)) {
            abort(404, 'File not found on server. Please process the media again.');
        }

        return response()->download($path, $task->output_filename, [
            'Accept-Ranges' => 'bytes',
            'Content-Type' => $task->output_mime ?: 'application/octet-stream',
        ])->deleteFileAfterSend(true);
    }

    /**
     * TikTok CDN URLs are short-lived and FFmpeg's Windows DNS resolver can fail
     * after response headers have already reached the browser. Refresh and fully
     * prepare the disposable file first so a failure remains a normal HTTP error.
     */
    private function prepareTikTokDownload(DownloadTask $task): BinaryFileResponse
    {
        $directory = DownloadTask::storageDir($task->uuid);
        File::ensureDirectoryExists($directory, 0755, true);
        $token = (string) Str::uuid();
        $sourcePath = $directory.DIRECTORY_SEPARATOR.'.delivery-'.$token.'.source.mp4';
        $extension = $task->format === 'mp3' ? 'mp3' : 'mp4';
        $outputPath = $directory.DIRECTORY_SEPARATOR.'.delivery-'.$token.'.'.$extension;

        try {
            $media = app(TikTokEmbedExtractor::class)->extract($task->source_url);
            $this->downloadRemoteFile($media['stream_url'], $media['headers'], $sourcePath);

            if ($task->format === 'mp3') {
                $this->convertTikTokAudio($sourcePath, $outputPath, $task);
                @unlink($sourcePath);
            } else {
                if (! @rename($sourcePath, $outputPath)) {
                    throw new RuntimeException('Could not prepare the temporary TikTok video.');
                }
            }

            if (! is_file($outputPath) || filesize($outputPath) <= 0) {
                throw new RuntimeException('TikTok returned an empty media file.');
            }

            return response()->download($outputPath, $task->output_filename ?: 'tiktok.'.$extension, [
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'private, no-store',
                'Content-Type' => $task->format === 'mp3' ? 'audio/mpeg' : 'video/mp4',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            @unlink($sourcePath);
            @unlink($outputPath);

            Log::warning('TikTok delivery preparation failed', [
                'task_uuid' => $task->uuid,
                'error' => $exception->getMessage(),
            ]);

            abort(502, 'TikTok media could not be prepared. Please try the download again.');
        }
    }

    /** @param array<string, string> $headers */
    private function downloadRemoteFile(string $url, array $headers, string $destination): void
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('The PHP cURL extension is required for TikTok downloads.');
        }

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Could not create the temporary TikTok file.');
        }

        $downloaded = 0;
        $maxBytes = max(1, (int) config('downloads.max_bytes', 250 * 1024 * 1024));
        $curl = curl_init($url);
        $headerLines = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_string($value) && ! str_contains($name.$value, "\r") && ! str_contains($name.$value, "\n")) {
                $headerLines[] = $name.': '.$value;
            }
        }

        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => max(60, (int) config('downloads.process_timeout', 1800)),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FAILONERROR => false,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, string $chunk) use ($handle, &$downloaded, $maxBytes): int {
                $length = strlen($chunk);
                if ($downloaded + $length > $maxBytes) {
                    return 0;
                }

                $written = fwrite($handle, $chunk);
                if ($written === false) {
                    return 0;
                }

                $downloaded += $written;

                return $written;
            },
        ]);

        try {
            $success = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $contentType = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
            $error = curl_error($curl);
        } finally {
            curl_close($curl);
            fclose($handle);
        }

        if ($downloaded >= $maxBytes) {
            throw new RuntimeException('TikTok media exceeds the configured download size limit.');
        }

        if ($success === false || $status < 200 || $status >= 300 || $downloaded === 0) {
            throw new RuntimeException('TikTok CDN request failed'.($error !== '' ? ': '.$error : " (HTTP {$status})"));
        }

        if ($contentType !== '' && ! str_starts_with($contentType, 'video/') && ! str_starts_with($contentType, 'application/octet-stream')) {
            throw new RuntimeException("TikTok CDN returned unexpected content type {$contentType}.");
        }
    }

    private function convertTikTokAudio(string $sourcePath, string $outputPath, DownloadTask $task): void
    {
        $bitrate = in_array($task->bitrate, ['128k', '192k', '256k', '320k'], true) ? $task->bitrate : '192k';
        $process = new Process([
            $this->ffmpegBinary(), '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $sourcePath, '-vn', '-c:a', 'libmp3lame', '-b:a', $bitrate,
            $outputPath,
        ], base_path());
        $process->setTimeout((float) config('downloads.process_timeout', 1800));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFmpeg could not convert the TikTok audio: '.trim($process->getErrorOutput()));
        }
    }

    private function streamRemoteMedia(DownloadTask $task): StreamedResponse
    {
        $streams = $this->validatedStreams($task->remote_streams);
        $ffmpeg = $this->ffmpegBinary();
        $arguments = [$ffmpeg, '-hide_banner', '-loglevel', 'error'];

        foreach ($streams as $stream) {
            if ($stream['headers'] !== []) {
                $headerLines = '';
                foreach ($stream['headers'] as $name => $value) {
                    $headerLines .= $name.': '.$value."\r\n";
                }
                array_push($arguments, '-headers', $headerLines);
            }

            array_push($arguments, '-i', $stream['url']);
        }

        $videoIndex = $this->streamIndex($streams, 'vcodec');
        $audioIndex = $this->streamIndex($streams, 'acodec');

        $isAudio = $task->format === 'mp3';

        if (! $isAudio && $videoIndex === null) {
            throw new RuntimeException('The remote source did not provide a video stream.');
        }

        if ($isAudio) {
            if ($audioIndex === null) {
                throw new RuntimeException('The remote source did not provide an audio stream.');
            }

            $bitrate = in_array($task->bitrate, ['128k', '192k', '256k', '320k'], true) ? $task->bitrate : '192k';
            array_push($arguments, '-map', $audioIndex.':a:0', '-vn', '-c:a', 'libmp3lame', '-b:a', $bitrate, '-f', 'mp3', 'pipe:1');
        } else {
            array_push($arguments, '-map', $videoIndex.':v:0');
            if ($audioIndex !== null) {
                array_push($arguments, '-map', $audioIndex.':a:0');
            }

            array_push(
                $arguments,
                '-c', 'copy',
                '-f', 'mp4',
                '-movflags', 'frag_keyframe+empty_moov',
                'pipe:1'
            );
        }

        $filename = $task->output_filename ?: 'media.mp4';

        return response()->streamDownload(function () use ($arguments, $task) {
            $process = new Process($arguments, base_path());
            $process->setTimeout((float) config('downloads.process_timeout', 1800));
            $errorOutput = '';

            $process->run(function (string $type, string $buffer) use (&$errorOutput) {
                if ($type === Process::OUT) {
                    echo $buffer;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();

                    return;
                }

                $errorOutput = substr($errorOutput.$buffer, -8192);
            });

            if (! $process->isSuccessful()) {
                Log::warning('Remote media stream ended unsuccessfully', [
                    'task_uuid' => $task->uuid,
                    'platform' => $task->platform,
                    'exit_code' => $process->getExitCode(),
                    'ffmpeg_error' => trim($errorOutput),
                ]);

                throw new RuntimeException('The remote media stream ended before the download was complete.');
            }
        }, $filename, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $task->output_mime ?: 'video/mp4',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<int, mixed>  $streams
     * @return list<array{url: string, headers: array<string, string>, vcodec: string, acodec: string}>
     */
    private function validatedStreams(array $streams): array
    {
        if ($streams === [] || count($streams) > 2) {
            throw new RuntimeException('Invalid remote stream configuration.');
        }

        $validated = [];
        foreach ($streams as $stream) {
            $url = is_array($stream) ? ($stream['url'] ?? null) : null;
            $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';

            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array($scheme, ['http', 'https'], true)) {
                throw new RuntimeException('Invalid remote media URL.');
            }

            $headers = [];
            foreach (($stream['headers'] ?? []) as $name => $value) {
                if (is_string($name) && is_string($value) && ! str_contains($name.$value, "\r") && ! str_contains($name.$value, "\n")) {
                    $headers[$name] = $value;
                }
            }

            $validated[] = [
                'url' => $url,
                'headers' => $headers,
                'vcodec' => (string) ($stream['vcodec'] ?? 'none'),
                'acodec' => (string) ($stream['acodec'] ?? 'none'),
            ];
        }

        return $validated;
    }

    /** @param list<array{vcodec: string, acodec: string}> $streams */
    private function streamIndex(array $streams, string $codec): ?int
    {
        foreach ($streams as $index => $stream) {
            if ($stream[$codec] !== 'none') {
                return $index;
            }
        }

        return null;
    }

    private function ffmpegBinary(): string
    {
        $configured = (string) config('downloads.ffmpeg_path', 'ffmpeg');
        $path = $configured;

        if (str_contains($path, '/') || str_contains($path, '\\')) {
            $isAbsolute = str_starts_with($path, '/')
                || str_starts_with($path, '\\\\')
                || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
            $path = $isAbsolute ? $path : base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        }

        if (is_dir($path)) {
            $path .= DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'ffmpeg.exe' : 'ffmpeg');
        }

        if ((str_contains($path, '/') || str_contains($path, '\\')) && ! is_file($path)) {
            throw new RuntimeException('FFmpeg binary was not found for remote streaming.');
        }

        return $path;
    }
}
