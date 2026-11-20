<?php

namespace App\Mail;

use App\Models\QuotationProposalOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the client their quotation is ready, with the signed link to it. */
class QuotationGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuotationProposalOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Su cotización '.$this->order->getReference().' está lista');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.quotation-generated');
    }
}
