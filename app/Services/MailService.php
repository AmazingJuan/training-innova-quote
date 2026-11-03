<?php

namespace App\Services;

use App\Jobs\NotifyManagementOfQuotation;
use App\Mail\QuotationGenerated;
use App\Mail\QuotationGeneratedAdmin;
use App\Mail\QuotationPending;
use App\Mail\WelcomeEmail;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Queues every outgoing e-mail. Each quotation event notifies the client
 * (when it has an address) and the admin notification inbox (when configured).
 */
class MailService
{
    /**
     * @param  list<array{path: string, name: string, mime: string|null}>  $files
     *                                                                             Documents attached by the client, which travel in the notice to management.
     */
    public function sendQuotationPending(QuotationProposalOrder $order, array $files = []): void
    {
        $this->notifyClient($order, new QuotationPending($order));
        // A job of its own: it carries the attachments and deletes them once sent.
        NotifyManagementOfQuotation::dispatch($order->getId(), $files);
    }

    public function sendQuotationGenerated(QuotationProposalOrder $order): void
    {
        $this->notifyClient($order, new QuotationGenerated($order));
        $this->notifyAdmin(new QuotationGeneratedAdmin($order));
    }

    public function sendWelcome(string $email): void
    {
        Mail::to($email)->queue(new WelcomeEmail);
    }

    private function notifyClient(QuotationProposalOrder $order, Mailable $mail): void
    {
        $email = $order->loadMissing('client')->getClient()?->getEmail();

        if ($email) {
            Mail::to($email)->queue($mail);
        }
    }

    private function notifyAdmin(Mailable $mail): void
    {
        $email = Configuration::current()?->getNotificationEmail();

        if ($email) {
            Mail::to($email)->queue($mail);
        }
    }
}
