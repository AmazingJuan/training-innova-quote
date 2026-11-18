<?php

namespace App\Mail;

use App\Models\QuotationProposalOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Confirms to the client that their request was received. */
class QuotationPending extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuotationProposalOrder $quotationOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recibimos su solicitud de cotización '.$this->quotationOrder->getReference());
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quotation-pending');
    }
}
