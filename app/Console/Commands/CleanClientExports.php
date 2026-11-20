<?php

namespace App\Console\Commands;

use App\Support\ClientExportStore;
use Illuminate\Console\Command;

/** Hourly (bootstrap/app.php): exports that were never downloaded. */
class CleanClientExports extends Command
{
    protected $signature = 'clients:clean-exports';

    protected $description = 'Elimina las exportaciones de clientes a Excel que nadie descargó';

    public function handle(ClientExportStore $exports): int
    {
        $this->info('Exportaciones eliminadas: '.$exports->sweep().'.');

        return self::SUCCESS;
    }
}
