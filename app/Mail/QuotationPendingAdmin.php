<?php

namespace App\Mail;

use App\Models\QuotationProposalOrder;
use App\Support\QuotationAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationPendingAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{path: string, name: string, mime: string|null}>  $files
     *                                                                             Documents attached by the client, waiting on the attachments disk.
     */
    public function __construct(
        public QuotationProposalOrder $quotationOrder,
        public array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->quotationOrder->getQuotationCode() ?? '#'.$this->quotationOrder->getId();

        return new Envelope(
            subject: 'Nueva solicitud de cotización '.$ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.quotation-pending',
            with: ['attachedFiles' => array_column($this->files, 'name')],
        );
    }

    /**
     * The client's documents: this e-mail is the only place they are kept.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(function (array $file) {
            $attachment = Attachment::fromStorageDisk(QuotationAttachment::DISK, $file['path'])->as($file['name']);

            return $file['mime'] === null ? $attachment : $attachment->withMime($file['mime']);
        }, $this->files);
    }
}
