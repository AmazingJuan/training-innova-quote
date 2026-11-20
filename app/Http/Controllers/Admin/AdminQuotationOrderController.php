<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MarkQuotationReady;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateQuotationUrlRequest;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuotationOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $quotationOrders = QuotationProposalOrder::query()
            ->select('id', 'quotation_code', 'client_id', 'business_unit_id', 'is_generated', 'viewed_by_client', 'quotation_url', 'created_at')
            ->with(['client:id,name,email,company,role', 'businessUnit:id,display_name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where(fn (Builder $code) => Client::whereContains($code, 'quotation_code', $search))
                ->orWhereHas('businessUnit', fn (Builder $unit) => Client::whereContains($unit, 'display_name', $search))
                ->orWhereHas('client', fn (Builder $client) => $client->search($search, Client::SEARCHABLE_COLUMNS))))
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
                'quotationOrder' => $quotationOrder->load(['client', 'businessUnit:id,display_name']),
            ],
        ]);
    }

    public function uploadQuotationUrl(AdminUpdateQuotationUrlRequest $request, QuotationProposalOrder $quotationOrder, MarkQuotationReady $markReady): RedirectResponse
    {
        $markReady->handle($quotationOrder, $request->validated('quotation_url'));

        return to_route('dashboard.quotation-orders.show', $quotationOrder)->with('success', 'URL de cotización subida exitosamente.');
    }
}
