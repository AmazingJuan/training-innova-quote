<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Exports\ClientsExport;
use App\Support\ClientExportStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/** Builds the clients' Excel in the background; the page polls its status. */
class GenerateClientsExport implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{search?:string, company?:string, business_unit?:string}  $filters
     */
    public function __construct(
        public string $token,
        public array $filters = [],
    ) {}

    public function handle(ClientExportStore $exports): void
    {
        $exports->mark($this->token, ExportStatus::Processing);

        Excel::store(new ClientsExport($this->filters), $exports->path($this->token), 'local');

        $exports->mark($this->token, ExportStatus::Ready);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo generar la exportación de clientes.', [
            'token' => $this->token,
            'message' => $exception?->getMessage(),
        ]);

        app(ClientExportStore::class)->mark($this->token, ExportStatus::Failed);
    }
}
