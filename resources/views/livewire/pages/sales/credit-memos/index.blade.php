<?php

use App\Livewire\Concerns\BrowsesItemsForDocument;
use App\Livewire\Concerns\SortsDeskList;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UomSchedule;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app'), Title('Credit Memos')] class extends Component
{
    use WithPagination;
    use SortsDeskList;
    use BrowsesItemsForDocument {
        openItemBrowse as openDocumentItemBrowse;
        closeItemBrowse as closeDocumentItemBrowse;
    }

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public string $favorite = 'all';

    public ?int $selectedId = null;

    public bool $showForm = false;

    public string $memo_number = '';

    public string $memo_date = '';

    public ?int $customer_id = null;

    public ?int $sales_order_id = null;

    public string $comments = '';

    public string $reference_no = '';

    public string $reason = '';

    public bool $restock_inventory = true;

    /** Flat credit amount when no item lines are used. */
    public string $credit_amount = '';

    /** @var array<int, array{item_id:?int,item_code:string,description:string,uom:string,qty:string,price:string}> */
    public array $lines = [];

    public string $itemLookup = '';

    public bool $scanModeActive = false;

    public string $lookupMessage = '';

    public ?int $emailMemoId = null;

    public string $emailTo = '';

    public string $emailSubject = '';

    public bool $showCustomerBrowse = false;

    public string $customerSearch = '';

    public bool $showOrderBrowse = false;

    public string $orderBrowseSearch = '';

    /** Order-line picker (optional: items from selected invoice/order). */
    public bool $showOrderItemBrowse = false;

    public string $itemBrowseSearch = '';

    public ?int $itemBrowseLineIndex = null;

    public function with(): array
    {
        $companyId = auth()->user()->company_id;

        $query = CreditMemo::query()
            ->with(['customer', 'salesOrder.invoice'])
            ->withSum('applications as applied_sum', 'amount')
            ->where('company_id', $companyId)
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('memo_number', 'like', $term)
                        ->orWhere('reference_no', 'like', $term)
                        ->orWhere('reason', 'like', $term)
                        ->orWhere('comments', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('company_name', 'like', $term)
                            ->orWhere('customer_id', 'like', $term))
                        ->orWhereHas('salesOrder', function ($o) use ($term) {
                            $o->where('order_number', 'like', $term)
                                ->orWhere('customer_po_no', 'like', $term)
                                ->orWhereHas('invoice', fn ($inv) => $inv->where('invoice_number', 'like', $term));
                        });
                });
            });

        if (in_array($this->statusFilter, ['Open', 'Applied'], true)) {
            $query->where('status', $this->statusFilter);
        } elseif ($this->favorite === 'open') {
            $query->where('status', 'Open');
        } elseif ($this->favorite === 'applied') {
            $query->where('status', 'Applied');
        }

        $query = $this->applyDeskSort($query);

        $selectedOrder = null;
        if ($this->sales_order_id) {
            $selectedOrder = SalesOrder::query()
                ->with('invoice:id,sales_order_id,invoice_number,status')
                ->where('company_id', $companyId)
                ->find($this->sales_order_id);
        }

        $lineTotal = collect($this->lines)
            ->filter(fn ($l) => trim((string) ($l['item_code'] ?? '')) !== '')
            ->sum(function ($l) {
                return ((float) ($l['qty'] ?? 0)) * ((float) ($l['price'] ?? 0));
            });

        $data = [
            'memos' => $query->paginate(50),
            'listTitle' => match (true) {
                $this->statusFilter === 'Open', $this->favorite === 'open' => 'Credit Memos (Open)',
                $this->statusFilter === 'Applied', $this->favorite === 'applied' => 'Credit Memos (Applied)',
                default => 'Credit Memos',
            },
            'selectedCustomer' => $this->customer_id
                ? Customer::query()->find($this->customer_id)
                : null,
            'selectedOrder' => $selectedOrder,
            'browseCustomers' => $this->showCustomerBrowse
                ? Customer::query()
                    ->where('company_id', $companyId)
                    ->where('is_inactive', false)
                    ->when(filled($this->customerSearch), function ($q) {
                        $term = '%'.$this->customerSearch.'%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('customer_id', 'like', $term)
                                ->orWhere('company_name', 'like', $term)
                                ->orWhere('contact', 'like', $term)
                                ->orWhere('telephone', 'like', $term);
                        });
                    })
                    ->orderBy('company_name')
                    ->limit(80)
                    ->get(['id', 'customer_id', 'company_name', 'contact', 'city', 'state', 'telephone'])
                : collect(),
            'browseOrders' => ($this->showOrderBrowse && $this->customer_id)
                ? SalesOrder::query()
                    ->with('invoice:id,sales_order_id,invoice_number,status')
                    ->where('company_id', $companyId)
                    ->where('customer_id', $this->customer_id)
                    ->when(filled($this->orderBrowseSearch), function ($q) {
                        $term = '%'.$this->orderBrowseSearch.'%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('order_number', 'like', $term)
                                ->orWhere('customer_po_no', 'like', $term)
                                ->orWhere('reference_no', 'like', $term)
                                ->orWhereHas('invoice', fn ($inv) => $inv->where('invoice_number', 'like', $term));
                        });
                    })
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(['id', 'order_number', 'order_date', 'customer_po_no', 'reference_no', 'status', 'total'])
                : collect(),
            'browseOrderLines' => ($this->showOrderItemBrowse && $this->sales_order_id)
                ? SalesOrderLine::query()
                    ->where('sales_order_id', $this->sales_order_id)
                    ->when(filled($this->itemBrowseSearch), function ($q) {
                        $term = '%'.$this->itemBrowseSearch.'%';
                        $q->where(function ($inner) use ($term) {
                            $inner->where('item_code', 'like', $term)
                                ->orWhere('description', 'like', $term);
                        });
                    })
                    ->orderBy('line_no')
                    ->get(['id', 'item_id', 'item_code', 'description', 'uom', 'qty_ordered', 'qty_shipped', 'price', 'line_total', 'line_no'])
                : collect(),
            'uomOptions' => $this->companyUomOptions($companyId),
            'favorites' => [
                'all' => 'All Credit Memos',
                'open' => 'Open',
                'applied' => 'Applied',
            ],
            'emailMemo' => $this->emailMemoId
                ? CreditMemo::query()->with('customer')->find($this->emailMemoId)
                : null,
            'lineTotal' => $lineTotal,
            'creditPreview' => $lineTotal > 0 ? $lineTotal : (float) str_replace(',', '', (string) $this->credit_amount),
            'browseItems' => collect($this->browseRows),
        ];

        return array_merge($this->documentBrowseViewData(), $data);
    }

    protected function deskSortMap(): array
    {
        return [
            'memo_number' => 'memo_number',
            'memo_date' => 'memo_date',
            'customer_code' => ['relation' => 'customer', 'column' => 'customer_id'],
            'customer_name' => ['relation' => 'customer', 'column' => 'company_name'],
            'order_number' => ['relation' => 'salesOrder', 'column' => 'order_number'],
            'invoice_number' => ['raw' => '(select invoice_number from invoices where invoices.sales_order_id = credit_memos.sales_order_id limit 1)'],
            'reason' => 'reason',
            'amount' => 'amount',
            'remaining' => ['raw' => '(credit_memos.amount - COALESCE(applied_sum, 0))'],
            'status' => 'status',
        ];
    }

    /** @return list<string> */
    protected function companyUomOptions(int $companyId): array
    {
        $fromSchedule = UomSchedule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $fromItems = Item::query()
            ->where('company_id', $companyId)
            ->whereNotNull('unit_of_measure')
            ->where('unit_of_measure', '!=', '')
            ->distinct()
            ->orderBy('unit_of_measure')
            ->pluck('unit_of_measure')
            ->all();

        $fromPrices = ItemPrice::query()
            ->whereHas('item', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('uom')
            ->where('uom', '!=', '')
            ->distinct()
            ->orderBy('uom')
            ->pluck('uom')
            ->all();

        $merged = array_values(array_unique(array_filter([
            ...$fromSchedule,
            ...$fromItems,
            ...$fromPrices,
            'EA', 'BX', 'CS', 'CTN', 'PK', 'RL',
        ])));
        natcasesort($merged);

        return array_values($merged);
    }

    /** @return list<string> */
    public function uomOptionsForLine(int $index, array $companyOptions): array
    {
        $options = $companyOptions;
        $code = trim((string) ($this->lines[$index]['item_code'] ?? ''));
        if ($code !== '') {
            $item = Item::query()
                ->with('prices')
                ->where('company_id', auth()->user()->company_id)
                ->where('item_code', $code)
                ->first();
            if ($item) {
                if (filled($item->unit_of_measure)) {
                    array_unshift($options, (string) $item->unit_of_measure);
                }
                foreach ($item->prices as $p) {
                    if (filled($p->uom)) {
                        $options[] = (string) $p->uom;
                    }
                }
            }
        }
        $current = trim((string) ($this->lines[$index]['uom'] ?? ''));
        if ($current !== '') {
            $options[] = $current;
        }
        $options = array_values(array_unique(array_filter($options)));
        natcasesort($options);

        return array_values($options);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedId = null;
    }

    public function updatedFavorite(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->statusFilter = match ($this->favorite) {
            'open' => 'Open',
            'applied' => 'Applied',
            default => '',
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->selectedId = null;
        $this->favorite = match ($this->statusFilter) {
            'Open' => 'open',
            'Applied' => 'applied',
            default => 'all',
        };
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;
    }

    public function refreshList(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function viewSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a credit memo first.');

            return;
        }

        $this->openMemoPdf($this->selectedId);
    }

    public function openMemoPdf(int $id): void
    {
        $memo = CreditMemo::query()->find($id);
        if (! $memo || $memo->company_id !== auth()->user()->company_id) {
            return;
        }

        $this->selectedId = $id;
        $this->js('window.open('.json_encode(route('sales.credit-memos.pdf', $memo)).', "_blank", "noopener")');
    }

    public function printSelected(): void
    {
        $this->viewSelected();
    }

    public function emailSelected(): void
    {
        if (! $this->selectedId) {
            session()->flash('status', 'Select a credit memo first.');

            return;
        }

        $this->openEmail($this->selectedId);
    }

    public function startNew(): void
    {
        $this->showForm = true;
        $this->selectedId = null;
        $this->resetErrorBag();
        $this->memo_number = CreditMemo::nextNumber(auth()->user()->company_id);
        $this->memo_date = now()->toDateString();
        $this->customer_id = null;
        $this->sales_order_id = null;
        $this->comments = '';
        $this->reference_no = '';
        $this->reason = '';
        $this->restock_inventory = true;
        $this->credit_amount = '';
        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->scanModeActive = false;
        $this->showCustomerBrowse = false;
        $this->showOrderBrowse = false;
        $this->showOrderItemBrowse = false;
        $this->closeDocumentItemBrowse();
        $this->lines = [
            $this->emptyCreditLine(),
        ];
    }

    public function mount(): void
    {
        $customerId = request()->integer('customer_id') ?: null;
        $openNew = request()->boolean('new');

        if ($openNew) {
            $this->startNew();
            if ($customerId) {
                $exists = Customer::query()
                    ->where('company_id', auth()->user()->company_id)
                    ->whereKey($customerId)
                    ->exists();
                if ($exists) {
                    $this->customer_id = $customerId;
                }
            }
        }
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->showCustomerBrowse = false;
        $this->showOrderBrowse = false;
        $this->showOrderItemBrowse = false;
        $this->closeDocumentItemBrowse();
        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->resetErrorBag();
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyCreditLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [
            $this->emptyCreditLine(),
        ];
    }

    public function lookupLineItem(int $index, ?string $code = null): void
    {
        if (! $this->sales_order_id) {
            return;
        }

        if ($code !== null) {
            $lines = $this->lines;
            $lines[$index]['item_code'] = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $code) ?? '');
            $this->lines = $lines;
        }

        $resolved = trim((string) ($this->lines[$index]['item_code'] ?? ''));
        if ($resolved === '') {
            return;
        }

        // Prefer exact order-line code; also allow UPC/alias -> item_id on this order.
        $orderLine = SalesOrderLine::query()
            ->where('sales_order_id', $this->sales_order_id)
            ->where(function ($q) use ($resolved) {
                $q->whereRaw('LOWER(item_code) = ?', [mb_strtolower($resolved)]);
            })
            ->orderBy('line_no')
            ->first();

        if (! $orderLine) {
            $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');
            if ($item) {
                $orderLine = SalesOrderLine::query()
                    ->where('sales_order_id', $this->sales_order_id)
                    ->where(function ($q) use ($item) {
                        $q->where('item_id', $item->id)
                            ->orWhereRaw('LOWER(item_code) = ?', [mb_strtolower((string) $item->item_code)]);
                    })
                    ->orderBy('line_no')
                    ->first();
            }
        }

        if (! $orderLine) {
            $this->lines[$index]['description'] = '';
            $this->lines[$index]['uom'] = '';
            $this->lines[$index]['price'] = '';

            return;
        }

        $this->lines[$index]['item_code'] = (string) ($orderLine->item_code ?? $resolved);
        $this->lines[$index]['description'] = (string) ($orderLine->description ?? '');
        $this->lines[$index]['uom'] = (string) ($orderLine->uom ?? '');
        $price = (string) $orderLine->price;
        $this->lines[$index]['price'] = ($price === '0' || $price === '0.0' || $price === '0.00') ? '' : $price;
        if (($this->lines[$index]['qty'] ?? '') === '' || (float) $this->lines[$index]['qty'] <= 0) {
            $this->lines[$index]['qty'] = '';
        }
    }

    public function lookupOrBrowseItem(int $index, ?string $code = null): void
    {
        if ($code !== null) {
            $lines = $this->lines;
            $lines[$index]['item_code'] = trim($code);
            $this->lines = $lines;
        }

        $resolved = trim((string) ($this->lines[$index]['item_code'] ?? ''));
        if ($resolved === '') {
            $this->openItemBrowse($index);

            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $resolved, 'any');
        if ($item) {
            $this->browseLineIndex = $index;
            $this->fillLineFromItem($index, $item);
            if (($this->lines[$index]['qty'] ?? '') === '' || (float) $this->lines[$index]['qty'] <= 0) {
                $this->lines[$index]['qty'] = '1';
            }
            $this->playPosSound('success');

            return;
        }

        if ($this->sales_order_id) {
            $this->lookupLineItem($index, $resolved);
            if (trim((string) ($this->lines[$index]['description'] ?? '')) !== '') {
                return;
            }
        }

        $this->openItemBrowse($index, $resolved);
    }

    public function updatedLines($value, string $key): void
    {
        if (! str_ends_with($key, '.uom')) {
            return;
        }
        $parts = explode('.', $key);
        $index = (int) ($parts[0] ?? -1);
        if ($index < 0 || ! isset($this->lines[$index])) {
            return;
        }
        $uom = trim((string) ($this->lines[$index]['uom'] ?? ''));
        $code = trim((string) ($this->lines[$index]['item_code'] ?? ''));
        if ($uom === '' || $code === '') {
            return;
        }
        $item = Item::query()
            ->with('prices')
            ->where('company_id', auth()->user()->company_id)
            ->where('item_code', $code)
            ->first();
        if (! $item) {
            return;
        }
        $match = $item->prices->first(fn ($p) => strcasecmp((string) $p->uom, $uom) === 0);
        if ($match) {
            $this->lines[$index]['price'] = (string) $match->price;
        }
    }

    public function openCustomerBrowse(): void
    {
        $this->showCustomerBrowse = true;
        $this->customerSearch = '';
        $this->showOrderBrowse = false;
        $this->showOrderItemBrowse = false;
    }

    public function closeCustomerBrowse(): void
    {
        $this->showCustomerBrowse = false;
    }

    public function pickCustomer(int $customerId): void
    {
        $exists = Customer::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereKey($customerId)
            ->exists();
        if (! $exists) {
            return;
        }
        $this->customer_id = $customerId;
        $this->sales_order_id = null;
        $this->lines = [
            $this->emptyCreditLine(),
        ];
        $this->showCustomerBrowse = false;
        $this->resetErrorBag('customer_id');
        $this->resetErrorBag('sales_order_id');
        $this->openOrderBrowse();
    }

    public function clearCustomer(): void
    {
        $this->customer_id = null;
        $this->sales_order_id = null;
        $this->showOrderBrowse = false;
        $this->showOrderItemBrowse = false;
        $this->lines = [
            $this->emptyCreditLine(),
        ];
    }

    public function openOrderBrowse(): void
    {
        if (! $this->customer_id) {
            $this->addError('customer_id', 'Select a customer first.');

            return;
        }
        $this->showOrderBrowse = true;
        $this->orderBrowseSearch = '';
        $this->showCustomerBrowse = false;
        $this->showOrderItemBrowse = false;
    }

    public function closeOrderBrowse(): void
    {
        $this->showOrderBrowse = false;
    }

    public function pickOrder(int $orderId): void
    {
        $order = SalesOrder::query()
            ->with('invoice:id,sales_order_id,invoice_number')
            ->where('company_id', auth()->user()->company_id)
            ->where('customer_id', $this->customer_id)
            ->find($orderId);
        if (! $order) {
            return;
        }

        $this->sales_order_id = $order->id;
        if (blank($this->reference_no)) {
            $this->reference_no = $order->invoice?->invoice_number
                ?: $order->order_number
                ?: '';
        }
        $this->lines = [
            $this->emptyCreditLine(),
        ];
        $this->showOrderBrowse = false;
        $this->resetErrorBag('sales_order_id');
    }

    public function clearOrder(): void
    {
        $this->sales_order_id = null;
        $this->showOrderItemBrowse = false;
        $this->lines = [
            $this->emptyCreditLine(),
        ];
    }

    public function openOrderItemBrowse(?int $index = null): void
    {
        if (! $this->sales_order_id) {
            $this->addError('sales_order_id', 'Select Order / Invoice No. first, then use Order Items.');

            return;
        }

        $this->itemBrowseLineIndex = $index ?? (count($this->lines) > 0 ? count($this->lines) - 1 : 0);
        if (! isset($this->lines[$this->itemBrowseLineIndex])) {
            $this->addLine();
            $this->itemBrowseLineIndex = count($this->lines) - 1;
        }
        $this->itemBrowseSearch = trim((string) ($this->lines[$this->itemBrowseLineIndex]['item_code'] ?? ''));
        $this->showOrderItemBrowse = true;
        $this->showCustomerBrowse = false;
        $this->showOrderBrowse = false;
        $this->resetErrorBag('sales_order_id');
    }

    public function closeOrderItemBrowse(): void
    {
        $this->showOrderItemBrowse = false;
        $this->itemBrowseLineIndex = null;
    }

    public function openItemBrowse(?int $lineIndex = null, ?string $search = null): void
    {
        if (! $this->showForm) {
            return;
        }
        if (($search === null || $search === '') && $lineIndex === null) {
            $search = trim($this->itemLookup);
        }
        $this->showOrderItemBrowse = false;
        $this->openDocumentItemBrowse($lineIndex, $search);
    }

    public function closeItemBrowse(): void
    {
        $this->closeDocumentItemBrowse();
    }

    public function pickBrowseItem(int $itemId): void
    {
        if (! $this->showForm) {
            return;
        }

        $item = Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('is_inactive', false)
            ->find($itemId);

        if (! $item) {
            $this->playPosSound('error');

            return;
        }

        $this->applyItemToCredit($item);
        $this->lookupMessage = '';
        $this->lineWarning = '';
        $this->playPosSound('success');
        $this->browseLineIndex = $this->firstEmptyLineIndex();
        $this->focusBrowseSearch();
    }

    public function addItemFromEntry(?string $code = null): void
    {
        if (! $this->showForm) {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        $this->itemLookup = $code;

        if ($code === '') {
            $this->clearAndFocusEntry();

            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $code, 'any');
        if ($item) {
            $this->itemLookup = '';
            $this->browseLineIndex = null;
            $this->applyItemToCredit($item);
            $this->scanModeActive = true;
            $this->playPosSound('success');
            $this->clearAndFocusEntry();

            return;
        }

        $this->playPosSound('error');
        $this->lookupMessage = 'Item '.$code.' was not found.';
        $this->openItemBrowse(null, $code);
    }

    public function autoAddEntryIfExactMatch(?string $code = null): void
    {
        if (! $this->showForm) {
            return;
        }

        $code = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', (string) ($code ?? $this->itemLookup)) ?? '');
        if ($code === '' || mb_strlen($code) < 2) {
            return;
        }

        $item = Item::findByScanCode((int) auth()->user()->company_id, $code, 'any');
        if (! $item || $this->codeIsPrefixOfLongerItemCode($code)) {
            return;
        }

        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->browseLineIndex = null;
        $this->applyItemToCredit($item);
        $this->scanModeActive = true;
        $this->playPosSound('success');
        $this->clearAndFocusEntry();
    }

    public function focusScanAndAdd(): void
    {
        if (! $this->showForm) {
            return;
        }

        $this->scanModeActive = true;
        $this->lookupMessage = '';

        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('cm-item-entry');
                if (!el) return;
                el.focus();
                const v = (el.value || '').trim();
                if (v.length >= 2) {
                    $wire.addItemFromEntry(v);
                } else {
                    el.select();
                }
            });
        JS);
    }

    public function clearItemLookup(): void
    {
        $this->itemLookup = '';
        $this->lookupMessage = '';
        $this->clearAndFocusEntry();
    }

    protected function clearAndFocusEntry(): void
    {
        $this->itemLookup = '';
        $this->js(<<<'JS'
            requestAnimationFrame(() => {
                const el = document.getElementById('cm-item-entry');
                if (el) { el.focus(); el.select(); }
            });
        JS);
    }

    protected function applyItemToCredit(Item $item): void
    {
        foreach ($this->lines as $i => $line) {
            if ((int) ($line['item_id'] ?? 0) === (int) $item->id
                || strcasecmp((string) ($line['item_code'] ?? ''), (string) $item->item_code) === 0) {
                $qty = (float) ($line['qty'] ?? 0);
                $this->lines[$i]['qty'] = (string) max(1, $qty + 1);
                $this->fillLineFromItem($i, $item, bumpQty: false);

                return;
            }
        }

        $index = $this->resolveCreditTargetIndex();
        $this->fillLineFromItem($index, $item);
        if (($this->lines[$index]['qty'] ?? '') === '' || (float) $this->lines[$index]['qty'] <= 0) {
            $this->lines[$index]['qty'] = '1';
        }

        $hasEmpty = collect($this->lines)->contains(fn ($l) => ! filled($l['item_code'] ?? null));
        if (! $hasEmpty) {
            $this->addLine();
        }
    }

    protected function fillLineFromItem(int $index, Item $item, bool $bumpQty = true): void
    {
        $this->lines[$index]['item_id'] = (int) $item->id;
        $this->lines[$index]['item_code'] = (string) $item->item_code;
        $this->lines[$index]['description'] = (string) ($item->description ?? '');
        $this->lines[$index]['uom'] = (string) ($item->unit_of_measure ?? '');
        $this->lines[$index]['price'] = $this->resolveCreditPrice($item);
        if ($bumpQty && (($this->lines[$index]['qty'] ?? '') === '' || (float) $this->lines[$index]['qty'] <= 0)) {
            $this->lines[$index]['qty'] = '1';
        }
    }

    protected function resolveCreditPrice(Item $item): string
    {
        if ($this->sales_order_id) {
            $orderLine = SalesOrderLine::query()
                ->where('sales_order_id', $this->sales_order_id)
                ->where(function ($q) use ($item) {
                    $q->where('item_id', $item->id)
                        ->orWhereRaw('LOWER(item_code) = ?', [mb_strtolower((string) $item->item_code)]);
                })
                ->orderBy('line_no')
                ->first();
            if ($orderLine && (float) $orderLine->price > 0) {
                return number_format((float) $orderLine->price, 4, '.', '');
            }
        }

        return number_format((float) ($item->list_price ?? 0), 4, '.', '');
    }

    protected function resolveCreditTargetIndex(): int
    {
        if ($this->browseLineIndex !== null && isset($this->lines[$this->browseLineIndex])
            && ! filled($this->lines[$this->browseLineIndex]['item_code'] ?? null)) {
            return (int) $this->browseLineIndex;
        }

        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null)) {
                return (int) $i;
            }
        }

        $this->addLine();

        return count($this->lines) - 1;
    }

    protected function firstEmptyLineIndex(): ?int
    {
        foreach ($this->lines as $i => $line) {
            if (! filled($line['item_code'] ?? null)) {
                return (int) $i;
            }
        }

        return null;
    }

    protected function codeIsPrefixOfLongerItemCode(string $code): bool
    {
        $code = mb_strtolower(trim($code));
        if ($code === '') {
            return false;
        }

        return Item::query()
            ->where('company_id', auth()->user()->company_id)
            ->where('is_inactive', false)
            ->whereRaw('LOWER(item_code) LIKE ?', [$code.'%'])
            ->whereRaw('LOWER(item_code) <> ?', [$code])
            ->exists();
    }

    public function emptyCreditLine(): array
    {
        return ['item_id' => null, 'item_code' => '', 'description' => '', 'uom' => '', 'qty' => '', 'price' => ''];
    }

    public function pickOrderLine(int $lineId): void
    {
        if (! $this->sales_order_id) {
            return;
        }

        $orderLine = SalesOrderLine::query()
            ->where('sales_order_id', $this->sales_order_id)
            ->find($lineId);
        if (! $orderLine) {
            return;
        }

        $index = $this->itemBrowseLineIndex;
        if ($index === null || ! isset($this->lines[$index])) {
            $this->addLine();
            $index = count($this->lines) - 1;
        }

        $this->lines[$index]['item_id'] = $orderLine->item_id ? (int) $orderLine->item_id : null;
        $this->lines[$index]['item_code'] = (string) $orderLine->item_code;
        $this->lines[$index]['description'] = (string) ($orderLine->description ?? '');
        $this->lines[$index]['uom'] = (string) ($orderLine->uom ?? '');
        $price = (string) $orderLine->price;
        $this->lines[$index]['price'] = ($price === '0' || $price === '0.0' || $price === '0.00') ? '' : $price;
        if (($this->lines[$index]['qty'] ?? '') === '' || (float) $this->lines[$index]['qty'] <= 0) {
            $this->lines[$index]['qty'] = '1';
        }

        $this->showOrderItemBrowse = false;
        $this->itemBrowseLineIndex = null;
    }

    public function openEmail(int $id): void
    {
        $memo = CreditMemo::query()->with('customer')->findOrFail($id);
        abort_unless($memo->company_id === auth()->user()->company_id, 403);
        $this->emailMemoId = $memo->id;
        $this->emailTo = $memo->customer?->email ?? '';
        $this->emailSubject = 'Credit Memo '.$memo->memo_number;
    }

    public function closeEmail(): void
    {
        $this->emailMemoId = null;
    }

    public function save(): void
    {
        $this->validate([
            'memo_number' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'credit_amount' => 'nullable|numeric|min:0',
            'lines' => 'nullable|array',
            'lines.*.item_code' => 'nullable|string',
            'lines.*.qty' => 'nullable|numeric|min:0',
            'lines.*.price' => 'nullable|numeric|min:0',
        ]);

        if ($this->sales_order_id) {
            $orderOk = SalesOrder::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('customer_id', $this->customer_id)
                ->whereKey($this->sales_order_id)
                ->exists();
            if (! $orderOk) {
                $this->addError('sales_order_id', 'Selected order does not belong to this customer.');

                return;
            }
        }

        $filledLines = collect($this->lines)
            ->filter(fn ($l) => trim((string) ($l['item_code'] ?? '')) !== '')
            ->values();

        if ($filledLines->isNotEmpty() && ! $this->customer_id) {
            $this->addError('customer_id', 'Select a customer before adding item lines.');

            return;
        }

        foreach ($filledLines as $i => $line) {
            if ((float) ($line['qty'] ?? 0) <= 0) {
                $this->addError('lines.'.$i.'.qty', 'Qty must be greater than zero for item lines.');

                return;
            }

            $exists = Item::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('item_code', $line['item_code'])
                ->exists();
            if (! $exists) {
                $this->addError('lines.'.$i.'.item_code', 'Unknown item code.');

                return;
            }
        }

        $lineAmount = (float) $filledLines->sum(fn ($l) => ((float) $l['qty']) * ((float) $l['price']));
        $flatAmount = round((float) str_replace(',', '', (string) $this->credit_amount), 2);
        $amount = $filledLines->isNotEmpty() ? $lineAmount : $flatAmount;

        if ($amount < 0.01) {
            $this->addError('credit_amount', 'Enter a credit amount, or add at least one item line.');

            return;
        }

        // Flat amount credit (no items) should not restock inventory.
        $restock = $this->restock_inventory && $filledLines->isNotEmpty();

        $companyId = (int) auth()->user()->company_id;

        DB::transaction(function () use ($amount, $companyId, $filledLines, $restock) {
            $candidate = filled($this->memo_number) ? (string) $this->memo_number : CreditMemo::nextNumber($companyId);
            if (
                CreditMemo::query()
                    ->where('company_id', $companyId)
                    ->where('memo_number', $candidate)
                    ->exists()
            ) {
                $candidate = CreditMemo::nextNumber($companyId);
            }
            $this->memo_number = $candidate;

            $memo = CreditMemo::query()->create([
                'company_id' => $companyId,
                'memo_number' => $candidate,
                'memo_date' => $this->memo_date,
                'reference_no' => $this->reference_no ?: null,
                'reason' => $this->reason ?: null,
                'customer_id' => $this->customer_id,
                'sales_order_id' => $this->sales_order_id,
                'amount' => $amount,
                'status' => 'Open',
                'comments' => $this->comments,
                'restock_inventory' => $restock,
            ]);

            foreach ($filledLines as $i => $line) {
                $item = Item::query()
                    ->where('company_id', $companyId)
                    ->where('item_code', $line['item_code'])
                    ->first();
                $qty = (float) $line['qty'];
                $price = (float) $line['price'];
                $memo->lines()->create([
                    'item_id' => $item?->id,
                    'item_code' => $line['item_code'],
                    'description' => $line['description'] ?: $item?->description,
                    'uom' => $line['uom'] ?: $item?->unit_of_measure,
                    'qty' => $qty,
                    'price' => $price,
                    'line_total' => $qty * $price,
                    'line_no' => $i + 1,
                ]);
            }

            if ($restock) {
                app(InventoryService::class)->applyCreditMemoStock($memo->fresh('lines'));
            }
        });

        $this->showForm = false;
        $msg = 'Credit memo '.$this->memo_number.' created.';
        if ($filledLines->isEmpty()) {
            $msg .= ' Amount-only credit (no items).';
        } else {
            $msg .= $restock ? ' Stock restocked.' : ' No stock change (price adjustment).';
        }
        $msg .= ' Apply it from an unpaid invoice.';
        session()->flash('status', $msg);
    }
}; ?>

