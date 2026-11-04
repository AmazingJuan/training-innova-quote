<?php

use App\Api\Controllers\QuotationOrderApiController;
use App\Api\Controllers\UserApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [UserApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('/quotations', QuotationOrderApiController::class)->only(['index']);
});
