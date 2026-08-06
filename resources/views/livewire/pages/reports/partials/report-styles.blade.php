{{-- Shared Report Criteria styles (include once per report page) --}}
<style>
.sbr-root { height: 100%; min-height: 0; display: flex; flex-direction: column; }
.sbr-page { background: #e8e8e8; flex: 1; min-height: 0; display: flex; flex-direction: column; }
.sbr-body { background: #d4d0c8; flex: 1; min-height: 0; overflow: auto; }
.sbr-toolbar {
    display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem;
    padding: 0.45rem 0.75rem; font-size: 12px; color: #1e293b;
    background: #f1f5f9; border-bottom: 1px solid #94a3b8;
}
.sbr-placeholder {
    margin: 2rem auto; max-width: 28rem; text-align: center;
    color: #475569; font-size: 14px; padding: 1rem;
}
.sbr-report {
    margin: 0.75rem auto; max-width: 1100px; background: #fff; color: #000;
    padding: 1.25rem 1.5rem 2rem; box-shadow: 0 1px 4px rgba(0,0,0,.18);
    font-family: "Times New Roman", Times, Georgia, serif;
    font-size: 12.5px; line-height: 1.35;
}
.sbr-report-wide { max-width: 1200px; }
.sbr-filter-box {
    margin: 0.5rem 0.75rem 0; max-width: 20rem;
}
.sbr-customer { margin-bottom: 1.75rem; page-break-inside: avoid; }
.sbr-customer-head { margin-bottom: 0.45rem; }
.sbr-customer-title {
    display: flex; justify-content: space-between; align-items: baseline;
    gap: 1rem; font-weight: 700; font-size: 13.5px;
}
.sbr-customer-name { text-transform: uppercase; }
.sbr-customer-id { font-weight: 700; white-space: nowrap; }
.sbr-customer-line { font-weight: 400; font-size: 12.5px; }
.sbr-mfr-head { font-weight: 700; margin: 0.65rem 0 0.25rem; font-size: 12.5px; }
.sbr-table { width: 100%; border-collapse: collapse; }
.sbr-table thead th {
    text-align: left; font-weight: 700; padding: 0.2rem 0.25rem 0.35rem;
    border-bottom: 1px solid #000; white-space: nowrap; font-size: 12px;
}
.sbr-table tbody td { padding: 0.12rem 0.25rem; vertical-align: top; }
.sbr-table .col-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.sbr-table thead th.col-num { text-align: right; }
.sbr-table .col-desc { word-break: break-word; }
.sbr-totals-row td { padding-top: 0.4rem; border-top: 1px solid #000; font-weight: 700; }
.sbr-grand-row td { border-top: 2px solid #000; }
.sbr-totals-label { text-align: left; }
.sbr-empty {
    padding: 1.5rem 1rem; text-align: center; color: #64748b;
    font-family: system-ui, sans-serif; font-size: 14px;
}
.sbr-criteria-overlay {
    position: fixed !important; inset: 0 !important; z-index: 2147483000 !important;
    display: flex !important; align-items: center; justify-content: center;
    padding: 1rem; pointer-events: none; background: transparent;
}
.sbr-criteria-backdrop {
    position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45); pointer-events: auto;
}
.sbr-criteria-modal {
    position: relative; z-index: 1; width: 100%; max-width: 34rem;
    border-radius: 2px; border: 1px solid #808080;
    box-shadow: 2px 2px 12px rgba(0,0,0,.4); background: #f0f0f0;
    pointer-events: auto; overflow: hidden;
}
.sbr-criteria-head {
    background: #0a5ea8; display: flex; align-items: center; justify-content: space-between;
    gap: 0.75rem; padding: 0.55rem 0.75rem; color: #fff; font-size: 14px; font-weight: 600;
}
.sbr-criteria-body {
    background: #f0f0f0; color: #000; font-family: "Segoe UI", Tahoma, sans-serif;
    font-size: 13px; padding: 0.9rem 1rem 1rem;
}
.sbr-criteria-fieldset { border: none; margin: 0; padding: 0 0 0.85rem; }
.sbr-radio-row {
    display: flex; flex-wrap: nowrap; align-items: center;
    gap: 0.4rem 0.45rem; margin-bottom: 0.6rem; white-space: nowrap;
}
.sbr-radio-row input[type="radio"] { flex: 0 0 auto; margin: 0; }
.sbr-radio-label { flex: 0 0 auto; min-width: 6.4rem; font-weight: 500; cursor: pointer; }
.sbr-date-input {
    width: 9.25rem; flex: 0 0 auto; background: #fff !important; color: #0f172a !important;
    height: 2.15rem; padding: 0.25rem 0.4rem; border: 1px solid #94a3b8; border-radius: 3px;
    font-size: 13px; -webkit-appearance: auto !important; appearance: auto !important;
    color-scheme: light; cursor: pointer;
}
.sbr-date-input::-webkit-calendar-picker-indicator { display: block !important; opacity: 1 !important; cursor: pointer; }
.sbr-and { color: #334155; font-size: 12px; }
.sbr-criteria-extra { margin-top: 0.25rem; padding-top: 0.65rem; border-top: 1px solid #c0c0c0; }
.sbr-field-label { display: block; margin-bottom: 0.35rem; font-weight: 500; }
.sbr-select {
    width: 100%; background: #fff; border: 1px solid #94a3b8; border-radius: 3px;
    height: 2.15rem; padding: 0.25rem 0.4rem; font-size: 13px;
    -webkit-appearance: auto !important; appearance: auto !important;
}
.sbr-criteria-actions {
    display: flex; justify-content: flex-end; gap: 0.5rem;
    margin-top: 1.1rem; padding-top: 0.75rem;
}
.sbr-criteria-actions button {
    min-width: 4.75rem; padding: 0.4rem 0.9rem; border: 1px solid #94a3b8;
    background: #fff; cursor: pointer; font-size: 13px; border-radius: 2px; pointer-events: auto;
}
.sbr-criteria-actions button.sbr-btn-ok {
    background: #0a5ea8; border-color: #084c8a; color: #fff; font-weight: 600;
}
.sbr-criteria-close {
    background: transparent; border: none; color: #fff; font-size: 1.35rem;
    line-height: 1; cursor: pointer; padding: 0 0.2rem;
}
.sbr-criteria-close:hover { color: #fecaca; }
.sr-only {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}
@media print {
    .chief-menubar, .chief-tabs, .chief-action-bar, .sbr-toolbar, .no-print,
    .sbr-criteria-overlay, .desk-modal-backdrop { display: none !important; }
    .sbr-page, .sbr-body { background: #fff !important; overflow: visible !important; height: auto !important; }
    .sbr-report { margin: 0; max-width: none; box-shadow: none; padding: 0; }
}
</style>
