<?php

namespace App\Livewire\Concerns;

/**
 * Click-to-sort for the F2 item browse popup (same column headers as Items list).
 */
trait SortsItemBrowse
{
    public string $browseSortField = 'quantity_in_stock';

    public string $browseSortDir = 'desc';

    /** @return array<string, string> */
    protected function browseSortColumns(): array
    {
        return [
            'item_code' => 'item_code',
            'description' => 'description',
            'unit_of_measure' => 'unit_of_measure',
            'list_price' => 'list_price',
            'available' => 'available',
            'quantity_in_stock' => 'quantity_in_stock',
        ];
    }

    public function sortBrowseBy(string $field): void
    {
        if (! isset($this->browseSortColumns()[$field])) {
            return;
        }

        if ($this->browseSortField === $field) {
            $this->browseSortDir = $this->browseSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->browseSortField = $field;
            $this->browseSortDir = 'asc';
        }

        if ($this->showBrowse ?? false) {
            $this->resetBrowseAndLoadFirstPage();
        }
    }

    protected function applyBrowseOrder($query)
    {
        $dir = strtolower($this->browseSortDir) === 'desc' ? 'desc' : 'asc';
        $field = $this->browseSortField;
        if (! isset($this->browseSortColumns()[$field])) {
            $field = 'quantity_in_stock';
        }

        if (! empty($this->browseNewOnly) && $field === 'quantity_in_stock') {
            return $query->orderByDesc('created_at')->orderBy('item_code');
        }

        return match ($field) {
            'item_code' => $query->orderBy('item_code', $dir)->orderBy('id', $dir),
            'description' => $query->orderBy('description', $dir)->orderBy('item_code'),
            'unit_of_measure' => $query->orderBy('unit_of_measure', $dir)->orderBy('item_code'),
            'list_price' => $query->orderBy('list_price', $dir)->orderBy('item_code'),
            'available' => $query->orderByRaw('(quantity_in_stock - allocated_qty) '.$dir)->orderBy('item_code'),
            default => $query->orderBy('quantity_in_stock', $dir)->orderBy('item_code'),
        };
    }

    /**
     * Resolve display U/M: items.unit_of_measure, else first non-empty item_prices.uom.
     *
     * @param  iterable<int, object>  $rows
     * @return array<int, string> item_id => uom
     */
    protected function browseUomsForRows(iterable $rows): array
    {
        $needIds = [];
        $resolved = [];

        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $fromItem = trim((string) ($row->unit_of_measure ?? ''));
            if ($fromItem !== '') {
                $resolved[$id] = $fromItem;
            } else {
                $needIds[] = $id;
            }
        }

        if ($needIds === []) {
            return $resolved;
        }

        $fromPrices = \Illuminate\Support\Facades\DB::table('item_prices')
            ->whereIn('item_id', array_values(array_unique($needIds)))
            ->whereNotNull('uom')
            ->where('uom', '!=', '')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['item_id', 'uom']);

        foreach ($fromPrices as $price) {
            $id = (int) $price->item_id;
            if (! isset($resolved[$id])) {
                $u = trim((string) $price->uom);
                if ($u !== '') {
                    $resolved[$id] = $u;
                }
            }
        }

        return $resolved;
    }
}
