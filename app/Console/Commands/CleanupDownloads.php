<?php

namespace App\Console\Commands;

use App\Models\DownloadTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CleanupDownloads extends Command
{
    protected $signature = 'downloads:cleanup';

    protected $description = 'Remove expired downloaded files';

    public function handle()
    {
        $expiredTasks = DownloadTask::expired()->get();
        $downloadRoot = storage_path('app/'.config('downloads.storage_path', 'downloads'));

        foreach ($expiredTasks as $task) {
            if (Str::isUuid($task->uuid)) {
                File::deleteDirectory($downloadRoot.DIRECTORY_SEPARATOR.$task->uuid);
            }

            $task->delete();
        }

        $this->info('Cleaned up '.$expiredTasks->count().' expired downloads.');
    }
}