<div class="desk-page relative">
    <x-favorite-list :favorites="$favorites" :active="$favorite" />

    <div @class(['desk-main', 'desk-main-rail-layout' => ! $showForm])>
        <x-action-bar :title="$showForm ? 'New Credit Memo' : 'Action'" />

        @if ($showForm)
            <form wire:submit="save" class="entity-body cm-form">
                @if (session('status'))
                    <div class="desk-flash" role="status">{{ session('status') }}</div>
                @endif

                <div class="cm-steps" aria-label="Credit memo steps">
                    <div @class(['cm-step', 'is-done' => (bool) $customer_id, 'is-active' => ! $customer_id])>
                        <span class="cm-step-num">1</span>
                        <span class="cm-step-label">Customer</span>
                    </div>
                    <div class="cm-step-line"></div>
                    <div @class(['cm-step', 'is-done' => (bool) $sales_order_id, 'is-active' => (bool) $customer_id && ! $sales_order_id])>
                        <span class="cm-step-num">2</span>
                        <span class="cm-step-label">Order / Invoice</span>
                    </div>
                    <div class="cm-step-line"></div>
                    <div @class(['cm-step', 'is-active' => (bool) $sales_order_id || filled($credit_amount)])>
                        <span class="cm-step-num">3</span>
                        <span class="cm-step-label">Credit Lines / Amount</span>
                    </div>
                </div>

                <div class="cm-form-top">
                    <div class="inv-card cm-card">
                        <div class="inv-card-title">Memo</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="memo_number">Memo No.</label>
                            <input id="memo_number" wire:model="memo_number" class="so-input font-mono" readonly title="Auto-generated" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="memo_date">Date</label>
                            <input id="memo_date" type="date" wire:model="memo_date" class="so-input" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="cm_reference">Reference</label>
                            <input id="cm_reference" wire:model="reference_no" class="so-input" placeholder="RMA / PO…" />
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="cm_reason">Reason</label>
                            <select id="cm_reason" wire:model="reason" class="so-input">
                                <option value="">— Select —</option>
                                <option value="Return">Return</option>
                                <option value="Price Adjustment">Price Adjustment</option>
                                <option value="Allowance">Allowance</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="inv-card cm-card">
                        <div class="inv-card-title">Customer &amp; Order</div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl">Customer</label>
                            <div class="cm-pick-row">
                                <input
                                    type="text"
                                    class="so-input"
                                    readonly
                                    value="{{ $selectedCustomer ? ($selectedCustomer->customer_id.' — '.$selectedCustomer->company_name) : '' }}"
                                    placeholder="Find customer…"
                                    wire:click="openCustomerBrowse"
                                    style="cursor:pointer"
                                />
                                <button type="button" class="desk-btn desk-btn-sm" wire:click="openCustomerBrowse">Find</button>
                                @if ($customer_id)
                                    <button type="button" class="desk-btn desk-btn-sm" wire:click="clearCustomer" title="Clear">×</button>
                                @endif
                            </div>
                        </div>
                        @error('customer_id') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror

                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl">Order / Inv</label>
                            <div class="cm-pick-row">
                                @php
                                    $orderLabel = '';
                                    if ($selectedOrder) {
                                        $orderLabel = (string) $selectedOrder->order_number;
                                        if ($selectedOrder->invoice) {
                                            $orderLabel .= ' / Inv '.$selectedOrder->invoice->invoice_number;
                                        }
                                    }
                                @endphp
                                <input
                                    type="text"
                                    class="so-input"
                                    readonly
                                    value="{{ $orderLabel }}"
                                    placeholder="{{ $customer_id ? 'Find order / invoice…' : 'Select customer first' }}"
                                    @if ($customer_id) wire:click="openOrderBrowse" style="cursor:pointer" @else disabled @endif
                                />
                                <button type="button" class="desk-btn desk-btn-sm" wire:click="openOrderBrowse" @disabled(! $customer_id)>Find</button>
                                @if ($sales_order_id)
                                    <button type="button" class="desk-btn desk-btn-sm" wire:click="clearOrder" title="Clear">×</button>
                                @endif
                            </div>
                        </div>
                        @error('sales_order_id') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror

                        <div class="so-form-row so-form-row-side" style="align-items:flex-start">
                            <label class="so-form-lbl" for="cm_comments">Comments</label>
                            <textarea id="cm_comments" wire:model="comments" rows="3" class="so-input so-input-area" placeholder="Optional notes…"></textarea>
                        </div>
                    </div>

                    <div class="inv-card cm-card cm-summary-card">
                        <div class="inv-card-title">Credit Summary</div>
                        <div class="cm-summary-amount">
                            <span>Credit Amount</span>
                            <strong>${{ number_format($creditPreview, 2) }}</strong>
                        </div>
                        <div class="so-form-row so-form-row-side">
                            <label class="so-form-lbl" for="cm_credit_amount">Flat Amount</label>
                            <input
                                id="cm_credit_amount"
                                wire:model.live="credit_amount"
                                class="so-input text-right"
                                inputmode="decimal"
                                placeholder="0.00"
                                @disabled($lineTotal > 0)
                                title="{{ $lineTotal > 0 ? 'Using line totals' : 'Required if no item lines' }}"
                            />
                        </div>
                        @error('credit_amount') <p class="cm-field-error" role="alert">{{ $message }}</p> @enderror
                        <p class="item-hint" style="margin:0.4rem 0 0.55rem">
                            @if ($lineTotal > 0)
                                Amount comes from credit lines below.
                            @else
                                Use flat amount for price adjustment / allowance (no items).
                            @endif
                        </p>
                        <label class="entity-check cm-restock">
                            <input type="checkbox" wire:model="restock_inventory" />
                            Restock inventory when item lines are saved
                        </label>
                    </div>
                </div>

                <div class="entity-section cm-lines-section">
                    <div class="entity-section-head cm-lines-head">
                        <h3 class="entity-section-title">Credit Lines</h3>
                        <div class="cm-lines-actions">
                            <span class="item-hint cm-lines-hint">
                                Scan / Browse full catalog (same as Purchase Orders).
                                @if ($sales_order_id)
                                    Order price used when item is on {{ $selectedOrder?->order_number }}.
                                @endif
                            </span>
                            <div class="cm-lines-btns">
                                @if ($sales_order_id)
                                    <button type="button" wire:click="openOrderItemBrowse" class="desk-btn desk-btn-sm">From Order</button>
                                @endif
                                <button type="button" wire:click="openItemBrowse" class="desk-btn desk-btn-sm">Browse Items (F2)</button>
                                <button type="button" wire:click="addLine" class="desk-btn desk-btn-sm">Add Line</button>
                            </div>
                        </div>
                    </div>

                    <div class="so-entry cm-entry">
                        <span class="so-entry-label">Add item — scan or type code</span>
                        <div class="so-scan-bar cm-scan-bar" role="search" @class(['is-scan-ready' => $scanModeActive])>
                            <button type="button" wire:click="focusScanAndAdd" class="so-scan-btn" title="Scan: focus entry, or add code in the box">
                                <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <input
                                id="cm-item-entry"
                                type="text"
                                wire:model="itemLookup"
                                class="so-input so-entry-input font-mono"
                                placeholder="{{ $scanModeActive ? 'Type full code… adds when exact match' : 'Scan barcode or type full code then ✓' }}"
                                autocomplete="off"
                                x-data="{
                                    timer: null,
                                    rapid: false,
                                    scheduleAuto() {
                                        clearTimeout(this.timer);
                                        const scanOn = !!$wire.scanModeActive;
                                        if (!scanOn && !this.rapid) return;
                                        const delay = this.rapid ? 100 : 750;
                                        this.timer = setTimeout(() => {
                                            const v = ($el.value || '').trim();
                                            if (v.length < 2) { this.rapid = false; return; }
                                            $wire.autoAddEntryIfExactMatch(v);
                                            this.rapid = false;
                                        }, delay);
                                    },
                                    onKey(e) {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                            clearTimeout(this.timer);
                                            $wire.addItemFromEntry(($el.value || '').trim());
                                            this.rapid = false;
                                            return;
                                        }
                                        if (e.key === 'F2') {
                                            e.preventDefault();
                                            $wire.openItemBrowse();
                                            return;
                                        }
                                        const now = Date.now();
                                        if (this._last && (now - this._last) < 45) this.rapid = true;
                                        this._last = now;
                                        this.scheduleAuto();
                                    }
                                }"
                                x-on:keydown="onKey($event)"
                            />
                            <button type="button" class="so-icon-btn so-entry-clear-btn" wire:click="clearItemLookup" title="Clear">×</button>
                            <button type="button" class="so-icon-btn so-entry-add-btn" x-on:click.prevent="$wire.addItemFromEntry(document.getElementById('cm-item-entry')?.value || '')" title="Add">
                                <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
                            </button>
                        </div>
                        <button type="button" wire:click="openItemBrowse" class="so-browse-btn cm-browse-btn" title="Item list (F2)">Browse (F2)</button>
                        @if ($lookupMessage !== '')
                            <span class="item-hint cm-lookup-msg">{{ $lookupMessage }}</span>
                        @endif
                    </div>

                    <div class="desk-grid cm-lines-wrap">
                        <table class="desk-table cm-lines-table">
                            <colgroup>
                                <col class="col-code">
                                <col class="col-find">
                                <col class="col-desc">
                                <col class="col-uom">
                                <col class="col-qty">
                                <col class="col-price">
                                <col class="col-total">
                                <col class="col-action">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="col-code">Item Code</th>
                                    <th class="col-find"></th>
                                    <th class="col-desc">Description</th>
                                    <th class="col-uom">UOM</th>
                                    <th class="col-qty">Qty</th>
                                    <th class="col-price">Price</th>
                                    <th class="col-total">Total</th>
                                    <th class="col-action"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $i => $line)
                                    @php $lineUoms = $this->uomOptionsForLine($i, $uomOptions); @endphp
                                    <tr wire:key="cm-line-{{ $i }}">
                                        <td class="col-code" data-label="Item Code">
                                            <input
                                                id="cm-line-code-{{ $i }}"
                                                wire:model="lines.{{ $i }}.item_code"
                                                wire:keydown.enter.prevent="lookupOrBrowseItem({{ $i }}, $event.target.value)"
                                                class="so-input font-mono cm-cell"
                                                placeholder="Code"
                                                aria-label="Item code line {{ $i + 1 }}"
                                                autocomplete="off"
                                            />
                                        </td>
                                        <td class="col-find" data-label="">
                                            <div class="cm-find-btns">
                                                <button type="button" class="desk-btn desk-btn-sm" wire:click="openItemBrowse({{ $i }})" title="Browse catalog">Browse</button>
                                                @if ($sales_order_id)
                                                    <button type="button" class="desk-btn desk-btn-sm" wire:click="openOrderItemBrowse({{ $i }})" title="Items from selected order">Order</button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="col-desc" data-label="Description">
                                            <input wire:model="lines.{{ $i }}.description" class="so-input cm-cell" aria-label="Description line {{ $i + 1 }}" />
                                        </td>
                                        <td class="col-uom" data-label="UOM">
                                            <select wire:model.live="lines.{{ $i }}.uom" class="so-input cm-cell" aria-label="UOM line {{ $i + 1 }}">
                                                <option value="">—</option>
                                                @foreach ($lineUoms as $uomOpt)
                                                    <option value="{{ $uomOpt }}">{{ $uomOpt }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="col-qty" data-label="Qty">
                                            <input wire:model.live="lines.{{ $i }}.qty" class="so-input text-right cm-cell" placeholder="0" aria-label="Qty line {{ $i + 1 }}" />
                                        </td>
                                        <td class="col-price" data-label="Price">
                                            <input wire:model.live="lines.{{ $i }}.price" class="so-input text-right cm-cell" placeholder="0" aria-label="Price line {{ $i + 1 }}" />
                                        </td>
                                        <td class="col-total desk-money" data-label="Total">${{ number_format(((float) ($line['qty'] ?: 0) * (float) ($line['price'] ?: 0)), 2) }}</td>
                                        <td class="col-action" data-label="">
                                            <button type="button" wire:click="removeLine({{ $i }})" class="desk-btn desk-btn-sm" aria-label="Remove line">×</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('lines') <p class="cm-field-error cm-lines-error" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="entity-footer-actions cm-form-footer">
                    <button type="button" wire:click="cancelForm" class="desk-btn">Cancel</button>
                    <button type="submit" class="desk-btn desk-btn-primary">Save Credit Memo</button>
                </div>
            </form>
        @else
            <div class="desk-main-split">
                <div class="desk-main-body">
                    @if (session('status'))
                        <div class="desk-flash" role="status">{{ session('status') }}</div>
                    @endif

                    <div class="desk-toolbar orders-toolbar cm-list-toolbar">
                        <label class="desk-toolbar-label" for="cm-search">Search Credit Memos:</label>
                        <input
                            id="cm-search" data-pos-search
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Memo #, customer, order #, invoice #…"
                            class="desk-search orders-search-input"
                            aria-label="Search Credit Memos"
                        />
                        <div class="orders-toolbar-right">
                            <select
                                id="cm-status-filter"
                                wire:model.live="statusFilter"
                                class="desk-select orders-status-select"
                                aria-label="Filter by status"
                            >
                                <option value="">All</option>
                                <option value="Open">Open</option>
                                <option value="Applied">Applied</option>
                            </select>
                            <button type="button" wire:click="startNew" class="desk-btn desk-btn-primary">New Credit Memo</button>
                        </div>
                    </div>

                    <div class="desk-titlebar">
                        <h2 class="desk-title">{{ $listTitle }}</h2>
                        <span class="desk-title-meta">{{ number_format($memos->total()) }} records</span>
                    </div>

                    <div class="desk-grid cm-list-grid">
                        <table class="desk-table desk-table-fit">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:2rem"></th>
                                    <x-desk-sort-th field="memo_number" label="Memo No." />
                                    <x-desk-sort-th field="memo_date" label="Date" />
                                    <x-desk-sort-th field="customer_code" label="Customer ID" />
                                    <x-desk-sort-th field="customer_name" label="Customer" />
                                    <x-desk-sort-th field="order_number" label="Order No." />
                                    <x-desk-sort-th field="invoice_number" label="Invoice No." />
                                    <x-desk-sort-th field="reason" label="Reason" />
                                    <x-desk-sort-th field="amount" label="Amount" align="money" />
                                    <x-desk-sort-th field="remaining" label="Remaining" align="money" />
                                    <x-desk-sort-th field="status" label="Status" align="center" />
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($memos as $m)
                                    @php
                                        $applied = (float) ($m->applied_sum ?? 0);
                                        $remaining = max(0, (float) $m->amount - $applied);
                                    @endphp
                                    <tr
                                        wire:click="selectRow({{ $m->id }})"
                                        wire:dblclick="openMemoPdf({{ $m->id }})"
                                        class="cursor-pointer"
                                        @class(['is-selected' => $selectedId === $m->id])
                                    >
                                        <td class="text-center" wire:click.stop>
                                            <input
                                                type="radio"
                                                name="cm_select"
                                                value="{{ $m->id }}"
                                                @checked($selectedId === $m->id)
                                                wire:click="selectRow({{ $m->id }})"
                                                aria-label="Select credit memo {{ $m->memo_number }}"
                                            />
                                        </td>
                                        <td class="desk-num">
                                            <a href="{{ route('sales.credit-memos.pdf', $m) }}" target="_blank" rel="noopener" wire:click.stop>{{ $m->memo_number }}</a>
                                        </td>
                                        <td>{{ optional($m->memo_date)?->format('n/j/Y') }}</td>
                                        <td class="desk-num">{{ $m->customer?->customer_id }}</td>
                                        <td>{{ $m->customer?->company_name }}</td>
                                        <td class="desk-num">{{ $m->salesOrder?->order_number ?: '—' }}</td>
                                        <td class="desk-num">{{ $m->salesOrder?->invoice?->invoice_number ?: '—' }}</td>
                                        <td>{{ $m->reason ?: '—' }}</td>
                                        <td class="desk-money">${{ number_format($m->amount, 2) }}</td>
                                        <td class="desk-money">${{ number_format($remaining, 2) }}</td>
                                        <td class="text-center">
                                            <span @class([
                                                'desk-pill',
                                                'desk-pill-new' => $m->status === 'Open',
                                                'desk-pill-invoiced' => $m->status === 'Applied',
                                                'desk-pill-muted' => ! in_array($m->status, ['Open', 'Applied'], true),
                                            ])>{{ $m->status }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="is-empty">
                                        <td colspan="11">No credit memos yet. Click <strong>New Credit Memo</strong> to create one.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <x-record-count :count="$memos->total()">{{ $memos->links() }}</x-record-count>
                </div>

                <aside class="desk-rail" aria-label="Credit memo actions">
                    <button type="button" wire:click="startNew" class="desk-rail-btn desk-rail-btn-primary" title="New credit memo" aria-label="New credit memo">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M8 3v10M3 8h10"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="viewSelected" class="desk-rail-btn" title="View PDF" aria-label="View PDF" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M1.5 8s2.5-4.5 6.5-4.5S14.5 8 14.5 8s-2.5 4.5-6.5 4.5S1.5 8 1.5 8z"/>
                            <circle cx="8" cy="8" r="2"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="printSelected" class="desk-rail-btn" title="Print PDF" aria-label="Print PDF" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <path d="M4 6V3h8v3M4 12h8v-3H4v3z"/>
                            <rect x="3" y="6" width="10" height="4" rx="0.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="emailSelected" class="desk-rail-btn" title="Email credit memo" aria-label="Email" @disabled(! $selectedId)>
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                            <rect x="2" y="3.5" width="12" height="9" rx="1"/>
                            <path d="M2.5 4.5L8 9l5.5-4.5"/>
                        </svg>
                    </button>
                    <button type="button" wire:click="refreshList" class="desk-rail-btn" title="Refresh" aria-label="Refresh list">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M13 8a5 5 0 11-1.2-3.3"/>
                            <path d="M13 3v3h-3"/>
                        </svg>
                    </button>
                </aside>
            </div>
        @endif
    </div>

    @if ($emailMemo)
        <div class="desk-modal-backdrop" wire:click.self="closeEmail" role="dialog" aria-modal="true" aria-label="Email credit memo">
            <div class="desk-modal desk-modal-sm">
                <div class="desk-modal-head">
                    <span>Email Credit Memo {{ $emailMemo->memo_number }}</span>
                    <button type="button" wire:click="closeEmail" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <form method="POST" action="{{ route('sales.credit-memos.email', $emailMemo) }}" class="desk-modal-body space-y-3">
                    @csrf
                    <p class="inv-email-note">Sends the credit memo PDF to the customer.</p>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="cm-email">To</label>
                        <input id="cm-email" name="email" type="email" value="{{ $emailTo }}" required class="so-input" placeholder="customer@email.com" />
                    </div>
                    <div class="so-form-row so-form-row-side">
                        <label class="so-form-lbl" for="cm-subject">Subject</label>
                        <input id="cm-subject" name="subject" value="{{ $emailSubject }}" class="so-input" />
                    </div>
                    <div class="entity-footer-actions" style="justify-content:flex-end">
                        <button type="button" wire:click="closeEmail" class="desk-btn">Cancel</button>
                        <button type="submit" class="desk-btn desk-btn-primary">Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showCustomerBrowse)
        <div class="desk-modal-backdrop" wire:click.self="closeCustomerBrowse" role="dialog" aria-modal="true" aria-label="Select customer">
            <div class="desk-modal desk-modal-lg so-item-browse-modal">
                <div class="desk-modal-head">
                    <span>Select Customer</span>
                    <button type="button" wire:click="closeCustomerBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-item-browse-toolbar">
                    <input
                        type="search"
                        wire:model.live.debounce.200ms="customerSearch"
                        class="so-input so-item-browse-search"
                        placeholder="Search ID, name, contact, phone…"
                        autofocus
                    />
                    <span class="so-item-browse-count">{{ $browseCustomers->count() }} shown</span>
                </div>
                <div class="so-item-browse-scroll">
                    <table class="so-item-browse-table">
                        <thead>
                            <tr>
                                <th>Customer ID</th>
                                <th>Company</th>
                                <th>Contact</th>
                                <th>City</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($browseCustomers as $bc)
                                <tr class="is-pickable" wire:click="pickCustomer({{ $bc->id }})" style="cursor:pointer" title="Click to select">
                                    <td class="font-mono">{{ $bc->customer_id }}</td>
                                    <td>{{ $bc->company_name }}</td>
                                    <td>{{ $bc->contact }}</td>
                                    <td>{{ collect([$bc->city, $bc->state])->filter()->implode(', ') }}</td>
                                    <td>{{ $bc->telephone }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="so-item-browse-empty">No customers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="so-item-browse-foot">
                    <span>Click a row to select · Esc / Close</span>
                    <button type="button" wire:click="closeCustomerBrowse" class="desk-btn">Close</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showOrderBrowse)
        <div class="desk-modal-backdrop" wire:click.self="closeOrderBrowse" role="dialog" aria-modal="true" aria-label="Select order or invoice">
            <div class="desk-modal desk-modal-lg so-item-browse-modal">
                <div class="desk-modal-head">
                    <span>Select Order / Invoice{{ $selectedCustomer ? ' — '.$selectedCustomer->company_name : '' }}</span>
                    <button type="button" wire:click="closeOrderBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-item-browse-toolbar">
                    <input
                        type="search"
                        wire:model.live.debounce.200ms="orderBrowseSearch"
                        class="so-input so-item-browse-search"
                        placeholder="Search order #, invoice #, PO #…"
                        autofocus
                    />
                    <span class="so-item-browse-count">{{ $browseOrders->count() }} shown</span>
                </div>
                <div class="so-item-browse-scroll">
                    <table class="so-item-browse-table">
                        <thead>
                            <tr>
                                <th>Order No.</th>
                                <th>Invoice No.</th>
                                <th>PO No.</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="is-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($browseOrders as $bo)
                                <tr class="is-pickable" wire:click="pickOrder({{ $bo->id }})" style="cursor:pointer" title="Click to select">
                                    <td class="font-mono">{{ $bo->order_number }}</td>
                                    <td class="font-mono">{{ $bo->invoice?->invoice_number ?: '—' }}</td>
                                    <td>{{ $bo->customer_po_no ?: '—' }}</td>
                                    <td>{{ optional($bo->order_date)?->format('n/j/Y') }}</td>
                                    <td>{{ $bo->status }}</td>
                                    <td class="is-num">${{ number_format((float) $bo->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="so-item-browse-empty">No orders / invoices for this customer.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="so-item-browse-foot">
                    <span>Click a row to select · Esc / Close</span>
                    <button type="button" wire:click="closeOrderBrowse" class="desk-btn">Close</button>
                </div>
            </div>
        </div>
    @endif

    @if ($showOrderItemBrowse)
        <div class="desk-modal-backdrop so-item-browse-backdrop" wire:click.self="closeOrderItemBrowse" role="dialog" aria-modal="true" aria-label="Order item list">
            <div class="desk-modal desk-modal-lg so-item-browse-modal">
                <div class="desk-modal-head">
                    <span>
                        Order Items
                        @if ($selectedOrder)
                            — {{ $selectedOrder->order_number }}
                            @if ($selectedOrder->invoice)
                                / Inv {{ $selectedOrder->invoice->invoice_number }}
                            @endif
                        @endif
                    </span>
                    <button type="button" wire:click="closeOrderItemBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-item-browse-toolbar">
                    <input
                        type="search"
                        wire:model.live.debounce.200ms="itemBrowseSearch"
                        class="so-input so-item-browse-search"
                        placeholder="Search this order's items…"
                        autofocus
                    />
                    <span class="so-item-browse-count">{{ $browseOrderLines->count() }} shown</span>
                </div>
                <div class="so-item-browse-scroll">
                    <table class="so-item-browse-table">
                        <colgroup>
                            <col class="col-code" />
                            <col class="col-desc" />
                            <col class="col-uom" />
                            <col class="col-stock" />
                            <col class="col-price" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th>UOM</th>
                                <th class="is-num">Ordered</th>
                                <th class="is-num">Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($browseOrderLines as $bl)
                                <tr class="is-pickable" wire:click="pickOrderLine({{ $bl->id }})" style="cursor:pointer" title="Click to select">
                                    <td class="font-mono">{{ $bl->item_code }}</td>
                                    <td class="col-desc-cell">{{ $bl->description }}</td>
                                    <td>{{ $bl->uom ?: '—' }}</td>
                                    <td class="is-num">{{ rtrim(rtrim(number_format((float) $bl->qty_ordered, 2, '.', ''), '0'), '.') }}</td>
                                    <td class="is-num">${{ number_format((float) $bl->price, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="so-item-browse-empty">No items on this order.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="so-item-browse-foot">
                    <span>Only items from the selected order · Click a row to fill</span>
                    <button type="button" wire:click="closeOrderItemBrowse" class="desk-btn">Close</button>
                </div>
            </div>
        </div>
    @endif

    @include('livewire.pages.sales.orders.partials.item-browse-panel')

@script
<script>
    $wire.on('open-item-record', (payload) => {
        const url = payload?.url ?? payload?.[0]?.url;
        if (!url) return;
        window.open(url, '_blank');
    });
    $wire.on('pos-alert', (e) => {
        const kind = e?.kind ?? e?.[0]?.kind ?? 'error';
        window.playPosAlert && window.playPosAlert(kind);
    });
</script>
@endscript
</div>
