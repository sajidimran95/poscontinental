<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ParkedSale;
use App\Models\User;
use App\Services\Rep\SalesRepScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleParkedSaleController extends Controller
{
    protected function user(): User
    {
        return auth('sale')->user();
    }

    public function index(): JsonResponse
    {
        $user = $this->user();
        abort_unless($user->isSalesRep() || $user->canAccessFeature('sales.orders', 'edit'), 403);

        $rows = ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        return response()->json($rows->map(fn (ParkedSale $p) => $this->present($p))->values());
    }

    public function show(ParkedSale $parkedSale): JsonResponse
    {
        $this->assertOwn($parkedSale);

        return response()->json($this->present($parkedSale, true));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->user();
        abort_unless($user->isSalesRep() || $user->canAccessFeature('sales.orders', 'edit'), 403);

        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'customer_label' => ['nullable', 'string', 'max:191'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.variation_id' => ['required', 'integer'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.allow_decimal' => ['nullable'],
            'location_id' => ['nullable', 'integer'],
            'shipping' => ['nullable', 'array'],
        ]);

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);
        SalesRepScope::assertCompanyCustomer($user, $customer);

        $existing = ParkedSale::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->count();
        if ($existing >= 40) {
            return response()->json(['message' => 'Too many parked sales. Recall or discard one first.'], 422);
        }

        $lines = array_values($data['lines']);
        $total = 0.0;
        foreach ($lines as $line) {
            $total += ((float) $line['quantity']) * ((float) $line['unit_price']);
        }

        $label = trim((string) ($data['customer_label'] ?? ''));
        if ($label === '') {
            $label = $customer->company_name ?: $customer->contact ?: ('Customer #'.$customer->id);
        }

        $parked = ParkedSale::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'customer_label' => $label,
            'line_count' => count($lines),
            'total' => round($total, 4),
            'payload' => [
                'customer_id' => $customer->id,
                'customer_label' => $label,
                'location_id' => $data['location_id'] ?? null,
                'lines' => $lines,
                'shipping' => $data['shipping'] ?? [],
            ],
        ]);

        return response()->json($this->present($parked), 201);
    }

    public function destroy(ParkedSale $parkedSale): JsonResponse
    {
        $this->assertOwn($parkedSale);
        $parkedSale->delete();

        return response()->json(['ok' => true]);
    }

    protected function assertOwn(ParkedSale $parkedSale): void
    {
        $user = $this->user();
        abort_unless(
            (int) $parkedSale->company_id === (int) $user->company_id
            && (int) $parkedSale->user_id === (int) $user->id,
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(ParkedSale $p, bool $withPayload = false): array
    {
        $row = [
            'id' => (int) $p->id,
            'customer_id' => $p->customer_id ? (int) $p->customer_id : null,
            'customer_label' => $p->customer_label,
            'line_count' => (int) $p->line_count,
            'total' => (float) $p->total,
            'updated_at' => optional($p->updated_at)->toIso8601String(),
        ];
        if ($withPayload) {
            $row['payload'] = $p->payload ?? [];
        }

        return $row;
    }
}
