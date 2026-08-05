<?php

namespace App\Http\Resources\Rep;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesOrder */
class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'order_date' => optional($this->order_date)?->toDateString(),
            'required_date' => optional($this->required_date)?->toDateString(),
            'customer_po_no' => $this->customer_po_no,
            'reference_no' => $this->reference_no,
            'comments' => $this->comments,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'customer_id' => $this->customer->customer_id,
                'company_name' => $this->customer->company_name,
            ] : null),
            'sales_rep_id' => $this->sales_rep_id,
            'sales_rep' => $this->whenLoaded('salesRep', fn () => $this->salesRep ? [
                'id' => $this->salesRep->id,
                'name' => $this->salesRep->name,
            ] : null),
            'subtotal' => (float) $this->subtotal,
            'trade_discount' => (float) $this->trade_discount,
            'freight' => (float) $this->freight,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'line_no' => $line->line_no,
                'item_id' => $line->item_id,
                'item_code' => $line->item_code,
                'description' => $line->description,
                'uom' => $line->uom,
                'qty_ordered' => (float) $line->qty_ordered,
                'qty_shipped' => (float) $line->qty_shipped,
                'price' => (float) $line->price,
                'discount' => (float) $line->discount,
                'line_total' => (float) $line->line_total,
                'line_message' => $line->line_message,
            ])->values()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
