<?php

namespace App\Http\Controllers;

use App\Services\JapsAi\VendorInvoicePurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosUserSoundController extends Controller
{
    /**
     * Spoken alert for the user who did the work. Other users get no text.
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['fingerprint' => '', 'speak' => '']);
        }

        $companyId = (int) $user->company_id;
        $alerts = VendorInvoicePurchaseOrderService::adminAlerts($companyId);

        return response()->json([
            'fingerprint' => VendorInvoicePurchaseOrderService::adminAlertFingerprint($alerts),
            'speak' => VendorInvoicePurchaseOrderService::speakTextForUser($companyId, (int) $user->id, $alerts),
        ]);
    }
}
