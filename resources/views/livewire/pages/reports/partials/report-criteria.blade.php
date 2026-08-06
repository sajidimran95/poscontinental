@php
    /** Expected from parent Livewire: $showCriteria, dateMode, dates, filters */
    $rcTitle = $rcTitle ?? 'Report Criteria';
    $rcShowCustomer = $rcShowCustomer ?? false;
    $rcShowSupplier = $rcShowSupplier ?? false;
    $rcShowCategory = $rcShowCategory ?? false;
    $rcShowManufacturer = $rcShowManufacturer ?? false;
    $rcShowItem = $rcShowItem ?? false;
@endphp

@if ($showCriteria)
    <div class="sbr-criteria-overlay" role="dialog" aria-modal="true" wire:key="report-criteria">
        <div class="sbr-criteria-backdrop" wire:click="cancelCriteria" aria-hidden="true"></div>
        <div class="sbr-criteria-modal" role="document">
            <div class="sbr-criteria-head">
                <span>{{ $rcTitle }}</span>
                <button type="button" class="sbr-criteria-close" wire:click.prevent="cancelCriteria" aria-label="Close">&times;</button>
            </div>
            <div class="sbr-criteria-body">
                <fieldset class="sbr-criteria-fieldset">
                    <legend class="sr-only">Set Date</legend>
                    <div class="sbr-radio-row">
                        <input id="rc-mode-single" type="radio" wire:model="dateMode" value="single" />
                        <label for="rc-mode-single" class="sbr-radio-label">Date:</label>
                        <input type="date" class="sbr-date-input" wire:model="singleDate" />
                    </div>
                    <div class="sbr-radio-row">
                        <input id="rc-mode-range" type="radio" wire:model="dateMode" value="range" />
                        <label for="rc-mode-range" class="sbr-radio-label">Date Between:</label>
                        <input type="date" class="sbr-date-input" wire:model="dateFrom" />
                        <span class="sbr-and">and</span>
                        <input type="date" class="sbr-date-input" wire:model="dateTo" />
                    </div>
                </fieldset>

                @if ($rcShowCustomer)
                    <div class="sbr-criteria-extra">
                        <label class="sbr-field-label" for="rc-customer">Select a Customer</label>
                        <select id="rc-customer" wire:model="customerId" class="sbr-select">
                            <option value="">All</option>
                            @foreach (($customers ?? collect()) as $c)
                                <option value="{{ $c->id }}">{{ $c->customer_id }} — {{ $c->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($rcShowSupplier)
                    <div class="sbr-criteria-extra">
                        <label class="sbr-field-label" for="rc-supplier">Select a Supplier</label>
                        <select id="rc-supplier" wire:model="supplierId" class="sbr-select">
                            <option value="">All</option>
                            @foreach (($suppliers ?? collect()) as $s)
                                <option value="{{ $s->id }}">{{ $s->supplier_id }} — {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($rcShowCategory)
                    <div class="sbr-criteria-extra">
                        <label class="sbr-field-label" for="rc-category">Select a Category</label>
                        <select id="rc-category" wire:model="categoryId" class="sbr-select">
                            <option value="">All</option>
                            @foreach (($categories ?? collect()) as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($rcShowManufacturer)
                    <div class="sbr-criteria-extra">
                        <label class="sbr-field-label" for="rc-mfr">Select a Manufacturer</label>
                        <select id="rc-mfr" wire:model="manufacturer" class="sbr-select">
                            <option value="">All</option>
                            @foreach (($manufacturers ?? collect()) as $m)
                                <option value="{{ $m }}">{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($rcShowItem)
                    <div class="sbr-criteria-extra">
                        <label class="sbr-field-label" for="rc-item">Select an Item</label>
                        <select id="rc-item" wire:model="itemId" class="sbr-select">
                            <option value="">All</option>
                            @foreach (($items ?? collect()) as $it)
                                <option value="{{ $it->id }}">{{ $it->item_code }} — {{ \Illuminate\Support\Str::limit($it->description, 40) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="sbr-criteria-actions">
                    <button type="button" class="sbr-btn-ok" wire:click.prevent="applyCriteria">OK</button>
                    <button type="button" wire:click.prevent="cancelCriteria">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endif
