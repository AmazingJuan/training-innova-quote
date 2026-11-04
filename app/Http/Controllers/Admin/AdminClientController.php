<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateClientsExport;
use App\Models\Client;
use App\Models\QuotationProposalOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminClientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);

        $clients = Client::query()
            ->withClientStats()
            ->applyClientFilters($filters)
            ->orderByActivity()
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Client $client) => [
                ...$this->contactData($client),
                'last_quotation_at' => $this->isoDate($client->getAttribute('last_quotation_at')),
            ]);

        return Inertia::render('admin/clients/index', [
            'viewData' => [
                'clients' => $clients,
                'filters' => $filters,
                'options' => $this->filterOptions(),
            ],
        ]);
    }

    public function show(Client $client): Response
    {
        $client->loadCount(Client::statsCounts());

        $orders = $client->quotationProposalOrders()->latest()->get();

        return Inertia::render('admin/clients/show', [
            'viewData' => [
                'client' => [
                    ...$this->contactData($client),
                    'first_quotation_at' => $orders->last()?->getCreatedAt()?->toIso8601String(),
                    'last_quotation_at' => $orders->first()?->getCreatedAt()?->toIso8601String(),
                ],
                'quotations' => $orders->map(fn (QuotationProposalOrder $order) => [
                    'id' => $order->getId(),
                    'quotation_code' => $order->getQuotationCode(),
                    'business_unit' => $order->getBusinessUnit(),
                    'is_generated' => $order->getIsGenerated(),
                    'quotation_url' => $order->getQuotationUrl(),
                    'created_at' => $order->getCreatedAt()?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Queue the Excel generation and return a token the frontend can poll.
     */
    public function startExport(Request $request): JsonResponse
    {
        $token = (string) Str::uuid();

        GenerateClientsExport::markStatus($token, GenerateClientsExport::STATUS_PENDING);
        GenerateClientsExport::dispatch($token, $this->resolveFilters($request));

        return response()->json(['token' => $token]);
    }

    public function exportStatus(string $token): JsonResponse
    {
        return response()->json([
            'status' => Cache::get(GenerateClientsExport::statusKey($token), 'unknown'),
        ]);
    }

    /**
     * Single-use download: the file is removed once it has been sent.
     */
    public function downloadExport(string $token): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        $path = GenerateClientsExport::relativePath($token);

        abort_unless(
            Cache::get(GenerateClientsExport::statusKey($token)) === GenerateClientsExport::STATUS_READY && $disk->exists($path),
            404,
        );

        Cache::forget(GenerateClientsExport::statusKey($token));

        return response()
            ->download($disk->path($path), 'clientes_'.now()->format('d-m-Y_h-iA').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return array{search:string, company:string, business_unit:string}
     */
    private function resolveFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->trim()->toString(),
            'company' => $request->string('company')->trim()->toString(),
            'business_unit' => $request->string('business_unit')->trim()->toString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactData(Client $client): array
    {
        return [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'email' => $client->getEmail(),
            'company' => $client->getCompany(),
            'phone' => $client->getPhone(),
            'role' => $client->getRole(),
            'nit' => $client->getNit(),
            'economic_activity' => $client->getEconomicActivity(),
            'address' => $client->getAddress(),
            'quotations_count' => (int) $client->getAttribute('quotations_count'),
            'generated_count' => (int) $client->getAttribute('generated_count'),
            'pending_count' => (int) $client->getAttribute('pending_count'),
        ];
    }

    private function isoDate(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }

    /**
     * @return array{companies: list<string>, businessUnits: list<string>}
     */
    private function filterOptions(): array
    {
        return [
            'companies' => Client::query()
                ->whereNotNull('company')
                ->where('company', '!=', '')
                ->distinct()
                ->orderBy('company')
                ->pluck('company')
                ->all(),
            'businessUnits' => QuotationProposalOrder::query()
                ->where('business_unit', '!=', '')
                ->distinct()
                ->orderBy('business_unit')
                ->pluck('business_unit')
                ->all(),
        ];
    }
}
