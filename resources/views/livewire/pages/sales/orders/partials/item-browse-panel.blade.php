    @if ($showBrowse)
        {{-- Docked on the right of the sales order (not a popup) --}}
        <style>
            .so-expand-panel.so-expand-with-browse {
                display: flex !important;
                flex-direction: row !important;
                align-items: stretch !important;
                width: 100% !important;
                max-width: none !important;
                min-width: 0 !important;
                min-height: 0 !important;
                height: 100% !important;
                max-height: 100% !important;
                flex: 1 1 auto !important;
                gap: 0 !important;
            }
            .so-expand-with-browse > .so-expand-main {
                flex: 1 1 55% !important;
                min-width: 0 !important;
                min-height: 0 !important;
                max-width: 58% !important;
                height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
            }
            .so-expand-with-browse > .so-browse-dock {
                flex: 1 1 45% !important;
                min-width: 0 !important;
                min-height: 0 !important;
                max-width: 48% !important;
                height: auto !important;
                max-height: none !important;
                align-self: stretch !important;
            }
            .so-expand-with-browse .so-items-wrap,
            .so-expand-with-browse .so-items-wrap-tall {
                min-height: 0 !important;
                flex: 1 1 0 !important;
                overflow: hidden !important;
            }
            .so-expand-with-browse .so-footer {
                flex-direction: row !important;
                flex-wrap: nowrap !important;
                align-items: flex-start !important;
                gap: 0.45rem 0.85rem !important;
                padding: 0.4rem 0.55rem !important;
                flex-shrink: 0 !important;
            }
            .so-expand-with-browse .so-counters {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                gap: 0.35rem 1rem !important;
            }
            .so-expand-with-browse .so-counter-col {
                min-width: 0 !important;
            }
            .so-expand-with-browse .so-totals {
                flex: 0 0 auto !important;
                min-width: 0 !important;
                max-width: 13.5rem !important;
                width: auto !important;
                margin-left: auto !important;
            }
            .so-browse-dock .desk-modal-close {
                margin-right: 0.4rem;
                padding: 0 0.45rem;
                flex-shrink: 0;
            }
            .so-browse-dock {
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                height: 100%;
                overflow: hidden;
                background: #fff;
                border-left: 1px solid #c5cad3;
            }
            .so-browse-dock .desk-modal-head {
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: .5rem !important;
                flex-shrink: 0;
            }
            .so-browse-head-count {
                margin-left: auto;
                margin-right: .35rem;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                white-space: nowrap;
            }
            .so-item-browse-toolbar {
                display: flex;
                align-items: center;
                gap: .65rem;
                padding: .45rem .75rem;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                flex-shrink: 0;
            }
            .so-browse-alert {
                flex-shrink: 0;
                padding: .5rem .85rem;
                font-size: 13px;
                font-weight: 700;
                line-height: 1.35;
            }
            .so-browse-alert-error {
                background: #fef2f2;
                color: #991b1b;
                border-bottom: 1px solid #fecaca;
            }
            .so-browse-alert-warn {
                background: #fffbeb;
                color: #92400e;
                border-bottom: 1px solid #fde68a;
            }
            .so-browse-alert-ok {
                background: #ecfdf5;
                color: #065f46;
                border-bottom: 1px solid #a7f3d0;
            }
            .so-browse-action {
                position: relative;
            }
            .so-browse-action-btn {
                display: inline-flex;
                align-items: center;
                gap: .3rem;
                height: 1.85rem;
                padding: 0 .55rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #fff;
                color: #0f172a;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
            }
            .so-browse-action-btn:hover { background: #f1f5f9; }
            .so-browse-action-menu {
                position: absolute;
                top: calc(100% + 0.25rem);
                left: 0;
                z-index: 20;
                min-width: 15.5rem;
                padding: .25rem 0;
                background: #fff;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(15, 23, 42, .12);
            }
            .so-browse-action-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .75rem;
                width: 100%;
                text-align: left;
                border: 0;
                background: transparent;
                color: #0f172a;
                font-size: 12px;
                padding: .4rem .7rem;
                cursor: pointer;
            }
            .so-browse-action-item:hover { background: #f1f5f9; }
            .so-browse-action-item .kbd {
                color: #94a3b8;
                font-size: 11px;
                white-space: nowrap;
            }
            .so-browse-action-sep {
                height: 1px;
                background: #e2e8f0;
                margin: .2rem 0;
            }
            .so-item-browse-table th.is-check,
            .so-item-browse-table td.is-check {
                width: 2.1rem;
                text-align: center;
                padding-left: .35rem;
                padding-right: .35rem;
            }
            .so-item-browse-table tr.is-focused {
                background: #eff6ff;
            }
            .so-item-browse-table tr.is-focused:hover {
                background: #dbeafe;
            }
            .so-item-browse-table tr.is-checked {
                background: #f0fdf4;
            }
            .so-item-browse-table tr.is-checked.is-focused {
                background: #dbeafe;
            }
            .so-item-browse-table td.is-check input[type="checkbox"] {
                width: 1rem;
                height: 1rem;
                cursor: pointer;
                accent-color: #2b5797;
            }
            .so-item-browse-check {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                font-size: 12px;
                color: #334155;
            }
            .so-item-browse-body {
                display: flex !important;
                flex: 1 1 auto !important;
                min-height: 0 !important;
                background: #fff;
            }
            .so-item-browse-scroll {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                min-height: 0 !important;
                overflow: auto !important;
                background: #fff;
            }
            .so-browse-filter-panel {
                display: flex !important;
                flex-direction: row !important;
                flex-shrink: 0 !important;
                border-left: 1px solid #e2e8f0 !important;
                background: #fff !important;
                min-height: 0 !important;
            }
            .so-browse-lists-stack {
                display: flex !important;
                flex-direction: column !important;
                width: 14rem !important;
                min-width: 14rem !important;
                min-height: 0 !important;
                flex: 1 1 auto !important;
            }
            .so-browse-listbox {
                display: flex !important;
                flex-direction: column !important;
                flex: 1 1 50% !important;
                min-height: 0 !important;
                border-bottom: 1px solid #e2e8f0;
            }
            .so-browse-listbox:last-child { border-bottom: 0; }
            .so-browse-listbox-caption {
                padding: .4rem .55rem;
                font-size: 11px;
                font-weight: 700;
                color: #334155;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
                text-transform: uppercase;
                letter-spacing: .02em;
            }
            .so-browse-listbox-body {
                flex: 1 1 auto !important;
                min-height: 0 !important;
                overflow-y: auto !important;
                background: #fff !important;
            }
            .so-browse-list-item {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                border: 0 !important;
                border-radius: 0 !important;
                background: transparent !important;
                color: #0f172a !important;
                font-size: 12px !important;
                font-weight: 500 !important;
                padding: .32rem .55rem !important;
                cursor: pointer !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                border-bottom: 1px solid #f1f5f9 !important;
            }
            .so-browse-list-item:hover { background: #f1f5f9 !important; }
            .so-browse-list-item.is-selected {
                background: #eff6ff !important;
                color: #1e40af !important;
                font-weight: 700 !important;
                box-shadow: inset 3px 0 0 #2b5797;
            }
            .so-browse-list-empty {
                font-size: 12px;
                color: #94a3b8;
                padding: .65rem .55rem;
            }
            .so-browse-side-tools {
                display: flex;
                flex-direction: column;
                gap: .3rem;
                padding: .4rem .3rem;
                border-left: 1px solid #e2e8f0;
                background: #f8fafc;
                flex-shrink: 0;
            }
            .so-browse-tool-btn {
                width: 1.75rem;
                height: 1.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #fff;
                color: #334155;
                padding: 0;
                cursor: pointer;
            }
            .so-browse-tool-btn:hover {
                background: #f1f5f9;
                border-color: #94a3b8;
            }
            .so-browse-tool-btn:disabled,
            .so-browse-tool-btn.is-disabled {
                opacity: .38;
                cursor: not-allowed;
                pointer-events: none;
            }
            .so-browse-action-item:disabled {
                opacity: .42;
                cursor: not-allowed;
                color: #94a3b8;
            }
            .so-browse-action-item:disabled:hover {
                background: transparent;
            }
            .so-browse-tool-btn.is-primary {
                width: 2rem;
                height: 2rem;
                border: 2px solid #1d4ed8;
                background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
                color: #fff;
                box-shadow: 0 0 0 2px rgba(37, 99, 235, .22), 0 2px 6px rgba(37, 99, 235, .35);
            }
            .so-browse-tool-btn.is-primary:hover:not(:disabled) {
                background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
                border-color: #1e40af;
                color: #fff;
                box-shadow: 0 0 0 3px rgba(37, 99, 235, .28), 0 3px 8px rgba(37, 99, 235, .4);
            }
            .so-browse-tool-btn.is-primary:disabled,
            .so-browse-tool-btn.is-primary.is-disabled {
                opacity: .45;
                border-color: #93c5fd;
                background: #bfdbfe;
                color: #fff;
                box-shadow: none;
            }
            .so-browse-tool-btn.is-primary svg {
                stroke-width: 2.2;
            }
            .so-item-browse-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }
            .so-item-browse-table thead th {
                position: sticky;
                top: 0;
                z-index: 2;
                background: #f1f5f9;
                border-bottom: 1px solid #e2e8f0;
                padding: .45rem .55rem;
                font-size: 12px;
                font-weight: 700;
                color: #334155;
                text-align: left;
                white-space: nowrap;
            }
            .so-item-browse-table thead th.is-num,
            .so-item-browse-table td.is-num { text-align: right; }
            .so-item-browse-table td {
                padding: .35rem .55rem;
                border-bottom: 1px solid #f1f5f9;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                color: #0f172a;
            }
            .so-item-browse-table td.col-desc-cell { white-space: normal; }
            .so-item-browse-table tr.is-pickable { cursor: pointer; }
            .so-item-browse-table tr.is-pickable:hover { background: #f8fafc; }
            .so-item-browse-table tr.is-disabled { opacity: .55; cursor: not-allowed; }
            .so-item-browse-empty {
                text-align: center;
                color: #64748b;
                padding: 1.25rem !important;
                font-size: 13px;
            }
            .so-item-browse-foot-chief {
                display: flex;
                flex-wrap: nowrap;
                align-items: center;
                gap: .65rem;
                padding: .65rem 4.75rem .65rem .85rem;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                flex-shrink: 0;
                overflow: visible;
            }
            .so-item-browse-foot-search {
                display: flex;
                flex-wrap: nowrap;
                align-items: center;
                gap: .5rem;
                flex: 1 1 auto;
                min-width: 0;
                position: relative;
                max-width: none;
                overflow: visible;
            }
            .so-browse-foot-label {
                flex: 0 0 auto;
                font-size: 13px;
                font-weight: 600;
                color: #334155;
                white-space: nowrap;
                overflow: visible;
            }
            .so-browse-search-ico {
                position: absolute;
                left: .55rem;
                top: 50%;
                transform: translateY(-50%);
                color: #64748b;
                flex-shrink: 0;
                pointer-events: none;
                z-index: 1;
                width: 14px;
                height: 14px;
            }
            .so-item-browse-search-bottom {
                width: 100%;
                max-width: none;
                height: 2.35rem !important;
                padding-left: .5rem !important;
                margin: 0 !important;
                box-sizing: border-box;
            }
            .so-item-browse-foot-actions {
                margin-left: auto;
                display: flex;
                flex-wrap: nowrap;
                gap: .5rem;
                align-items: center;
                flex-shrink: 0;
                position: relative;
                z-index: 2;
            }
            .so-item-browse-foot-actions .desk-btn {
                height: 2.35rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            @media (max-width: 1280px) {
                .so-expand-with-browse > .so-expand-main { max-width: 56% !important; }
                .so-expand-with-browse > .so-browse-dock { max-width: 46% !important; }
                .so-expand-with-browse .so-footer { font-size: 12px !important; }
                .so-expand-with-browse .so-totals-row { gap: 0.65rem !important; padding: 0.18rem 0 !important; }
            }
            @media (max-width: 1100px) {
                .so-expand-with-browse > .so-expand-main,
                .so-expand-with-browse > .so-browse-dock {
                    max-width: none !important;
                }
                .so-item-browse-body { min-height: 0; }
            }
            @media (max-width: 800px) {
                .so-item-browse-body { flex-direction: column; }
                .so-browse-filter-panel {
                    width: 100% !important;
                    max-height: 12rem;
                    border-left: 0 !important;
                    border-top: 1px solid #e2e8f0 !important;
                }
                .so-browse-lists-stack { width: 100% !important; min-width: 0 !important; }
                .so-browse-side-tools { flex-direction: row; border-left: 0; border-top: 1px solid #e2e8f0; }
            }
        </style>
        <aside class="so-browse-dock" role="region" aria-labelledby="item-browse-title" wire:keydown.escape.window="closeBrowse" style="flex:1 1 45%;min-width:0;min-height:0;overflow:hidden;display:flex;flex-direction:column;align-self:stretch;">
            <div class="desk-modal-head">
                    <span id="item-browse-title">Browse Items</span>
                    <span class="so-browse-head-count" wire:loading.remove wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters,loadMoreBrowseItems,refreshBrowseItems">
                        Record Count: {{ number_format($browseTotal) }}
                    </span>
                    <button type="button" wire:click="closeBrowse" class="desk-modal-close" aria-label="Close">×</button>
                </div>
                <div class="so-item-browse-toolbar">
                    @php
                        $browseCheckedCount = collect($browseCheckedIds)->map(fn ($v) => (int) $v)->filter()->unique()->count();
                        $browseCanSingle = $browseCheckedCount <= 1 && ($browseCheckedCount === 1 || (int) ($browseSelectedId ?? 0) > 0);
                        $browseCanMultiInsert = $browseCheckedCount >= 1;
                    @endphp
                    <div class="so-browse-action" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <button type="button" class="so-browse-action-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="menu">
                            Action
                            <svg viewBox="0 0 12 12" width="10" height="10" fill="currentColor" aria-hidden="true"><path d="M3 4.5L6 8l3-3.5H3z"/></svg>
                        </button>
                        <div class="so-browse-action-menu" x-show="open" x-cloak role="menu" @click="open = false">
                            <button
                                type="button"
                                class="so-browse-action-item"
                                role="menuitem"
                                wire:click="insertBrowseChecked"
                                @disabled(! $browseCanMultiInsert && ! $browseCanSingle)
                            >
                                <span>Insert All Checked Items</span>
                                <span class="kbd">Ctrl+K</span>
                            </button>
                            <button
                                type="button"
                                class="so-browse-action-item"
                                role="menuitem"
                                wire:click="insertBrowseSelected"
                                @disabled(! $browseCanSingle)
                            >
                                <span>Insert Selected Item</span>
                                <span class="kbd">Ctrl+L</span>
                            </button>
                            <div class="so-browse-action-sep" role="separator"></div>
                            <button type="button" class="so-browse-action-item" role="menuitem" wire:click="openBrowseNewItem">
                                <span>Add New Item</span>
                                <span class="kbd">Ctrl+N</span>
                            </button>
                            <button
                                type="button"
                                class="so-browse-action-item"
                                role="menuitem"
                                wire:click="openBrowseEditSelected"
                                @disabled(! $browseCanSingle)
                            >
                                <span>View/Edit Selected Item</span>
                                <span class="kbd">Ctrl+E</span>
                            </button>
                            <div class="so-browse-action-sep" role="separator"></div>
                            <button type="button" class="so-browse-action-item" role="menuitem" wire:click="closeBrowse">
                                <span>Close</span>
                            </button>
                        </div>
                    </div>
                    <label class="so-item-browse-check">
                        <input type="checkbox" wire:model.live="browseNewOnly" />
                        New only ({{ $itemNewDays }} days)
                    </label>
                    <span class="so-item-browse-count" wire:loading wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters,loadMoreBrowseItems,refreshBrowseItems,insertBrowseChecked,insertBrowseSelected,selectAllBrowseVisible">Loading…</span>
                </div>
                @if (filled($lineWarning))
                    <div
                        class="so-browse-alert so-browse-alert-{{ in_array($lineWarningKind, ['error', 'danger'], true) ? 'error' : (in_array($lineWarningKind, ['success', 'info'], true) ? 'ok' : 'warn') }}"
                        role="alert"
                    >
                        {{ $lineWarning }}
                    </div>
                @endif
                <div
                    class="so-item-browse-body"
                    wire:keydown.ctrl.k.prevent="insertBrowseChecked"
                    wire:keydown.ctrl.l.prevent="insertBrowseSelected"
                    wire:keydown.ctrl.n.prevent="openBrowseNewItem"
                    wire:keydown.ctrl.e.prevent="openBrowseEditSelected"
                    tabindex="-1"
                >
                    <div
                        class="so-item-browse-scroll"
                        tabindex="0"
                        x-data="{ clickTimer: null }"
                        @scroll.passthrough="
                            const el = $event.target;
                            if (!el || {{ $browseHasMore ? 'false' : 'true' }}) return;
                            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 120) {
                                $wire.loadMoreBrowseItems();
                            }
                        "
                    >
                        <table class="so-item-browse-table">
                            <colgroup>
                                <col style="width:2.1rem" />
                                <col style="width:7.5rem" />
                                <col />
                                <col style="width:4.5rem" />
                                <col style="width:5.75rem" />
                                <col style="width:5.5rem" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="is-check" aria-label="Check"></th>
                                    <th scope="col">Item Code</th>
                                    <th scope="col">Item Description</th>
                                    <th scope="col">U of M</th>
                                    <th scope="col" class="is-num">Available Qty</th>
                                    <th scope="col" class="is-num">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($browseRows as $bi)
                                    @php
                                        $avail = (float) $bi['available'];
                                        $itemId = (int) $bi['id'];
                                        $isChecked = collect($browseCheckedIds)->contains(fn ($v) => (int) $v === $itemId);
                                        $isFocused = (int) $browseSelectedId === $itemId;
                                    @endphp
                                    <tr
                                        wire:key="browse-item-{{ $itemId }}"
                                        class="{{ ($avail > 0 || $oversellingOn) ? 'is-pickable' : 'is-disabled' }}{{ $isFocused ? ' is-focused' : '' }}{{ $isChecked ? ' is-checked' : '' }}"
                                        @click="
                                            if ($event.target.closest('input, button, a, label')) return;
                                            clearTimeout(clickTimer);
                                            clickTimer = setTimeout(() => $wire.selectBrowseRow({{ $itemId }}), 280);
                                        "
                                        @dblclick.prevent="
                                            clearTimeout(clickTimer);
                                            clickTimer = null;
                                            $wire.pickBrowseItem({{ $itemId }});
                                        "
                                        title="Click line to select · double-click to insert"
                                    >
                                        <td class="is-check" wire:click.stop>
                                            <input
                                                type="checkbox"
                                                value="{{ $itemId }}"
                                                wire:model.live="browseCheckedIds"
                                                aria-label="Check item {{ $bi['item_code'] }}"
                                            />
                                        </td>
                                        <td class="font-mono">{{ $bi['item_code'] }}</td>
                                        <td class="col-desc-cell">{{ $bi['description'] }}</td>
                                        <td>{{ $bi['unit_of_measure'] ?: '—' }}</td>
                                        <td class="is-num {{ $avail <= 0 ? 'text-red-700 font-semibold' : '' }}">{{ number_format($avail, 0) }}</td>
                                        <td class="is-num">${{ number_format((float) $bi['list_price'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="so-item-browse-empty">
                                            <span wire:loading.remove wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters">No items found to match selected criteria.</span>
                                            <span wire:loading wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters">Loading items…</span>
                                        </td>
                                    </tr>
                                @endforelse
                                @if ($browseHasMore && count($browseRows) > 0)
                                    <tr wire:key="browse-load-more">
                                        <td colspan="6" class="so-item-browse-empty" style="padding:0.75rem !important;">
                                            <button
                                                type="button"
                                                class="desk-btn desk-btn-sm"
                                                wire:click="loadMoreBrowseItems"
                                                wire:loading.attr="disabled"
                                                wire:target="loadMoreBrowseItems"
                                            >
                                                <span wire:loading.remove wire:target="loadMoreBrowseItems">Load more items…</span>
                                                <span wire:loading wire:target="loadMoreBrowseItems">Loading…</span>
                                            </button>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="so-browse-filter-panel" aria-label="Category filters">
                        <div class="so-browse-lists-stack">
                        <div class="so-browse-listbox">
                            <div class="so-browse-listbox-caption">Category</div>
                            <div class="so-browse-listbox-body" role="listbox" aria-label="Categories">
                                <button
                                    type="button"
                                    role="option"
                                    aria-selected="{{ $browseCategoryId === null ? 'true' : 'false' }}"
                                    class="so-browse-list-item{{ $browseCategoryId === null ? ' is-selected' : '' }}"
                                    wire:click="setBrowseCategory(null)"
                                >(All)</button>
                                @foreach ($browseCategories as $cat)
                                    @php
                                        $catCode = trim((string) ($cat->code ?? ''));
                                        $catName = trim((string) ($cat->name ?? ''));
                                        $catLabel = $catCode !== '' && $catName !== ''
                                            ? strtoupper($catCode).' — '.$catName
                                            : ($catCode !== '' ? strtoupper($catCode) : $catName);
                                    @endphp
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected="{{ (int) $browseCategoryId === (int) $cat->id ? 'true' : 'false' }}"
                                        class="so-browse-list-item{{ (int) $browseCategoryId === (int) $cat->id ? ' is-selected' : '' }}"
                                        wire:click="setBrowseCategory({{ $cat->id }})"
                                        title="{{ $catLabel }}"
                                    >{{ $catLabel }}</button>
                                @endforeach
                                @if ($browseCategories->isEmpty())
                                    <div class="so-browse-list-empty">No categories</div>
                                @endif
                            </div>
                        </div>
                        <div class="so-browse-listbox">
                            <div class="so-browse-listbox-caption">Subcategory</div>
                            <div class="so-browse-listbox-body" role="listbox" aria-label="Subcategories">
                                @if (! $browseCategoryId)
                                    <div class="so-browse-list-empty">Select a category</div>
                                @else
                                    <button
                                        type="button"
                                        role="option"
                                        aria-selected="{{ $browseSubcategoryId === null ? 'true' : 'false' }}"
                                        class="so-browse-list-item{{ $browseSubcategoryId === null ? ' is-selected' : '' }}"
                                        wire:click="setBrowseSubcategory(null)"
                                    >(All)</button>
                                    @forelse ($browseSubcategories as $sub)
                                        @php
                                            $subCode = trim((string) ($sub->code ?? ''));
                                            $subName = trim((string) ($sub->name ?? ''));
                                            $subLabel = $subCode !== '' && $subName !== ''
                                                ? strtoupper($subCode).' — '.$subName
                                                : ($subCode !== '' ? strtoupper($subCode) : $subName);
                                        @endphp
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected="{{ (int) $browseSubcategoryId === (int) $sub->id ? 'true' : 'false' }}"
                                            class="so-browse-list-item{{ (int) $browseSubcategoryId === (int) $sub->id ? ' is-selected' : '' }}"
                                            wire:click="setBrowseSubcategory({{ $sub->id }})"
                                            title="{{ $subLabel }}"
                                        >{{ $subLabel }}</button>
                                    @empty
                                        <div class="so-browse-list-empty">No subcategories</div>
                                    @endforelse
                                @endif
                            </div>
                        </div>
                        </div>
                        <div class="so-browse-side-tools" aria-label="Browse tools">
                            @php
                                $sideCheckedCount = collect($browseCheckedIds)->map(fn ($v) => (int) $v)->filter()->unique()->count();
                                $sideCanSingle = $sideCheckedCount <= 1 && ($sideCheckedCount === 1 || (int) ($browseSelectedId ?? 0) > 0);
                                $sideCanAdd = $sideCheckedCount >= 1 || $sideCanSingle;
                                $sideHasRows = count($browseRows) > 0;
                            @endphp
                            <button type="button" class="so-browse-tool-btn" title="Clear filters" aria-label="Clear filters" wire:click="clearBrowseFilters">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <path d="M2 3h12l-4.5 5.5V13l-3-1.5V8.5L2 3z"/>
                                </svg>
                            </button>
                            <button type="button" class="so-browse-tool-btn" title="Refresh list" aria-label="Refresh list" wire:click="refreshBrowseItems">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path d="M13.5 8a5.5 5.5 0 1 1-1.4-3.6"/>
                                    <path d="M13.5 2.5v3.2h-3.2"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="so-browse-tool-btn"
                                title="Select all loaded items"
                                aria-label="Select all loaded items"
                                wire:click="selectAllBrowseVisible"
                                @disabled(! $sideHasRows)
                            >
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                    <rect x="2.5" y="2.5" width="11" height="11" rx="1.5"/>
                                    <path d="M5 8l2 2 4-4" stroke-width="1.6"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="so-browse-tool-btn is-primary"
                                title="Insert all checked items"
                                aria-label="Insert all checked items"
                                wire:click="insertBrowseChecked"
                                @disabled(! $sideCanAdd)
                            >
                                <svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                                    <path d="M8 3v10M3 8h10"/>
                                </svg>
                            </button>
                            <button type="button" class="so-browse-tool-btn" title="Add new item (always available)" aria-label="Add new item" wire:click="openBrowseNewItem">
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.35" aria-hidden="true">
                                    <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                                    <path d="M12.5 9v5M10 11.5h5" stroke-width="1.5"/>
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="so-browse-tool-btn"
                                title="{{ $sideCanSingle ? 'View/edit selected item' : ($sideCheckedCount > 1 ? 'Edit disabled — multiple items checked' : 'Select one item to edit') }}"
                                aria-label="View/edit selected item"
                                wire:click="openBrowseEditSelected"
                                @disabled(! $sideCanSingle)
                            >
                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="so-item-browse-foot so-item-browse-foot-chief">
                    <div class="so-item-browse-foot-search so-browse-scan-row">
                        <span class="so-browse-foot-label">Search All Items</span>
                        <div class="so-scan-bar so-browse-scan-bar">
                            <button
                                type="button"
                                wire:click="focusBrowseScan"
                                class="so-scan-btn"
                                title="Scan barcode — focus search or add on Enter"
                            >
                                <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
                                </svg>
                                <span>Scan</span>
                            </button>
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="browseSearch"
                                wire:keydown.enter.prevent="scanBrowseAndPick($event.target.value)"
                                class="so-input so-item-browse-search-bottom"
                                placeholder="Type or scan code — matching item adds to cart"
                                aria-label="Scan or search items"
                                id="so-browse-search"
                                autocomplete="off"
                            />
                        </div>
                    </div>
                    <div class="so-item-browse-foot-actions">
                        <button type="button" wire:click="closeBrowse" class="desk-btn">Close</button>
                    </div>
                </div>
        </aside>
    @endif

