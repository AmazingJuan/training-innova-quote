<?php

namespace App\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;

class UserApiController extends Controller
{
    public function login(LoginRequest $request, ApiTokenService $tokens): JsonResponse
    {
        $user = $request->authenticate();

        return response()->json([
            'message' => 'Login successful',
            'status' => 'success',
            'data' => [
                'token' => $tokens->getOrCreateToken($user, $request->deviceName()),
            ],
        ], 200);
    }
}
