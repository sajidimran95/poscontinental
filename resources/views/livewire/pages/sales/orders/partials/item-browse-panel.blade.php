    @if ($showBrowse)
        <style>
            .so-browse-dock.so-browse-popup {
                position: fixed !important;
                top: 4.25rem;
                right: 1.5rem;
                left: auto;
                width: min(64rem, calc(100vw - 3rem));
                height: min(40rem, calc(100vh - 5.5rem));
                z-index: 80;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
                overflow: hidden;
                background: #fff;
                border: 1px solid #475569;
                border-radius: 6px;
                box-shadow: 0 18px 48px rgba(15, 23, 42, .38);
            }
            .so-browse-dock .desk-modal-close {
                margin-right: 0.4rem;
                padding: 0 0.45rem;
                flex-shrink: 0;
            }
            .so-browse-dock .desk-modal-head {
                display: flex !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: .5rem !important;
                flex-shrink: 0;
                cursor: move;
                user-select: none;
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
                table-layout: fixed;
                border-collapse: collapse;
                font-size: 13px;
            }
            .so-item-browse-table thead th {
                position: sticky;
                top: 0;
                z-index: 2;
                background: #e8eef6;
                border-bottom: 1px solid #c5cad3;
                padding: .42rem .55rem;
                font-size: 12px;
                font-weight: 700;
                color: #1e293b;
                text-align: left;
                white-space: nowrap;
            }
            .so-item-browse-table thead th.is-num,
            .so-item-browse-table td.is-num { text-align: right; font-variant-numeric: tabular-nums; }
            .so-item-browse-table td {
                padding: .42rem .55rem;
                border-bottom: 1px solid #e2e8f0;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                color: #0f172a;
                line-height: 1.35;
            }
            .so-item-browse-table td.col-desc-cell {
                white-space: nowrap;
            }
            .so-item-browse-table tr.is-pickable { cursor: pointer; }
            .so-item-browse-table tr.is-pickable:hover { background: #f1f5f9; }
            .so-item-browse-table tr.is-disabled { opacity: .55; cursor: not-allowed; }
            .so-item-browse-empty {
                text-align: center;
                color: #64748b;
                padding: 1.25rem !important;
                font-size: 13px;
            }
            .so-saved-search {
                position: static;
                flex: 0 0 auto;
            }
            .so-saved-search-btn {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                height: 2.35rem;
                max-width: 14rem;
                padding: 0 .7rem;
                border: 1px solid #94a3b8;
                border-radius: 4px;
                background: linear-gradient(180deg, #fff, #e8eef7);
                color: #0f172a;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
                white-space: nowrap;
            }
            .so-saved-search-btn:hover,
            .so-saved-search-btn.is-open {
                background: #dbeafe;
                border-color: #2b5797;
            }
            .so-saved-search-btn .ss-label {
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .so-cat-popup {
                position: fixed !important;
                top: 2.15rem;
                right: 0;
                left: auto;
                bottom: 0;
                z-index: 200 !important;
                display: flex !important;
                flex-direction: column !important;
                width: 17.5rem;
                height: auto !important;
                max-height: none !important;
                min-height: 0 !important;
                background: #fff;
                border: 1px solid #334155;
                border-radius: 0;
                box-shadow: -10px 0 28px rgba(15, 23, 42, .28);
                overflow: hidden;
            }
            .so-cat-popup .desk-modal-head {
                cursor: move;
                user-select: none;
                flex-shrink: 0;
            }
            .so-cat-popup .desk-modal-close {
                margin-left: auto;
            }
            .so-saved-search-menu {
                display: flex;
                flex-direction: column;
                flex: 1 1 auto;
                min-height: 0;
                width: 100%;
                background: #fff;
                overflow: hidden;
            }
            .so-saved-search-list {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                background: #fff;
            }
            .so-saved-search-item {
                display: block;
                width: 100%;
                text-align: left;
                border: 0;
                border-bottom: 1px solid #f1f5f9;
                background: transparent;
                color: #0f172a;
                font-size: 12px;
                font-weight: 600;
                padding: .28rem .55rem;
                cursor: pointer;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .so-saved-search-item.is-sub {
                padding-left: 1.35rem;
                font-weight: 500;
                color: #334155;
                background: #f8fafc;
            }
            .so-saved-search-item:hover { background: #e8f0fe; }
            .so-saved-search-item.is-selected {
                background: #316ac5;
                color: #fff;
            }
            .so-saved-search-empty {
                padding: .7rem .6rem;
                font-size: 12px;
                color: #94a3b8;
                font-style: italic;
            }
            .so-item-browse-foot-chief {
                display: flex;
                flex-wrap: nowrap;
                align-items: center;
                gap: .65rem;
                padding: .55rem .75rem;
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
            @media (max-width: 800px) {
                .so-browse-dock.so-browse-popup {
                    top: 3.75rem;
                    right: .5rem;
                    width: calc(100vw - 1rem);
                    height: min(36rem, calc(100vh - 5rem));
                }
                .so-cat-popup {
                    width: min(17.5rem, 92vw);
                }
                .so-item-browse-body { flex-direction: column; }
                .so-browse-side-tools { flex-direction: row; border-left: 0; border-top: 1px solid #e2e8f0; }
            }
        </style>
        <aside
            class="so-browse-dock so-browse-popup"
            role="dialog"
            aria-labelledby="item-browse-title"
            wire:keydown.escape.window="browseEscape"
            x-data="{
                x: (window.__soBrowsePos && window.__soBrowsePos.x != null) ? window.__soBrowsePos.x : null,
                y: (window.__soBrowsePos && window.__soBrowsePos.y != null) ? window.__soBrowsePos.y : null,
                drag: false,
                dx: 0,
                dy: 0,
                start(e) {
                    if (e.button !== 0 || e.target.closest('button, input, select, a, label')) return;
                    const r = this.$el.getBoundingClientRect();
                    this.drag = true;
                    this.dx = e.clientX - r.left;
                    this.dy = e.clientY - r.top;
                    this.x = r.left;
                    this.y = r.top;
                },
                move(e) {
                    if (!this.drag) return;
                    const w = this.$el.offsetWidth;
                    const h = this.$el.offsetHeight;
                    this.x = Math.min(window.innerWidth - 96, Math.max(8 - w + 96, e.clientX - this.dx));
                    this.y = Math.min(window.innerHeight - 48, Math.max(8, e.clientY - this.dy));
                },
                stop() {
                    if (!this.drag) return;
                    this.drag = false;
                    window.__soBrowsePos = { x: this.x, y: this.y };
                }
            }"
            :style="x === null ? {} : { left: x + 'px', top: y + 'px', right: 'auto' }"
            @mousemove.window="move($event)"
            @mouseup.window="stop()"
        >
            <div class="desk-modal-head" @mousedown="start($event)">
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
                        wire:key="so-browse-warning-{{ md5($lineWarning.'|'.$lineWarningKind) }}"
                        x-data
                        x-init="window.scheduleSoBannerDismiss && window.scheduleSoBannerDismiss('line')"
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
                                <col style="width:7.25rem" />
                                <col />
                                <col style="width:4.25rem" />
                                <col style="width:5.5rem" />
                                <col style="width:5.75rem" />
                                <col style="width:5.5rem" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="is-check" aria-label="Check"></th>
                                    <th scope="col">Item Code</th>
                                    <th scope="col">Item Description</th>
                                    <th scope="col">U of M</th>
                                    <th scope="col" class="is-num">Price</th>
                                    <th scope="col" class="is-num">Available Qty</th>
                                    <th scope="col" class="is-num">Qty in Stock</th>
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
                                        <td class="col-desc-cell" title="{{ $bi['description'] }}">{{ $bi['description'] }}</td>
                                        <td>{{ $bi['unit_of_measure'] ?: '—' }}</td>
                                        <td class="is-num">${{ number_format((float) $bi['list_price'], 2) }}</td>
                                        <td class="is-num {{ $avail <= 0 ? 'text-red-700 font-semibold' : '' }}">{{ number_format($avail, 0) }}</td>
                                        <td class="is-num">{{ number_format((float) ($bi['on_hand'] ?? $avail), 0) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="so-item-browse-empty">
                                            <span wire:loading.remove wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters">No items found to match selected criteria.</span>
                                            <span wire:loading wire:target="toggleBrowse,browseSearch,browseNewOnly,browseCategoryId,browseSubcategoryId,setBrowseCategory,setBrowseSubcategory,clearBrowseFilters">Loading items…</span>
                                        </td>
                                    </tr>
                                @endforelse
                                @if ($browseHasMore && count($browseRows) > 0)
                                    <tr wire:key="browse-load-more">
                                        <td colspan="7" class="so-item-browse-empty" style="padding:0.75rem !important;">
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
                @php
                    $savedSearchLabel = 'Saved Search';
                    $selectedCat = $browseCategoryId
                        ? $browseCategories->firstWhere('id', (int) $browseCategoryId)
                        : null;
                    if ($selectedCat) {
                        $savedCode = strtoupper(trim((string) ($selectedCat->code ?? '')));
                        $savedName = trim((string) ($selectedCat->name ?? ''));
                        $savedSearchLabel = $savedCode !== '' ? $savedCode : ($savedName !== '' ? $savedName : 'Saved Search');
                        if ($browseSubcategoryId) {
                            $selectedSub = $browseSubcategories->firstWhere('id', (int) $browseSubcategoryId);
                            if ($selectedSub) {
                                $subCode = strtoupper(trim((string) ($selectedSub->code ?? '')));
                                $savedSearchLabel .= ' / '.($subCode !== '' ? $subCode : trim((string) ($selectedSub->name ?? '')));
                            }
                        }
                    }
                @endphp
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
                    <div class="so-saved-search">
                        <button
                            type="button"
                            class="so-saved-search-btn{{ $browseSavedSearchOpen ? ' is-open' : '' }}"
                            wire:click="toggleBrowseSavedSearch"
                            aria-expanded="{{ $browseSavedSearchOpen ? 'true' : 'false' }}"
                            aria-haspopup="dialog"
                            title="Categories and saved search"
                        >
                            <span class="ss-label">{{ $savedSearchLabel }}</span>
                            <svg viewBox="0 0 12 12" width="10" height="10" fill="currentColor" aria-hidden="true"><path d="M3 4.5L6 8l3-3.5H3z"/></svg>
                        </button>
                    </div>
                    <div class="so-item-browse-foot-actions">
                        <button type="button" wire:click="closeBrowse" class="desk-btn">Close</button>
                    </div>
                </div>
        </aside>
        @if ($browseSavedSearchOpen)
        <template x-teleport="body">
        <aside
            class="so-cat-popup"
            role="dialog"
            aria-labelledby="so-cat-popup-title"
            x-data="{
                x: null,
                y: null,
                drag: false,
                dx: 0,
                dy: 0,
                pin() {
                    const menu = document.querySelector('.chief-menu');
                    const soBottom = document.querySelector('.so-bottom') || document.querySelector('.entity-footer');
                    const foot = document.querySelector('.chief-status-bar');
                    const top = menu ? Math.round(menu.getBoundingClientRect().bottom) : 34;
                    let end = window.innerHeight;
                    if (soBottom) {
                        end = Math.round(soBottom.getBoundingClientRect().top);
                    } else if (foot) {
                        end = Math.round(foot.getBoundingClientRect().top);
                    }
                    const h = Math.max(120, end - top);
                    this.$el.style.setProperty('top', top + 'px', 'important');
                    this.$el.style.setProperty('bottom', 'auto', 'important');
                    this.$el.style.setProperty('height', h + 'px', 'important');
                    this.$el.style.setProperty('max-height', h + 'px', 'important');
                    this.$el.style.setProperty('min-height', h + 'px', 'important');
                },
                start(e) {
                    if (e.button !== 0 || e.target.closest('button, input, select, a, label')) return;
                    const r = this.$el.getBoundingClientRect();
                    this.drag = true;
                    this.dx = e.clientX - r.left;
                    this.dy = e.clientY - r.top;
                    this.x = r.left;
                    this.y = r.top;
                },
                move(e) {
                    if (!this.drag) return;
                    const w = this.$el.offsetWidth;
                    this.x = Math.min(window.innerWidth - 96, Math.max(8 - w + 96, e.clientX - this.dx));
                    this.y = Math.min(window.innerHeight - 48, Math.max(8, e.clientY - this.dy));
                },
                stop() {
                    this.drag = false;
                }
            }"
            x-init="
                pin();
                const onResize = () => pin();
                window.addEventListener('resize', onResize);
                queueMicrotask(() => pin());
                return () => window.removeEventListener('resize', onResize);
            "
            :style="x === null ? {} : { left: x + 'px', top: y + 'px', right: 'auto' }"
            @mousemove.window="move($event)"
            @mouseup.window="stop()"
        >
            <div class="desk-modal-head" @mousedown="start($event)">
                <span id="so-cat-popup-title">Category</span>
                <button type="button" class="desk-modal-close" wire:click.stop="closeBrowseSavedSearch" aria-label="Close">×</button>
            </div>
            <div class="so-saved-search-menu" role="listbox" aria-label="Categories">
                <div class="so-saved-search-list">
                    <button
                        type="button"
                        role="option"
                        class="so-saved-search-item"
                        wire:click.stop="clearBrowseFilters"
                    >Clear Searches</button>
                    <button
                        type="button"
                        role="option"
                        class="so-saved-search-item{{ $browseCategoryId === null ? ' is-selected' : '' }}"
                        wire:click.stop="setBrowseCategory(null)"
                    >All Items</button>
                    @foreach ($browseCategories as $cat)
                        @php
                            $catCode = trim((string) ($cat->code ?? ''));
                            $catName = trim((string) ($cat->name ?? ''));
                            $catLabel = $catCode !== '' ? strtoupper($catCode) : $catName;
                            if ($catCode !== '' && $catName !== '' && strcasecmp($catCode, $catName) !== 0) {
                                $catLabel = strtoupper($catCode).' — '.$catName;
                            }
                            $catOpen = (int) $browseCategoryId === (int) $cat->id;
                        @endphp
                        <button
                            type="button"
                            role="option"
                            class="so-saved-search-item{{ $catOpen ? ' is-selected' : '' }}"
                            wire:click.stop="setBrowseCategory({{ $cat->id }})"
                            title="{{ $catLabel }}"
                        >{{ $catLabel }}</button>
                        @if ($catOpen)
                            <button
                                type="button"
                                role="option"
                                class="so-saved-search-item is-sub{{ $browseSubcategoryId === null ? ' is-selected' : '' }}"
                                wire:click.stop="setBrowseSubcategory(null)"
                            >(All)</button>
                            @forelse ($browseSubcategories as $sub)
                                @php
                                    $subCode = trim((string) ($sub->code ?? ''));
                                    $subName = trim((string) ($sub->name ?? ''));
                                    $subLabel = $subCode !== '' ? strtoupper($subCode) : $subName;
                                    if ($subCode !== '' && $subName !== '' && strcasecmp($subCode, $subName) !== 0) {
                                        $subLabel = strtoupper($subCode).' — '.$subName;
                                    }
                                @endphp
                                <button
                                    type="button"
                                    role="option"
                                    class="so-saved-search-item is-sub{{ (int) $browseSubcategoryId === (int) $sub->id ? ' is-selected' : '' }}"
                                    wire:click.stop="setBrowseSubcategory({{ $sub->id }})"
                                    title="{{ $subLabel }}"
                                >{{ $subLabel }}</button>
                            @empty
                                <div class="so-saved-search-empty">No subcategories</div>
                            @endforelse
                        @endif
                    @endforeach
                    @if ($browseCategories->isEmpty())
                        <div class="so-saved-search-empty">No categories</div>
                    @endif
                </div>
            </div>
        </aside>
        </template>
        @endif
    @endif

