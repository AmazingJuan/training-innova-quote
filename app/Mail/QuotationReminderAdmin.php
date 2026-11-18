<?php

namespace App\Mail;

use App\Models\QuotationProposalOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminds management of a request still waiting for its quotation, every two
 * and a half business days (see quotations:remind-management). Once the
 * client's offer date has gone by, it says so, with the same frequency.
 */
class QuotationReminderAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public QuotationProposalOrder $quotationOrder,
        public int $number,
        public int $businessDaysWaiting,
        public bool $overdue,
    ) {}

    public function envelope(): Envelope
    {
        $ref = $this->quotationOrder->getQuotationCode() ?? '#'.$this->quotationOrder->getId();

        return new Envelope(
            subject: ($this->overdue ? 'Cotización vencida: ' : 'Recordatorio: cotización pendiente ').$ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.quotation-reminder',
            with: ['offerDate' => $this->quotationOrder->getOfferDateAnswer()],
        );
    }
}
