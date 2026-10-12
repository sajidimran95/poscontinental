<?php

use App\Models\PurchaseOrder;
use App\Services\InventoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(InventoryService::class);
        $fixed = [];

        PurchaseOrder::query()
            ->whereIn('status', ['New', 'Partially Received'])
            ->select(['id', 'po_number', 'status'])
            ->chunkById(500, function ($pos) use ($service, &$fixed) {
                $statuses = $service->receivingStatusByPo($pos->pluck('id')->all());
                foreach ($pos as $po) {
                    $target = $statuses[$po->id] ?? 'New';
                    $upgrade = $target === 'Received'
                        || ($target === 'Partially Received' && $po->status === 'New');
                    if ($upgrade && $target !== $po->status) {
                        PurchaseOrder::query()->whereKey($po->id)->update(['status' => $target]);
                        $fixed[] = $po->po_number.' → '.$target;
                    }
                }
            });

        Log::info('Repaired purchase order receiving status', ['count' => count($fixed), 'pos' => $fixed]);
    }

    public function down(): void
    {
        //
    }
};
