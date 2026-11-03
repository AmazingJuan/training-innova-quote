<?php

namespace App\Http\Controllers\Quotation;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuotationProposal;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use App\Services\DecisionTreeService;
use App\Services\MailService;
use App\Support\QuotationAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class QuotationController extends Controller
{
    public function __construct(
        private readonly DecisionTreeService $trees,
        private readonly MailService $mail,
    ) {}

    public function selectBusinessUnit(): InertiaResponse
    {
        return Inertia::render('business-units/select', [
            'viewData' => ['businessUnits' => $this->quotableUnits()],
        ]);
    }

    public function getQuoteForBusinessUnit(string $businessUnitName): InertiaResponse|RedirectResponse
    {
        $businessUnit = BusinessUnit::query()->where('name', $businessUnitName)->first();

        if (! $businessUnit) {
            return $this->backToSelection('No hay ninguna unidad de negocio con ese nombre.');
        }

        $conditions = $this->trees->reachableTree($businessUnit);

        if ($conditions === []) {
            return $this->backToSelection('No existen condiciones definidas para la unidad de negocio seleccionada.');
        }

        return Inertia::render('quotation/index', [
            'viewData' => [
                'businessUnit' => $businessUnit->getDisplayName(),
                'unit' => $businessUnit->getName(),
                // The other units, to switch between them as in the corporate site's service explorer.
                'units' => $this->quotableUnits(),
                'conditions' => $conditions,
                'initial_condition_id' => $businessUnit->initial_condition_id,
            ],
        ]);
    }

    public function storeQuotationProposal(StoreQuotationProposal $request): RedirectResponse
    {
        $data = $request->validated();
        $sent = fn (?string $code) => back()->with('quotation_sent', ['code' => $code, 'email' => $data['contact']['email']]);

        // Honeypot: only bots fill the hidden field. They get the usual answer, but nothing is stored.
        if (filled($data['web'] ?? null)) {
            return $sent(null);
        }

        $order = DB::transaction(function () use ($data) {
            $businessUnit = BusinessUnit::query()->where('display_name', $data['businessUnit'])->firstOrFail();

            return QuotationProposalOrder::create([
                'quotation_code' => $businessUnit->lockAndAllocateNextQuotationCode(),
                'client_id' => Client::upsertFromContact($data['contact'])->getId(),
                // The client no longer picks services: the request is the encuadre (answers).
                'services' => [],
                'business_unit' => $data['businessUnit'],
                'answers' => $data['answers'],
            ]);
        });

        // The attached documents are not kept: they only wait for the notice to management.
        $this->mail->sendQuotationPending($order, QuotationAttachment::keep($data['documents'] ?? []));

        // The page itself confirms it (with the request's code), as the corporate contact form does.
        return $sent($order->getQuotationCode());
    }

    /**
     * Link sent by e-mail: marks the quotation as seen and forwards the client
     * to the document.
     */
    public function receiveQuotation(Request $request, string $quotationId): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return to_route('home')->with('error', 'La URL de la cotización ha expirado o no es válida.');
        }

        $quotation = QuotationProposalOrder::find($quotationId);

        if (! $quotation?->getQuotationUrl()) {
            return to_route('home')->with('error', 'La cotización aún no tiene un documento disponible.');
        }

        if (! $quotation->getViewedByClient()) {
            $quotation->setViewedByClient(true);
            $quotation->save();
        }

        return redirect()->away($quotation->getQuotationUrl());
    }

    /**
     * Units with a questionnaire, the only ones that can be quoted.
     *
     * @return list<array{name: string, display_name: string}>
     */
    private function quotableUnits(): array
    {
        return BusinessUnit::query()
            ->whereNotNull('initial_condition_id')
            ->orderBy('display_name')
            ->get(['id', 'name', 'display_name'])
            ->map(fn (BusinessUnit $unit) => ['name' => $unit->getName(), 'display_name' => $unit->getDisplayName()])
            ->all();
    }

    private function backToSelection(string $error): RedirectResponse
    {
        return to_route('quotation.select.business_unit')->with('error', $error);
    }
}
