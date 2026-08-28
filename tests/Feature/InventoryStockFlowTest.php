<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryReceiving;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\ReturnToVendor;
use App\Models\SalesOrder;
use App\Models\StockCount;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockFlowTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    private Company $company;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
        $this->company = Company::query()->create([
            'code' => 'TST',
            'name' => 'Test Co',
            'is_active' => true,
            'allow_negative_stock' => true,
        ]);
        $this->item = Item::query()->create([
            'company_id' => $this->company->id,
            'item_code' => 'SKU-1',
            'description' => 'Test item',
            'quantity_in_stock' => 100,
            'allocated_qty' => 0,
            'on_order_qty' => 0,
            'current_cost' => 2,
        ]);
    }

    public function test_full_stock_flow_item_po_receive_sales_invoice_rtv_count(): void
    {
        $item = $this->freshItem();
        $this->assertStock($item, 100, 0, 100, 0);

        $po = PurchaseOrder::query()->create([
            'company_id' => $this->company->id,
            'po_number' => 'PO-1',
            'status' => 'New',
        ]);
        $poLine = $po->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 40,
            'qty_received' => 0,
            'unit_cost' => 2,
            'extended_cost' => 80,
            'line_no' => 1,
        ]);
        $this->inventory->syncOnOrderQty([$item->id]);
        $item = $this->freshItem();
        $this->assertStock($item, 100, 0, 100, 40, 'PO save: on order only');

        $receiving = InventoryReceiving::query()->create([
            'company_id' => $this->company->id,
            'receipt_number' => 'RCV-1',
            'receipt_date' => now()->toDateString(),
            'purchase_order_id' => $po->id,
            'status' => 'New',
        ]);
        $receiving->lines()->create([
            'purchase_order_line_id' => $poLine->id,
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 40,
            'qty_received' => 40,
            'unit_cost' => 2,
            'line_no' => 1,
        ]);
        $this->inventory->processReceiving($receiving->fresh('lines'));
        $item = $this->freshItem();
        $this->assertStock($item, 140, 0, 140, 0, 'PO receive: stock +40, on order 0');

        $order = SalesOrder::query()->create([
            'company_id' => $this->company->id,
            'order_number' => 'SO-1',
            'status' => 'New',
            'order_type' => 'Sales Order',
        ]);
        $order->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 10,
            'qty_shipped' => 0,
            'price' => 5,
            'line_total' => 50,
            'line_no' => 1,
        ]);
        $this->inventory->syncAllocatedQty([$item->id]);
        $item = $this->freshItem();
        $this->assertStock($item, 140, 10, 130, 0, 'SO add: available -10, stock unchanged');

        $order->lines()->delete();
        $this->inventory->applyOrderQtyRevision($order->fresh('lines'), [(int) $item->id => 10], null);
        $item = $this->freshItem();
        $this->assertStock($item, 140, 0, 140, 0, 'SO remove (no invoice): available +10, stock unchanged');

        $order->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 10,
            'qty_shipped' => 0,
            'price' => 5,
            'line_total' => 50,
            'line_no' => 1,
        ]);
        $this->inventory->syncAllocatedQty([$item->id]);
        $item = $this->freshItem();
        $this->assertStock($item, 140, 10, 130, 0, 'SO add again before invoice');

        $invoice = Invoice::query()->create([
            'company_id' => $this->company->id,
            'invoice_number' => 'INV-1',
            'invoice_date' => now()->toDateString(),
            'sales_order_id' => $order->id,
            'status' => 'NOT PAID',
            'invoice_total' => 50,
        ]);
        $this->inventory->applyInvoiceStock($order->fresh('lines'), $invoice);
        $item = $this->freshItem();
        $this->assertStock($item, 130, 0, 130, 0, 'Invoice: stock -10, available not reduced again');

        $order->lines()->delete();
        $this->inventory->applyOrderQtyRevision($order->fresh('lines'), [(int) $item->id => 10], $invoice);
        $item = $this->freshItem();
        $this->assertStock($item, 140, 0, 140, 0, 'Invoice remove: stock and available +10');

        $order->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 5,
            'qty_shipped' => 5,
            'price' => 5,
            'line_total' => 25,
            'line_no' => 1,
        ]);
        $this->inventory->applyOrderQtyRevision($order->fresh('lines'), [], $invoice);
        $item = $this->freshItem();
        $this->assertStock($item, 135, 0, 135, 0, 'Invoice add: stock and available -5');

        $rtv = ReturnToVendor::query()->create([
            'company_id' => $this->company->id,
            'rtv_number' => 'RTV-1',
            'rtv_date' => now()->toDateString(),
            'status' => 'New',
        ]);
        $rtv->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty' => 10,
            'unit_cost' => 2,
            'extended_cost' => 20,
            'line_no' => 1,
        ]);
        $this->inventory->processRtv($rtv->fresh('lines'));
        $item = $this->freshItem();
        $this->assertStock($item, 125, 0, 125, 0, 'RTV: stock and available -10');

        $count = StockCount::query()->create([
            'company_id' => $this->company->id,
            'stock_count_no' => 'SC-1',
            'status' => 'New',
        ]);
        $count->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'in_stock' => 125,
            'allocated' => 0,
            'counted' => 200,
            'line_no' => 1,
        ]);
        $this->inventory->processStockCount($count->fresh('lines'));
        $item = $this->freshItem();
        $this->assertStock($item, 200, 0, 200, 0, 'Stock count: in stock set to counted');
    }

    public function test_po_remove_line_clears_on_order(): void
    {
        $item = $this->freshItem();
        $po = PurchaseOrder::query()->create([
            'company_id' => $this->company->id,
            'po_number' => 'PO-2',
            'status' => 'New',
        ]);
        $po->lines()->create([
            'item_id' => $item->id,
            'item_code' => $item->item_code,
            'qty_ordered' => 15,
            'qty_received' => 0,
            'line_no' => 1,
        ]);
        $this->inventory->syncOnOrderQty([$item->id]);
        $this->assertEquals(15.0, (float) $this->freshItem()->on_order_qty);

        $po->lines()->delete();
        $this->inventory->syncOnOrderQty([$item->id]);
        $this->assertEquals(0.0, (float) $this->freshItem()->on_order_qty);
    }

    private function freshItem(): Item
    {
        return $this->item->fresh();
    }

    private function assertStock(Item $item, float $stock, float $allocated, float $available, float $onOrder, string $message = ''): void
    {
        $this->assertEquals($stock, (float) $item->quantity_in_stock, $message.' in stock');
        $this->assertEquals($allocated, (float) $item->allocated_qty, $message.' allocated');
        $this->assertEquals($available, (float) $item->available_quantity, $message.' available');
        $this->assertEquals($onOrder, (float) $item->on_order_qty, $message.' on order');
    }
}
