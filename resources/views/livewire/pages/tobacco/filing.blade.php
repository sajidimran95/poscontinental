<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\TobaccoStampInventory;
use App\Services\TobaccoXmlService;
use App\Services\TobaccoXmlValidator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app'), Title('Tobacco XML Filing')] class extends Component
{
    public string $filer_type = 'secondary_wholesaler';

    public string $product = 'cigarettes';

    public string $period_start = '';

    public string $period_end = '';

    /** @var list<string> */
    public array $validation_errors = [];

    public ?string $validation_status = null;

    public function mount(): void
    {
        $this->period_start = now()->startOfMonth()->toDateString();
        $this->period_end = now()->endOfMonth()->toDateString();
    }

    public function validateXml(TobaccoXmlService $xmlService, TobaccoXmlValidator $validator): void
    {
        $result = $validator->validate($this->buildPayload($xmlService));
        $this->validation_errors = $result['errors'];
        $this->validation_status = $result['valid'] ? 'valid' : 'invalid';
    }

    public function downloadXml(TobaccoXmlService $xmlService, TobaccoXmlValidator $validator): ?StreamedResponse
    {
        $payload = $this->buildPayload($xmlService);
        $result = $validator->validate($payload);
        $this->validation_errors = $result['errors'];
        $this->validation_status = $result['valid'] ? 'valid' : 'invalid';

        if (! $result['valid']) {
            return null;
        }

        $filename = 'tobacco-'.$this->filer_type.'-'.$this->product.'-'.$this->period_start.'.xml';

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, ['Content-Type' => 'application/xml']);
    }

    private function buildPayload(TobaccoXmlService $xmlService): string
    {
        $companyId = auth()->user()->company_id;
        $company = auth()->user()->company;

        $suppliers = Supplier::query()->where('company_id', $companyId)->whereNotNull('fein_no')->get();
        $customers = Customer::query()->where('company_id', $companyId)->whereNotNull('fein_no')->get();
        $invoices = Invoice::query()
            ->with('customer', 'salesOrder.lines.item')
            ->where('company_id', $companyId)
            ->whereBetween('invoice_date', [$this->period_start, $this->period_end])
            ->get();
        $stamps = TobaccoStampInventory::query()
            ->where('company_id', $companyId)
            ->whereDate('period_start', '>=', $this->period_start)
            ->whereDate('period_end', '<=', $this->period_end)
            ->latest('id')
            ->first();

        return $xmlService->build(
            $company,
            $this->filer_type,
            $this->product,
            $this->period_start,
            $this->period_end,
            $suppliers,
            $customers,
            $invoices,
            $stamps,
        );
    }
}; ?>

<div class="chief-panel bg-white p-4 space-y-3">
    <x-action-bar title="Michigan Treasury Tobacco XML Filing" />
    <p class="text-sm text-slate-600">Generates Secondary Wholesaler / Unclassified Acquirer XML for Cigarettes or OTP from supplier/customer FEIN and invoice sales in the selected period. Output is validated against the Michigan Tobacco Return XSD before download.</p>
    <div class="flex flex-wrap gap-3 items-end">
        <div class="chief-field">
            <label for="filer-type">Filer Type</label>
            <select id="filer-type" wire:model="filer_type" class="chief-input">
                <option value="secondary_wholesaler">Secondary Wholesaler</option>
                <option value="unclassified_acquirer">Unclassified Acquirer</option>
            </select>
        </div>
        <div class="chief-field">
            <label for="product">Product</label>
            <select id="product" wire:model="product" class="chief-input">
                <option value="cigarettes">Cigarettes</option>
                <option value="otp">OTP</option>
            </select>
        </div>
        <div class="chief-field"><label for="period-from">From</label><input id="period-from" type="date" wire:model="period_start" class="chief-input" /></div>
        <div class="chief-field"><label for="period-to">To</label><input id="period-to" type="date" wire:model="period_end" class="chief-input" /></div>
        <button type="button" wire:click="validateXml" class="chief-btn">Validate XML</button>
        <button type="button" wire:click="downloadXml" class="chief-btn-primary">Download XML</button>
        <a href="{{ route('tobacco.stamp-inventory') }}" wire:navigate class="chief-btn">Stamp Inventory (UA)</a>
    </div>

    @if ($validation_status === 'valid')
        <div class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900" role="status">
            XML passed XSD validation for the selected period and filer options.
        </div>
    @elseif ($validation_status === 'invalid')
        <div class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900" role="alert">
            <p class="font-semibold">XSD validation failed</p>
            <ul class="mt-2 list-disc pl-5 space-y-1">
                @foreach ($validation_errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
