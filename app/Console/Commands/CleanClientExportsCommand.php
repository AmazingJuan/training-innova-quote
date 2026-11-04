<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClientsExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanClientExportsCommand extends Command
{
    protected $signature = 'exports:clean-clientes';

    protected $description = 'Delete client export files older than the configured retention period';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $directory = 'exports';
        $cutoff = now()->subHours(GenerateClientsExport::RETENTION_HOURS)->getTimestamp();
        $deleted = 0;

        if (! $disk->exists($directory)) {
            $this->info('No export directory found.');

            return self::SUCCESS;
        }

        foreach ($disk->files($directory) as $file) {
            if ($disk->lastModified($file) >= $cutoff) {
                continue;
            }

            $disk->delete($file);
            $deleted++;
        }

        $this->info("Deleted {$deleted} expired export file(s).");

        return self::SUCCESS;
    }
}
