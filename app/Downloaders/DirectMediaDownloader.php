<?php

namespace App\Downloaders;

use App\Downloaders\Contracts\DownloaderInterface;
use App\Models\DownloadTask;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DirectMediaDownloader implements DownloaderInterface
{
    public function analyze(string $url): array
    {
        $metadata = $this->transfer($url, null, true);
        $path = parse_url($metadata['url'], PHP_URL_PATH) ?: '';
        $filename = basename(rawurldecode($path)) ?: 'media-file';

        return [
            'platform' => 'direct',
            'title' => Str::limit($filename, 255, ''),
            'thumbnail_url' => null,
            'duration_seconds' => 0,
            'size_bytes' => $metadata['content_length'] ?? null,
            'formats' => ['original' => ['original']],
        ];
    }

    public function download(DownloadTask $task): string
    {
        $outputDirectory = storage_path('app/downloads/'.$task->uuid);
        File::ensureDirectoryExists($outputDirectory, 0755, true);

        $urlPath = rawurldecode(parse_url($task->source_url, PHP_URL_PATH) ?: '');
        $originalName = basename($urlPath) ?: 'download.bin';
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : 'bin';
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME), '_') ?: 'download';
        $outputPath = $outputDirectory.DIRECTORY_SEPARATOR.$safeName.'.'.$extension;

        try {
            $this->transfer($task->source_url, $outputPath, false);

            return $outputPath;
        } catch (Throwable $e) {
            File::deleteDirectory($outputDirectory);
            throw $e;
        }
    }

    /**
     * Transfer a public URL while validating and pinning DNS on every redirect.
     *
     * @return array{url: string, content_type: ?string, content_length: ?int}
     */
    private function transfer(string $url, ?string $destination, bool $headOnly): array
    {
        $maxRedirects = 3;
        $maxBytes = (int) config('downloads.max_bytes', 250 * 1024 * 1024);
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= $maxRedirects; $redirect++) {
            $target = $this->validatedTarget($currentUrl);
            $headers = [];
            $tooLarge = false;
            $handle = null;
            $curl = curl_init();

            if ($curl === false) {
                throw new RuntimeException('The download service could not initialize a transfer.');
            }

            try {
                if ($destination !== null) {
                    $handle = fopen($destination, 'w+b');
                    if ($handle === false) {
                        throw new RuntimeException('The download file could not be created.');
                    }
                    curl_setopt($curl, CURLOPT_FILE, $handle);
                } else {
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                }

                $resolvedIp = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
                curl_setopt_array($curl, [
                    CURLOPT_URL => $currentUrl,
                    CURLOPT_NOBODY => $headOnly,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => (int) config('downloads.process_timeout', 1800),
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_USERAGENT => 'Downloa din/1.0',
                    CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$resolvedIp}"],
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_XFERINFOFUNCTION => function ($resource, $downloadTotal, $downloaded) use ($maxBytes, &$tooLarge): int {
                        if ($downloadTotal > $maxBytes || $downloaded > $maxBytes) {
                            $tooLarge = true;

                            return 1;
                        }

                        return 0;
                    },
                    CURLOPT_HEADERFUNCTION => function ($resource, string $header) use (&$headers): int {
                        $length = strlen($header);
                        if (str_starts_with($header, 'HTTP/')) {
                            $headers = [];
                        } elseif (str_contains($header, ':')) {
                            [$name, $value] = explode(':', $header, 2);
                            $headers[strtolower(trim($name))] = trim($value);
                        }

                        return $length;
                    },
                ]);

                $success = curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $error = curl_error($curl);

                if ($tooLarge || (isset($headers['content-length']) && (int) $headers['content-length'] > $maxBytes)) {
                    throw new RuntimeException('The file exceeds the maximum allowed size.');
                }

                if ($success === false && ! ($headOnly && $status === 405)) {
                    throw new RuntimeException('The remote media server could not complete the transfer. '.$error);
                }

                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    if ($redirect === $maxRedirects || empty($headers['location'])) {
                        throw new RuntimeException('The media URL redirected too many times.');
                    }

                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($headers['location']));

                    continue;
                }

                if (! ($status >= 200 && $status < 300) && ! ($headOnly && $status === 405)) {
                    throw new RuntimeException("The remote media server returned HTTP {$status}.");
                }

                return [
                    'url' => $currentUrl,
                    'content_type' => $headers['content-type'] ?? null,
                    'content_length' => isset($headers['content-length']) ? (int) $headers['content-length'] : null,
                ];
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                curl_close($curl);
            }
        }

        throw new RuntimeException('The media URL could not be resolved.');
    }

    /** @return array{host: string, port: int, ip: string} */
    private function validatedTarget(string $url): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('The media URL is invalid.');
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Only public HTTP and HTTPS URLs without credentials are allowed.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            throw new RuntimeException('Only standard HTTP and HTTPS ports are allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : collect(dns_get_record($host, DNS_A | DNS_AAAA) ?: [])
                ->map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

        if ($ips === []) {
            throw new RuntimeException('The media host could not be resolved.');
        }

        foreach ($ips as $ip) {
            foreach (config('downloads.direct.blocked_ips', []) as $range) {
                if ($this->ipInCidr($ip, $range)) {
                    throw new RuntimeException('Downloads from private or reserved networks are not allowed.');
                }
            }
        }

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    private function ipInCidr(string $ip, string $range): bool
    {
        [$subnet, $prefix] = array_pad(explode('/', $range, 2), 2, null);
        $ipBytes = @inet_pton($ip);
        $subnetBytes = @inet_pton($subnet);

        if ($ipBytes === false || $subnetBytes === false || strlen($ipBytes) !== strlen($subnetBytes)) {
            return false;
        }

        $prefix = filter_var($prefix, FILTER_VALIDATE_INT);
        $maxBits = strlen($ipBytes) * 8;
        if ($prefix === false || $prefix < 0 || $prefix > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if (substr($ipBytes, 0, $wholeBytes) !== substr($subnetBytes, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBytes[$wholeBytes]) & $mask) === (ord($subnetBytes[$wholeBytes]) & $mask);
    }
}
