<?php

namespace App\Downloaders;

use App\Downloaders\Contracts\DownloaderInterface;
use App\Models\DownloadTask;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class YtDlpDownloader implements DownloaderInterface
{
    abstract protected function platform(): string;

    public function analyze(string $url): array
    {
        $this->ensureEnabled();

        try {
            $process = $this->createProcess([
                ...$this->baseCommand(),
                '--dump-single-json',
                '--skip-download',
                '--no-playlist',
                '--no-warnings',
                '--',
                $url,
            ]);
            $process->setTimeout(min(120, (int) config('downloads.process_timeout', 1800)));
            $process->mustRun();

            $metadata = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('Media analysis failed', [
                'platform' => $this->platform(),
                'exception' => $e,
            ]);

            $detail = isset($process) ? $this->friendlyError($process) : null;

            throw new RuntimeException($detail ?? 'The media could not be read. It may be private, restricted, or unavailable.');
        }

        return [
            'platform' => $this->platform(),
            'title' => Str::limit((string) ($metadata['title'] ?? 'Untitled media'), 255, ''),
            'thumbnail_url' => filter_var($metadata['thumbnail'] ?? null, FILTER_VALIDATE_URL) ?: null,
            'duration_seconds' => max(0, (int) ($metadata['duration'] ?? 0)),
            'size_bytes' => $this->estimateSize($metadata),
            'formats' => $this->availableFormats($metadata),
        ];
    }

    public function download(DownloadTask $task): string
    {
        $this->ensureEnabled();

        $outputDirectory = storage_path('app/downloads/'.$task->uuid);
        File::ensureDirectoryExists($outputDirectory, 0755, true);
        $outputTemplate = $outputDirectory.DIRECTORY_SEPARATOR.'media.%(ext)s';

        $arguments = [
            ...$this->baseCommand(),
            '--no-playlist',
            '--no-progress',
            '--no-warnings',
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
            $selector = $quality
                ? "bestvideo[height<={$quality}]+bestaudio/best[height<={$quality}]"
                : 'bestvideo+bestaudio/best';
            array_push($arguments, '--format', $selector, '--merge-output-format', 'mp4');
        } else {
            array_push($arguments, '--format', 'best');
        }

        array_push($arguments, '--output', $outputTemplate, '--', $task->source_url);

        try {
            $process = $this->createProcess($arguments);
            $process->setTimeout((int) config('downloads.process_timeout', 1800));
            $process->mustRun();

            $path = $this->findDownloadedFile($outputDirectory, $process->getOutput());
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
            'private video' => 'This video is private and cannot be downloaded.',
            'private' => 'This media is private and cannot be downloaded.',
            'not available in your country' => 'This media is not available in your region.',
            "isn't available in your country" => 'This media is not available in your region.',
            'georestricted' => 'This media is geographically restricted.',
            'geo-restricted' => 'This media is geographically restricted.',
            'video unavailable' => 'This video is unavailable or has been removed.',
            'copyright' => 'This content has been removed due to copyright.',
            'this live event has ended' => 'This livestream has already ended.',
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
        if ($this->platform() !== 'youtube') {
            return ['original' => ['original']];
        }

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
