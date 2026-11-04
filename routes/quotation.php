<?php

use App\Http\Controllers\Quotation\QuotationController;
use Illuminate\Support\Facades\Route;

Route::prefix('cotizar')->group(function () {

    Route::get('/', [QuotationController::class, 'selectBusinessUnit'])->name('quotation.select.business_unit');
    Route::post('/', [QuotationController::class, 'storeQuotationProposal'])->name('quotation.store.proposal');
    Route::get('/{businessUnitName}', [QuotationController::class, 'getQuoteForBusinessUnit'])->name('quotation.quote.business_unit');
});

Route::get('/recibir-cotizacion/{quotationId}', [QuotationController::class, 'receiveQuotation'])->name('quotation.receive');
