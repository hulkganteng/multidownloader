<?php

namespace App\Models;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DownloadTask implements Arrayable, Jsonable
{
    public string $uuid;
    public string $source_url = '';
    public string $platform = 'direct';
    public string $format = 'original';
    public ?string $quality = null;
    public ?string $bitrate = null;
    public string $title = 'Unknown';
    public ?string $thumbnail_url = null;
    public ?int $duration_seconds = null;
    public string $status = 'queued';
    public int $progress = 0;
    public ?string $error_message = null;
    public ?string $output_path = null;
    public ?string $output_filename = null;
    public ?int $output_size_bytes = null;
    public ?string $output_mime = null;
    public ?Carbon $finished_at = null;
    public ?Carbon $expires_at = null;
    public ?Carbon $created_at = null;
    public ?Carbon $updated_at = null;

    public function __construct(array $attributes = [])
    {
        $this->uuid = (string) Str::uuid();
        $this->created_at = now();
        $this->updated_at = now();
        $this->fill($attributes);
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if (property_exists($this, $key)) {
                if (in_array($key, ['finished_at', 'expires_at', 'created_at', 'updated_at'], true)) {
                    $this->{$key} = is_string($value) ? Carbon::parse($value) : $value;
                } else {
                    $this->{$key} = $value;
                }
            }
        }

        return $this;
    }

    public static function storageDir(?string $uuid = null): string
    {
        $base = storage_path('app/'.config('downloads.storage_path', 'downloads'));

        return $uuid ? $base.DIRECTORY_SEPARATOR.$uuid : $base;
    }

    public static function metaPath(string $uuid): string
    {
        return self::storageDir($uuid).DIRECTORY_SEPARATOR.'task.json';
    }

    public static function create(array $attributes): self
    {
        $task = new self($attributes);
        $task->save();

        return $task;
    }

    public function save(): bool
    {
        $dir = self::storageDir($this->uuid);
        File::ensureDirectoryExists($dir, 0755, true);
        $this->updated_at = now();

        $data = $this->toArray();

        return (bool) file_put_contents(self::metaPath($this->uuid), json_encode($data, JSON_PRETTY_PRINT));
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);

        return $this->save();
    }

    public static function find(string $uuid): ?self
    {
        $path = self::metaPath($uuid);
        if (! file_exists($path)) {
            return null;
        }

        try {
            $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return new self($data);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function findOrFail(string $uuid): self
    {
        $task = self::find($uuid);
        if (! $task) {
            abort(404, 'Download task not found.');
        }

        return $task;
    }

    public static function where(string $column, $value): object
    {
        return new class($column, $value) {
            public function __construct(private string $column, private mixed $value) {}

            public function first(): ?DownloadTask
            {
                if ($this->column === 'uuid') {
                    return DownloadTask::find((string) $this->value);
                }

                return null;
            }

            public function firstOrFail(): DownloadTask
            {
                if ($this->column === 'uuid') {
                    return DownloadTask::findOrFail((string) $this->value);
                }

                abort(404, 'Download task not found.');
            }
        };
    }

    /**
     * @return array<int, self>
     */
    public static function allTasks(): array
    {
        $base = self::storageDir();
        if (! is_dir($base)) {
            return [];
        }

        $tasks = [];
        foreach (File::directories($base) as $dir) {
            $uuid = basename($dir);
            $task = self::find($uuid);
            if ($task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    /**
     * @return array<int, self>
     */
    public static function expired(): array
    {
        return array_values(array_filter(self::allTasks(), function (DownloadTask $task) {
            return $task->expires_at && $task->expires_at->isPast();
        }));
    }

    public function delete(): bool
    {
        $dir = self::storageDir($this->uuid);
        if (is_dir($dir)) {
            return File::deleteDirectory($dir);
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'source_url' => $this->source_url,
            'platform' => $this->platform,
            'format' => $this->format,
            'quality' => $this->quality,
            'bitrate' => $this->bitrate,
            'title' => $this->title,
            'thumbnail_url' => $this->thumbnail_url,
            'duration_seconds' => $this->duration_seconds,
            'status' => $this->status,
            'progress' => $this->progress,
            'error_message' => $this->error_message,
            'output_path' => $this->output_path,
            'output_filename' => $this->output_filename,
            'output_size_bytes' => $this->output_size_bytes,
            'output_mime' => $this->output_mime,
            'finished_at' => $this->finished_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
