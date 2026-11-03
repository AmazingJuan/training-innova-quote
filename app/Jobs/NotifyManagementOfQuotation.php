<?php

namespace App\Jobs;

use App\Mail\QuotationPendingAdmin;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use App\Support\QuotationAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells management about a new request, with the documents the client attached.
 *
 * The e-mail is sent from inside the job instead of being queued again,
 * because its attachments wait on disk only until it goes out: this job is
 * what deletes them, both when it sends and when it definitely cannot. Files
 * are kept while a retry is still possible, so no retry goes out empty.
 *
 * The request is looked up by id (not serialized) so that a request deleted
 * meanwhile still lets the job run and clean up after itself.
 */
class NotifyManagementOfQuotation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{path: string, name: string, mime: string|null}>  $files
     */
    public function __construct(
        public string $orderId,
        public array $files = [],
    ) {}

    public function handle(): void
    {
        $order = QuotationProposalOrder::query()->with('client')->find($this->orderId);
        $email = Configuration::current()?->getNotificationEmail();

        if ($order === null || $email === null) {
            Log::warning('Nueva solicitud sin aviso a gerencia.', [
                'order' => $this->orderId,
                'reason' => $order === null ? 'la solicitud ya no existe' : 'falta el correo de notificaciones',
            ]);

            // Retrying would not fix either case, so the attachments go now.
            QuotationAttachment::discard($this->files);

            return;
        }

        // Throws on a mail failure: the files stay for the retry, and failed() removes them after the last one.
        Mail::to($email)->send(new QuotationPendingAdmin($order, $this->files));

        QuotationAttachment::discard($this->files);
    }

    /** The last try failed: the attachments are of no use to anyone now. */
    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo avisar a gerencia de una nueva solicitud.', [
            'order' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);

        QuotationAttachment::discard($this->files);
    }
}
