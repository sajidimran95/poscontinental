<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebOrderNotifyController extends Controller
{
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user?->canAccessFeature('sales.orders', 'view')) {
            return response()->json(['latest_id' => 0]);
        }

        $base = SalesOrder::query()
            ->where('company_id', $user->company_id)
            ->whereIn('order_source', [SalesOrder::SOURCE_CUSTOMER, SalesOrder::SOURCE_ECOMMERCE])
            ->whereNotIn('status', ['Cancelled', 'Void']);

        $latest = (clone $base)
            ->with('customer:id,customer_id,company_name,contact')
            ->orderByDesc('id')
            ->first(['id', 'order_number', 'order_source', 'customer_id', 'bill_to_name', 'total']);

        if (! $latest) {
            return response()->json(['latest_id' => 0]);
        }

        $after = max(0, (int) $request->query('after', 0));
        $latestId = (int) $latest->id;
        $newCount = $after > 0 ? (int) (clone $base)->where('id', '>', $after)->count() : 0;
        $playSound = false;
        if ($after > 0 && $latestId > $after) {
            $playSound = Cache::add(
                'pos-sound-web-order:'.$user->company_id.':'.$latestId,
                (int) $user->id,
                now()->addHours(12)
            );
        }

        return response()->json([
            'latest_id' => $latestId,
            'play_sound' => $playSound,
            'new_count' => $newCount,
            'preview' => [
                'order_number' => (string) $latest->order_number,
                'source' => $latest->sourceLabel(),
                'customer' => (string) ($latest->customer?->company_name ?: $latest->bill_to_name ?: $latest->customer?->contact ?: ''),
                'total' => number_format((float) $latest->total, 2),
                'url' => route('sales.orders.edit', $latest->id, false),
            ],
        ]);
    }
}
