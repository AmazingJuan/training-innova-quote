<?php

namespace App\Console\Commands;

use App\Support\QuotationAttachment;
use Illuminate\Console\Command;

/**
 * Safety net for the documents of a request: they are deleted by the job that
 * mails them, so anything still here after the retention period belongs to a
 * notice that never went out, and nothing else will ever read it.
 */
class CleanQuotationAttachments extends Command
{
    protected $signature = 'quotations:clean-attachments';

    protected $description = 'Elimina los documentos adjuntos que quedaron esperando un correo que nunca salió';

    public function handle(): int
    {
        $deleted = QuotationAttachment::sweep();

        $this->info("Adjuntos eliminados: {$deleted}.");

        return self::SUCCESS;
    }
}
