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
        $expiredTasks = DownloadTask::expired();
        $count = count($expiredTasks);

        foreach ($expiredTasks as $task) {
            $task->delete();
        }

        $this->info("Cleaned up {$count} expired downloads.");
    }
}
