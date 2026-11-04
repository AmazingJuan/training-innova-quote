<?php

namespace App\Mail;

use App\Models\QuotationProposalOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotationGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public QuotationProposalOrder $order;

    /**
     * Create a new message instance.
     *
     * @param  array  $mailData
     */
    public function __construct(QuotationProposalOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $ref = $this->order->getQuotationCode() ?? $this->order->getId();
        $subject = 'Su cotización '.$ref.' está lista';

        return $this
            ->subject($subject)
            ->view('emails.quotation-generated');
    }
}
