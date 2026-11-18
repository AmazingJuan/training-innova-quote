<?php

namespace App\Actions;

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use App\Services\MailService;
use App\Support\OfferDatePolicy;
use App\Support\QuotationAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Stores a quotation request sent from the public form and notifies the
 * client and management. The attached documents are not kept: they only wait
 * on disk for the notice to management (see QuotationAttachment).
 */
class SubmitQuotationRequest
{
    public function __construct(
        private readonly MailService $mail,
        private readonly OfferDatePolicy $offerDates,
    ) {}

    /**
     * @param  array<string, string>  $contact
     * @param  array<string, string>  $answers  keyed by question, as the client read it
     * @param  list<array{question: string, file: UploadedFile}>  $documents
     */
    public function handle(BusinessUnit $unit, array $contact, array $answers, array $documents = []): QuotationProposalOrder
    {
        $offerDateLabel = $unit->offerDateQuestion()?->getLabel();
        $offerDate = $offerDateLabel !== null && isset($answers[$offerDateLabel])
            ? $this->offerDates->parse($answers[$offerDateLabel])
            : null;

        $order = DB::transaction(fn () => QuotationProposalOrder::create([
            'quotation_code' => $unit->lockAndAllocateNextQuotationCode(),
            'client_id' => Client::upsertFromContact($contact)->getId(),
            'business_unit_id' => $unit->getId(),
            'answers' => $answers,
            'offer_date' => $offerDate,
        ]));

        $this->mail->sendQuotationPending($order, QuotationAttachment::keep($documents));

        return $order;
    }
}
