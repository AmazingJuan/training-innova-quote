<?php

namespace App\Support;

use App\Enums\ExportStatus;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where the clients' Excel exports wait to be downloaded, and their status.
 * A file is downloaded once (then deleted); anything left behind is swept
 * after RETENTION_HOURS by `clients:clean-exports`.
 */
class ClientExportStore
{
    public const RETENTION_HOURS = 2;

    private const DIRECTORY = 'exports';

    public function __construct(private readonly Cache $cache) {}

    /** A new export: its token, with the status "pending". */
    public function open(): string
    {
        $token = (string) Str::uuid();
        $this->mark($token, ExportStatus::Pending);

        return $token;
    }

    public function mark(string $token, ExportStatus $status): void
    {
        $this->cache->put($this->statusKey($token), $status->value, now()->addHours(self::RETENTION_HOURS));
    }

    public function status(string $token): ExportStatus
    {
        return ExportStatus::tryFrom((string) $this->cache->get($this->statusKey($token))) ?? ExportStatus::Unknown;
    }

    /** Path of the file inside the disk. */
    public function path(string $token): string
    {
        return self::DIRECTORY.'/clientes_'.$token.'.xlsx';
    }

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * Absolute path of a ready file, which stops being downloadable (single
     * use); null when it is not ready or no longer exists.
     */
    public function claim(string $token): ?string
    {
        if ($this->status($token) !== ExportStatus::Ready || ! $this->disk()->exists($this->path($token))) {
            return null;
        }

        $this->cache->forget($this->statusKey($token));

        return $this->disk()->path($this->path($token));
    }

    /** Delete the files older than the retention period; returns how many. */
    public function sweep(): int
    {
        $cutoff = now()->subHours(self::RETENTION_HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($this->disk()->files(self::DIRECTORY) as $file) {
            if ($this->disk()->lastModified($file) < $cutoff) {
                $this->disk()->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function statusKey(string $token): string
    {
        return 'client_export:'.$token;
    }
}
