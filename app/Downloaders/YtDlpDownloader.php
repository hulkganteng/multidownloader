<?php

namespace App\Downloaders;

use App\Downloaders\Contracts\DownloaderInterface;
use App\Downloaders\Contracts\RemoteStreamDownloaderInterface;
use App\Models\DownloadTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class YtDlpDownloader implements DownloaderInterface, RemoteStreamDownloaderInterface
{
    abstract protected function platform(): string;

    public function analyze(string $url): array
    {
        $this->ensureEnabled();
        $cleanUrl = $this->cleanUrl($url);
        $cacheKey = 'media_meta_'.sha1($cleanUrl);

        return Cache::remember($cacheKey, 900, function () use ($url, $cleanUrl) {
            try {
                $process = $this->createProcess([
                    ...$this->baseCommand(),
                    '--dump-single-json',
                    '--skip-download',
                    '--no-playlist',
                    '--no-warnings',
                    '--',
                    $cleanUrl,
                ]);
                $process->setTimeout(min(120, (int) config('downloads.process_timeout', 1800)));
                $process->mustRun();

                $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                Log::warning('Media analysis failed', [
                    'platform' => $this->platform(),
                    'url' => $url,
                    'clean_url' => $cleanUrl,
                    'process_error' => isset($process) ? $process->getErrorOutput() : null,
                    'process_output' => isset($process) ? $process->getOutput() : null,
                    'exception' => $e->getMessage(),
                ]);

                $detail = isset($process) ? $this->friendlyError($process) : null;

                throw new RuntimeException($detail ?? 'Could not read media from this link. Please ensure it is public and accessible.');
            }

            return [
                'platform' => $this->platform(),
                'title' => Str::limit((string) ($metadata['title'] ?? 'Untitled media'), 255, ''),
                'thumbnail_url' => filter_var($metadata['thumbnail'] ?? null, FILTER_VALIDATE_URL) ?: null,
                'duration_seconds' => max(0, (int) ($metadata['duration'] ?? 0)),
                'size_bytes' => $this->estimateSize($metadata),
                'formats' => $this->availableFormats($metadata),
            ];
        });
    }

    private function cleanUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);

        // Remove tracking params while preserving video identifiers
        if (str_contains($host, 'youtube.com') && isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $v = $query['v'] ?? null;
            if ($v) {
                return "https://www.youtube.com/watch?v={$v}";
            }
        }

        if ($host === 'youtu.be') {
            $path = trim($parts['path'] ?? '', '/');
            if ($path !== '') {
                return "https://youtu.be/{$path}";
            }
        }

        if (str_contains($host, 'instagram.com') || str_contains($host, 'instagr.am')) {
            $path = trim($parts['path'] ?? '', '/');

            return "https://www.instagram.com/{$path}/";
        }

        if (str_contains($host, 'tiktok.com')) {
            $path = trim($parts['path'] ?? '', '/');

            return "https://www.tiktok.com/{$path}";
        }

        return $url;
    }

    public function download(DownloadTask $task): string
    {
        $this->ensureEnabled();

        $outputDirectory = storage_path('app/downloads/'.$task->uuid);
        File::ensureDirectoryExists($outputDirectory, 0755, true);
        $outputTemplate = $outputDirectory.DIRECTORY_SEPARATOR.'media.%(ext)s';
        $cleanUrl = $this->cleanUrl($task->source_url);

        $arguments = [
            ...$this->baseCommand(),
            '--no-playlist',
            '--no-warnings',
            '--newline',
            '--no-mtime',
            '--concurrent-fragments', '4',
            '--buffer-size', '16M',
            '--max-filesize',
            (string) config('downloads.max_bytes'),
            '--print',
            'after_move:filepath',
        ];

        if ($task->format === 'mp3') {
            array_push(
                $arguments,
                '--extract-audio',
                '--audio-format',
                'mp3',
                '--audio-quality',
                strtoupper($task->bitrate ?: '192k')
            );
        } elseif ($task->format === 'mp4') {
            $quality = ctype_digit((string) $task->quality) ? (int) $task->quality : null;
            array_push($arguments, '--format', 'bestvideo+bestaudio/best', '--merge-output-format', 'mp4');
            if ($quality) {
                array_push($arguments, '--format-sort', "res:{$quality}");
            }
        } else {
            array_push($arguments, '--format', 'bestvideo+bestaudio/best');
        }

        array_push($arguments, '--output', $outputTemplate, '--', $cleanUrl);

        try {
            $process = $this->createProcess($arguments);
            $process->setTimeout((int) config('downloads.process_timeout', 1800));

            $lastProgress = 10;
            $lastUpdateTime = microtime(true);
            $processOutput = '';

            $process->run(function ($type, $buffer) use ($task, &$lastProgress, &$lastUpdateTime, &$processOutput) {
                $processOutput .= $buffer;

                // Match yt-dlp progress: [download]  45.2%
                if (preg_match('/\[download\]\s+(\d+(?:\.\d+)?)%/i', $buffer, $matches)) {
                    $pct = (float) $matches[1];
                    $mapped = (int) (10 + ($pct * 0.8)); // map 0-100% download into 10-90% overall
                    $now = microtime(true);

                    if (($mapped - $lastProgress >= 5 || ($now - $lastUpdateTime) >= 2.0) && $mapped < 95) {
                        $lastProgress = $mapped;
                        $lastUpdateTime = $now;
                        $task->update(['progress' => $mapped]);
                    }
                }
            });

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $path = $this->findDownloadedFile($outputDirectory, $processOutput);
            $maxBytes = (int) config('downloads.max_bytes');

            if (filesize($path) > $maxBytes) {
                throw new RuntimeException('The downloaded file exceeded the configured size limit.');
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';
            $safeTitle = Str::slug($task->title ?: 'media', '_') ?: 'media';
            $finalPath = $outputDirectory.DIRECTORY_SEPARATOR.$safeTitle.'.'.$extension;

            if ($path !== $finalPath) {
                File::move($path, $finalPath);
            }

            return $finalPath;
        } catch (Throwable $e) {
            File::deleteDirectory($outputDirectory);
            Log::warning('Media download attempt failed', [
                'task_uuid' => $task->uuid,
                'platform' => $this->platform(),
                'exception' => $e,
            ]);

            $detail = isset($process) ? $this->friendlyError($process) : null;

            throw new RuntimeException($detail ?? 'The source did not provide a downloadable media stream.', previous: $e);
        }
    }

    public function resolveRemoteStreams(DownloadTask $task): ?array
    {
        if ($task->format !== 'mp4' || ! config("downloads.{$this->platform()}.remote_streaming", false)) {
            return null;
        }

        $this->ensureEnabled();
        $quality = ctype_digit((string) $task->quality) ? (int) $task->quality : null;

        $process = $this->createProcess([
            ...$this->baseCommand(),
            '--dump-single-json',
            '--skip-download',
            '--no-playlist',
            '--no-warnings',
            '--format',
            'bestvideo+bestaudio/best',
            ...($quality ? ['--format-sort', "res:{$quality}"] : []),
            '--',
            $this->cleanUrl($task->source_url),
        ]);
        $process->setTimeout(min(120, (int) config('downloads.process_timeout', 1800)));
        $process->mustRun();

        $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $formats = $metadata['requested_formats'] ?? null;

        if (! is_array($formats) || $formats === []) {
            $requested = $metadata['requested_downloads'][0] ?? $metadata;
            $formats = is_array($requested['requested_formats'] ?? null)
                ? $requested['requested_formats']
                : [$requested];
        }

        $streams = [];
        $totalSize = 0;

        foreach ($formats as $format) {
            $url = $format['url'] ?? null;
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }

            $headers = [];
            foreach (($format['http_headers'] ?? []) as $name => $value) {
                if (is_string($name) && is_string($value) && ! str_contains($name.$value, "\r") && ! str_contains($name.$value, "\n")) {
                    $headers[$name] = $value;
                }
            }

            $size = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $totalSize += max(0, $size);
            $streams[] = [
                'url' => $url,
                'headers' => $headers,
                'vcodec' => (string) ($format['vcodec'] ?? 'none'),
                'acodec' => (string) ($format['acodec'] ?? 'none'),
            ];
        }

        if ($streams === [] || count($streams) > 2) {
            return null;
        }

        $maxBytes = (int) config('downloads.max_bytes');
        if ($totalSize > 0 && $totalSize > $maxBytes) {
            throw new RuntimeException('The selected media exceeds the maximum allowed size.');
        }

        $safeTitle = Str::slug($task->title ?: 'media', '_') ?: 'media';

        return [
            'streams' => $streams,
            'filename' => $safeTitle.'.mp4',
            'mime' => 'video/mp4',
            'size_bytes' => $totalSize > 0 ? $totalSize : null,
        ];
    }

    private function estimateSize(array $metadata): ?int
    {
        $videoSize = 0;
        $audioSize = 0;

        foreach ($metadata['formats'] ?? [] as $format) {
            $size = (int) ($format['filesize'] ?? $format['filesize_approx'] ?? 0);
            $vcodec = (string) ($format['vcodec'] ?? 'none');
            $acodec = (string) ($format['acodec'] ?? 'none');

            if ($vcodec !== 'none' && $size > $videoSize) {
                $videoSize = $size;
            } elseif ($acodec !== 'none' && $vcodec === 'none' && $size > $audioSize) {
                $audioSize = $size;
            }
        }

        $total = $videoSize + $audioSize;

        return $total > 0 ? $total : null;
    }

    private function friendlyError(Process $process): ?string
    {
        $output = $process->getErrorOutput()."\n".$process->getOutput();
        $message = mb_strtolower($output);

        $patterns = [
            'sign in to confirm' => 'This content is age-restricted or protected by a bot check.',
            'login required' => 'This content requires authentication. The post may be private or restricted.',
            'private video' => 'This video is private and cannot be downloaded.',
            'is a private video' => 'This video is private and cannot be downloaded.',
            'this video is private' => 'This video is private and cannot be downloaded.',
            'account is private' => 'This account is private and cannot be accessed.',
            'private account' => 'This account is private and cannot be accessed.',
            'only followers' => 'This content is private and only available to approved followers.',
            'not available in your country' => 'This media is not available in your region.',
            "isn't available in your country" => 'This media is not available in your region.',
            'georestricted' => 'This media is geographically restricted.',
            'geo-restricted' => 'This media is geographically restricted.',
            'video unavailable' => 'This video is unavailable or has been removed.',
            'this video has been removed' => 'This video has been removed.',
            'post has been removed' => 'This post has been removed.',
            'copyright' => 'This content has been removed due to copyright.',
            'this live event has ended' => 'This livestream has already ended.',
            'unable to extract' => 'Could not extract media info from this link. The post may be restricted or unavailable.',
            'rate-limit' => 'Too many requests. Please try again in a few moments.',
        ];

        foreach ($patterns as $needle => $friendly) {
            if (str_contains($message, $needle)) {
                return $friendly;
            }
        }

        return null;
    }

    protected function availableFormats(array $metadata): array
    {
        $qualities = collect($metadata['formats'] ?? [])
            ->filter(fn (array $format) => ($format['vcodec'] ?? 'none') !== 'none' && ! empty($format['height']))
            ->pluck('height')
            ->map(fn ($height) => (string) (int) $height)
            ->unique()
            ->sortDesc(SORT_NUMERIC)
            ->values()
            ->all();

        return [
            'mp4' => $qualities ?: ['default'],
            'mp3' => ['128k', '192k', '320k'],
        ];
    }

    private function findDownloadedFile(string $directory, string $processOutput): string
    {
        $reportedPaths = array_reverse(array_filter(array_map('trim', preg_split('/\R/', $processOutput) ?: [])));

        foreach ($reportedPaths as $reportedPath) {
            if (is_file($reportedPath)) {
                return $reportedPath;
            }
        }

        $files = collect(File::files($directory))
            ->reject(fn ($file) => str_ends_with($file->getFilename(), '.part'));

        if ($files->count() !== 1) {
            throw new RuntimeException('The downloader did not produce exactly one output file.');
        }

        return $files->first()->getPathname();
    }

    private function ensureEnabled(): void
    {
        if (! config("downloads.{$this->platform()}.enabled", false)) {
            throw new RuntimeException(ucfirst($this->platform()).' downloads are disabled.');
        }
    }

    private function binaryPath(): string
    {
        return $this->resolveConfiguredPath(
            (string) config("downloads.{$this->platform()}.bin_path", 'yt-dlp')
        );
    }

    /** @return list<string> */
    private function baseCommand(): array
    {
        $pythonPath = config('downloads.yt_dlp_python_path');
        $pythonPath = is_string($pythonPath) && $pythonPath !== ''
            ? $this->resolveConfiguredPath($pythonPath)
            : null;
        $command = $pythonPath
            ? [$pythonPath, '-m', 'yt_dlp']
            : [$this->binaryPath()];
        $ffmpegPath = config('downloads.ffmpeg_path');

        if (is_string($ffmpegPath) && $ffmpegPath !== '') {
            $ffmpegPath = $this->resolveConfiguredPath($ffmpegPath);
            array_push($command, '--ffmpeg-location', $ffmpegPath);
        }

        $cookiesPath = config('downloads.yt_dlp_cookies_path');
        if (is_string($cookiesPath) && $cookiesPath !== '') {
            $cookiesPath = $this->resolveConfiguredPath($cookiesPath);
            if (file_exists($cookiesPath)) {
                array_push($command, '--cookies', $cookiesPath);
            }
        }

        // Anti-bot extractor arguments, browser user-agent & options
        array_push(
            $command,
            '--no-check-certificates',
            '--user-agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            '--add-header',
            'Accept-Language:en-US,en;q=0.9',
            '--add-header',
            'Sec-Fetch-Mode:navigate',
            '--extractor-args',
            'youtube:player_client=android,web,ios'
        );

        return $command;
    }

    private function createProcess(array $arguments): Process
    {
        $temporaryDirectory = storage_path('app/tmp');
        File::ensureDirectoryExists($temporaryDirectory, 0755, true);

        $environment = [
            'TEMP' => $temporaryDirectory,
            'TMP' => $temporaryDirectory,
            // Avoid relying on OS entropy during startup in restricted accounts.
            'PYTHONHASHSEED' => '1',
        ];

        // Windows needs these variables to locate system DLLs and Winsock
        // providers. They can be absent from PHP's request environment even
        // though they are available to the parent process.
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['SystemRoot', 'WINDIR', 'PATH', 'COMSPEC', 'PATHEXT', 'SystemDrive'] as $name) {
                $value = getenv($name) ?: ($_SERVER[$name] ?? $_ENV[$name] ?? null);

                if (is_string($value) && $value !== '') {
                    $environment[$name] = $value;
                }
            }

            // Tunnel/service launchers sometimes start PHP with a reduced
            // environment. Python's socket module needs SystemRoot and the
            // Windows system directories to initialize Winsock (WinError 10106).
            $systemRoot = $environment['SystemRoot'] ?? $environment['WINDIR'] ?? null;

            if (! is_string($systemRoot) || $systemRoot === '') {
                $systemDrive = $environment['SystemDrive'] ?? 'C:';
                $systemRoot = $systemDrive.'\\Windows';
            }

            $environment['SystemRoot'] = $systemRoot;
            $environment['WINDIR'] = $systemRoot;
            $environment['SystemDrive'] ??= substr($systemRoot, 0, 2);
            $environment['COMSPEC'] ??= $systemRoot.'\\System32\\cmd.exe';
            $environment['PATHEXT'] ??= '.COM;.EXE;.BAT;.CMD';

            $requiredPaths = [
                $systemRoot.'\\System32',
                $systemRoot,
                $systemRoot.'\\System32\\Wbem',
            ];

            foreach ([config('downloads.yt_dlp_python_path'), config('downloads.ffmpeg_path')] as $binary) {
                if (is_string($binary) && $binary !== '') {
                    $binary = $this->resolveConfiguredPath($binary);
                    $requiredPaths[] = is_dir($binary) ? $binary : dirname($binary);
                }
            }

            $currentPath = $environment['PATH'] ?? '';
            $environment['PATH'] = implode(PATH_SEPARATOR, array_unique([
                ...$requiredPaths,
                ...array_filter(explode(PATH_SEPARATOR, $currentPath)),
            ]));
        }

        return new Process(
            $arguments,
            base_path(),
            $environment
        );
    }

    private function resolveConfiguredPath(string $path): string
    {
        $path = trim($path);

        // A bare command such as "yt-dlp" should still be resolved through PATH.
        if (! str_contains($path, '/') && ! str_contains($path, '\\')) {
            return $path;
        }

        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        if ($isAbsolute) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }
}
