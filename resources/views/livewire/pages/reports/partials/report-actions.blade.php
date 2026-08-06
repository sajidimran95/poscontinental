@props(['ready' => false])

<button type="button" class="desk-btn" wire:click="openCriteria">Report Criteria…</button>
@if ($ready)
    <button type="button" class="desk-btn" onclick="window.print()">Print</button>
    <button type="button" class="desk-btn" wire:click="downloadCsv">CSV</button>
    <button type="button" class="desk-btn desk-btn-primary" wire:click="downloadPdf">PDF</button>
@endif
