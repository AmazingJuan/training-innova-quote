<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateQuotationUrlRequest;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use App\Services\MailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuotationOrderController extends Controller
{
    public function __construct(private readonly MailService $mail) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $quotationOrders = QuotationProposalOrder::query()
            ->select('id', 'quotation_code', 'client_id', 'business_unit', 'is_generated', 'viewed_by_client', 'quotation_url', 'created_at')
            ->with('client:id,name,email,company,role')
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('quotation_code', 'like', $like)
                    ->orWhere('business_unit', 'like', $like)
                    ->orWhereHas('client', fn (Builder $client) => $client->search($search, Client::SEARCHABLE_COLUMNS)));
            })
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/quotation-orders/index', [
            'viewData' => [
                'quotationOrders' => $quotationOrders,
                'filters' => ['search' => $search],
            ],
        ]);
    }

    public function show(QuotationProposalOrder $quotationOrder): Response
    {
        return Inertia::render('admin/quotation-orders/show', [
            'viewData' => [
                'quotationOrder' => $quotationOrder->load('client'),
            ],
        ]);
    }

    public function uploadQuotationUrl(AdminUpdateQuotationUrlRequest $request, QuotationProposalOrder $quotationOrder): RedirectResponse
    {
        $quotationOrder->setQuotationUrl($request->validated('quotation_url'));
        $quotationOrder->setIsGenerated(true);
        $quotationOrder->setViewedByClient(false);
        $quotationOrder->save();

        $this->mail->sendQuotationGenerated($quotationOrder);

        return to_route('dashboard.quotation-orders.show', $quotationOrder)->with('success', 'URL de cotización subida exitosamente.');
    }
}
