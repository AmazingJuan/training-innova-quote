<?php

namespace App\Jobs;

use App\Exports\ClientsExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateClientsExport implements ShouldQueue
{
    use Queueable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const RETENTION_HOURS = 2;

    /**
     * @param  array{search?:string, company?:string, business_unit?:string}  $filters
     */
    public function __construct(
        public string $token,
        public array $filters = [],
    ) {}

    public function handle(): void
    {
        self::markStatus($this->token, self::STATUS_PROCESSING);

        Storage::disk('local')->makeDirectory('exports');

        Excel::store(new ClientsExport($this->filters), self::relativePath($this->token), 'local');

        self::markStatus($this->token, self::STATUS_READY);

        DeleteClientsExportFile::dispatch($this->token)->delay(now()->addHours(self::RETENTION_HOURS));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateClientsExport failed', [
            'token' => $this->token,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        self::markStatus($this->token, self::STATUS_FAILED);
    }

    public static function purge(string $token): void
    {
        $path = self::relativePath($token);

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        Cache::forget(self::statusKey($token));
    }

    public static function markStatus(string $token, string $status): void
    {
        Cache::put(self::statusKey($token), $status, now()->addHours(self::RETENTION_HOURS));
    }

    public static function statusKey(string $token): string
    {
        return 'client_export:'.$token;
    }

    public static function relativePath(string $token): string
    {
        return 'exports/clientes_'.$token.'.xlsx';
    }
}
