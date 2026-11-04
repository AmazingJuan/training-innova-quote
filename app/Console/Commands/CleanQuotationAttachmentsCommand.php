<?php

namespace App\Console\Commands;

use App\Support\QuotationAttachment;
use Illuminate\Console\Command;

/**
 * Safety net for the documents of a request: they are deleted by the job that
 * mails them, so anything still here after the retention period belongs to a
 * notice that never went out, and nothing else will ever read it.
 */
class CleanQuotationAttachmentsCommand extends Command
{
    protected $signature = 'adjuntos:clean-cotizaciones';

    protected $description = 'Delete quotation attachments left waiting longer than the retention period';

    public function handle(): int
    {
        $deleted = QuotationAttachment::sweep();

        $this->info("Deleted {$deleted} stale quotation attachment(s).");

        return self::SUCCESS;
    }
}
