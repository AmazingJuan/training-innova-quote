<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QuotationProposalOrder;
use Illuminate\Http\JsonResponse;

class QuotationOrderApiController extends Controller
{
    public function index(): JsonResponse
    {
        $requiredColumns = ['id', 'quotation_code', 'client_id', 'business_unit', 'gestion_line', 'services', 'answers'];
        $quotationOrders = QuotationProposalOrder::with('client')->select($requiredColumns)->get();
        if ($quotationOrders->isEmpty()) {
            return response()->json(['message' => 'No quotation orders found',
                'status' => 'error',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Quotation orders fetched successfully',
            'status' => 'success',
            'data' => $quotationOrders,
        ], 200);
    }
}
