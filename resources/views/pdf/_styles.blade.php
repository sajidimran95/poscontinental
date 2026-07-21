{{-- Shared DomPDF styles — keep tables/layout DomPDF-safe (no flex/grid) --}}
<style>
    @page { margin: 28px 32px; }
    * { box-sizing: border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        color: #0f172a;
        line-height: 1.35;
        margin: 0;
    }
    .brand-bar {
        background: #1e3a5f;
        color: #fff;
        padding: 14px 16px;
        margin: 0 0 16px;
    }
    .brand-bar table { width: 100%; border-collapse: collapse; }
    .brand-bar td { border: none; padding: 0; vertical-align: middle; color: #fff; }
    .brand-name { font-size: 16px; font-weight: bold; letter-spacing: 0.02em; }
    .brand-sub { font-size: 9px; color: #cbd5e1; margin-top: 3px; }
    .doc-title { font-size: 20px; font-weight: bold; text-align: right; }
    .doc-meta { font-size: 9.5px; text-align: right; color: #e2e8f0; margin-top: 4px; }
    .status {
        display: inline-block;
        margin-top: 6px;
        padding: 2px 8px;
        font-size: 9px;
        font-weight: bold;
        letter-spacing: 0.04em;
        background: #dbeafe;
        color: #1e3a8a;
    }
    .status-paid, .status-ok { background: #bbf7d0; color: #14532d; }
    .status-open, .status-warn { background: #fecaca; color: #7f1d1d; }
    .status-credit { background: #fde68a; color: #92400e; }
    .section { margin-bottom: 14px; }
    .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -4px 12px; }
    .card {
        vertical-align: top;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 10px 11px;
    }
    .card-title {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-bottom: 6px;
    }
    .card strong { font-size: 11px; color: #0f172a; }
    .card .line { margin: 2px 0; color: #334155; }
    .muted { color: #64748b; }
    .summary {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px 0;
        margin: 0 -4px 14px;
    }
    .summary td {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 10px 12px;
        text-align: center;
        width: 25%;
    }
    .summary .lbl {
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 4px;
    }
    .summary .val {
        font-size: 13px;
        font-weight: bold;
        color: #0f172a;
    }
    table.lines {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }
    table.lines th {
        background: #1e3a5f;
        color: #fff;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 7px 8px;
        border: none;
        text-align: left;
    }
    table.lines td {
        padding: 7px 8px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    table.lines tr:nth-child(even) td { background: #f8fafc; }
    .right { text-align: right; }
    .center { text-align: center; }
    .mono { font-family: DejaVu Sans Mono, DejaVu Sans, sans-serif; font-size: 10px; }
    .footer-grid { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .footer-grid > tbody > tr > td { vertical-align: top; border: none; padding: 0; }
    .notes {
        width: 54%;
        padding-right: 16px;
        color: #64748b;
        font-size: 9.5px;
    }
    .totals-wrap { width: 46%; }
    .totals {
        width: 100%;
        border-collapse: collapse;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .totals td {
        padding: 6px 10px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 10.5px;
    }
    .totals tr:last-child td { border-bottom: none; }
    .totals .label { color: #475569; }
    .totals .grand td {
        background: #1e3a5f;
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        padding: 8px 10px;
    }
    .totals .balance td {
        background: #fef2f2;
        color: #991b1b;
        font-weight: bold;
    }
    .totals .credit-grand td {
        background: #92400e;
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        padding: 8px 10px;
    }
    .foot {
        margin-top: 18px;
        padding-top: 8px;
        border-top: 1px solid #e2e8f0;
        font-size: 8.5px;
        color: #94a3b8;
        text-align: center;
    }
    .empty {
        padding: 16px;
        text-align: center;
        color: #64748b;
    }
</style>
