<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turnstile;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;

class TurnstileController extends Controller
{
    public function __construct(private AccessControlService $accessControlService) {}

    public function qr(Turnstile $turnstile): JsonResponse
    {
        if (! $turnstile->isActive()) {
            return response()->json([
                'message' => 'This turnstile is not currently active.',
            ], 422);
        }

        $turnstile = $this->accessControlService->getOrRefreshQrCode($turnstile);

        return response()->json([
            'turnstile_id' => $turnstile->id,
            'qr_code' => $turnstile->current_qr_code,
            'expires_at' => $turnstile->qr_expires_at,
        ]);
    }
}
