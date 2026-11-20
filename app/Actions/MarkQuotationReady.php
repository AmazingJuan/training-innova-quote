<?php

namespace App\Actions;

use App\Models\QuotationProposalOrder;
use App\Services\MailService;

/**
 * Management uploaded the quotation: the request stops being pending (no more
 * reminders) and the client receives the link.
 */
class MarkQuotationReady
{
    public function __construct(private readonly MailService $mail) {}

    public function handle(QuotationProposalOrder $order, string $quotationUrl): void
    {
        $order->setQuotationUrl($quotationUrl);
        $order->setIsGenerated(true);
        // A new document: the client has not seen this one yet.
        $order->setViewedByClient(false);
        $order->save();

        $this->mail->sendQuotationGenerated($order);
    }
}
