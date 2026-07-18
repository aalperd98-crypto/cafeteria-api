<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EntryValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanEntryRequest;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;

class EntryController extends Controller
{
    public function __construct(private AccessControlService $accessControlService) {}

    public function scan(ScanEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $accessLog = $this->accessControlService->scanEntry(
                $request->user(),
                (int) $validated['turnstile_id'],
                $validated['qr_code'],
            );
        } catch (EntryValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->statusCode());
        }

        return response()->json([
            'message' => 'Entry granted. Turnstile opening.',
            'access_log' => [
                'id' => $accessLog->id,
                'user_id' => $accessLog->user_id,
                'turnstile_id' => $accessLog->turnstile_id,
                'scanned_at' => $accessLog->scanned_at,
            ],
        ], 201);
    }
}
