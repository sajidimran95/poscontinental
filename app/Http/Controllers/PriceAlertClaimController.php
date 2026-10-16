<?php

namespace App\Http\Controllers;

use App\Services\ItemPriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceAlertClaimController extends Controller
{
    /** Sale / customer app: should this viewer see the price-change popup? (Only the first time.) */
    public function claim(Request $request, ItemPriceHistoryService $history): JsonResponse
    {
        $id = (int) $request->input('price_history_id');

        return response()->json([
            'show' => $history->claimAlert(ItemPriceHistoryService::viewerKey(), $id),
        ]);
    }
}
