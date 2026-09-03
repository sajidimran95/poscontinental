@props([
    'catalog' => [],
    'visibleKeys' => [],
    'locked' => null,
])

<style>
    .shf-backdrop { z-index: 90 !important; align-items: center; justify-content: center; }
    .desk-modal.shf-modal {
        width: min(36rem, calc(100vw - 2rem)) !important;
        max-width: 36rem !important;
        min-height: 24rem;
        display: flex !important;
        flex-direction: column;
        overflow: hidden;
    }
    .shf-body { padding: .85rem .9rem .7rem; background: #fff; flex: 1 1 auto; }
    .shf-grid { display: grid !important; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: .55rem .65rem; align-items: stretch; }
    .shf-col-title { font-size: 12px; font-weight: 700; color: #1e293b; margin: 0 0 .35rem; }
    .shf-list { min-height: 16rem; max-height: 20rem; overflow: auto; border: 1px solid #64748b; background: #fff; font-size: 13px; }
    .shf-list-item { display: block !important; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #e2e8f0; background: transparent; padding: .28rem .5rem; cursor: pointer; color: #0f172a; }
    .shf-list-item:hover { background: #e8f0fe; }
    .shf-list-item.is-selected { background: #316ac5; color: #fff; }
    .shf-list-empty { padding: .75rem .5rem; color: #94a3b8; font-style: italic; font-size: 12px; }
    .shf-arrows { display: flex; flex-direction: column; justify-content: center; gap: .35rem; padding-top: 1.35rem; }
    .shf-arrow { width: 2rem; height: 1.85rem; display: inline-flex; align-items: center; justify-content: center; border: 1px solid #94a3b8; border-radius: 3px; background: linear-gradient(180deg,#fff,#e8eef7); cursor: pointer; padding: 0; }
    .shf-arrow:hover { background: #dbeafe; }
    .shf-arrow:disabled { opacity: .4; cursor: not-allowed; }
    .shf-foot { display: flex; justify-content: flex-end; gap: .5rem; padding: .65rem .9rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
</style>

@php
    $labelMap = [];
    foreach ($catalog as $k => $col) {
        $labelMap[$k] = is_array($col) ? (string) ($col['label'] ?? $k) : (string) $col;
    }
    $lockedKey = $locked ?: (array_key_first($labelMap) ?: '');
@endphp

<div
    wire:ignore
    x-data="{
        pickerOpen: false,
        catalog: {{ \Illuminate\Support\Js::from($labelMap) }},
        draft: [],
        availableSelected: null,
        visibleSelected: null,
        locked: {{ \Illuminate\Support\Js::from($lockedKey) }},
        get availableKeys() {
            return Object.keys(this.catalog).filter((k) => !this.draft.includes(k));
        },
        openPicker() {
            let current = $wire.visibleColumns;
            if (typeof current === 'string') {
                try { current = JSON.parse(current); } catch (e) { current = []; }
            }
            if (!Array.isArray(current)) {
                current = current && typeof current === 'object' ? Object.values(current) : [];
            }
            current = current.filter((k) => typeof k === 'string' && this.catalog[k]);
            this.draft = current.length ? current : {{ \Illuminate\Support\Js::from($visibleKeys) }};
            if (!Array.isArray(this.draft) || !this.draft.length) {
                this.draft = this.locked ? [this.locked] : [];
            }
            this.visibleSelected = this.draft[0] || null;
            this.availableSelected = this.availableKeys[0] || null;
            this.pickerOpen = true;
        },
        closePicker() { this.pickerOpen = false; },
        showSelected() {
            const key = this.availableSelected;
            if (!key || this.draft.includes(key) || !this.catalog[key]) return;
            this.draft = [...this.draft, key];
            this.visibleSelected = key;
            this.availableSelected = this.availableKeys[0] || null;
        },
        hideSelected() {
            const key = this.visibleSelected;
            if (!key || key === this.locked) return;
            this.draft = this.draft.filter((k) => k !== key);
            if (!this.draft.length && this.locked) this.draft = [this.locked];
            this.availableSelected = this.catalog[key] ? key : (this.availableKeys[0] || null);
            this.visibleSelected = this.draft[0] || null;
        },
        move(delta) {
            const key = this.visibleSelected;
            if (!key) return;
            const i = this.draft.indexOf(key);
            const j = i + delta;
            if (i < 0 || j < 0 || j >= this.draft.length) return;
            const next = [...this.draft];
            const tmp = next[j];
            next[j] = next[i];
            next[i] = tmp;
            this.draft = next;
        },
        apply() {
            this.pickerOpen = false;
            try {
                const page = document.querySelector('table[data-col-resize]')?.getAttribute('data-col-resize') || 'list';
                localStorage.setItem('desk.cols.' + page, JSON.stringify(this.draft));
            } catch (e) {}
            $wire.applyColumnPicker(this.draft);
        }
    }"
    @open-list-fields.window="openPicker()"
    @open-item-fields.window="openPicker()"
>
    <div
        class="desk-modal-backdrop shf-backdrop"
        x-show="pickerOpen"
        x-cloak
        :style="pickerOpen ? { display: 'flex' } : { display: 'none' }"
        x-transition.opacity.duration.80ms
        @click.self="closePicker()"
        @keydown.escape.window="if (pickerOpen) closePicker()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="list-fields-title"
    >
        <div class="desk-modal shf-modal" @click.stop>
            <div class="desk-modal-head">
                <span id="list-fields-title">Show/Hide Fields</span>
                <button type="button" class="desk-modal-close" aria-label="Close" @click.prevent.stop="closePicker()">×</button>
            </div>
            <div class="shf-body">
                <div class="shf-grid">
                    <div>
                        <p class="shf-col-title">Available Fields</p>
                        <div class="shf-list" role="listbox" aria-label="Available Fields">
                            <template x-for="key in availableKeys" :key="key">
                                <button
                                    type="button"
                                    role="option"
                                    class="shf-list-item"
                                    :class="{ 'is-selected': availableSelected === key }"
                                    :aria-selected="availableSelected === key ? 'true' : 'false'"
                                    @click="availableSelected = key"
                                    @dblclick.prevent="availableSelected = key; showSelected()"
                                    x-text="catalog[key]"
                                ></button>
                            </template>
                            <div class="shf-list-empty" x-show="availableKeys.length === 0">All fields are shown.</div>
                        </div>
                    </div>
                    <div class="shf-arrows">
                        <button type="button" class="shf-arrow" title="Show field" :disabled="!availableSelected" @click="showSelected()">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 3l6 5-6 5"/></svg>
                        </button>
                        <button type="button" class="shf-arrow" title="Hide field" :disabled="!visibleSelected || visibleSelected === locked" @click="hideSelected()">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M11 3L5 8l6 5"/></svg>
                        </button>
                        <button type="button" class="shf-arrow" title="Move up" :disabled="!visibleSelected" @click="move(-1)">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 11l5-6 5 6"/></svg>
                        </button>
                        <button type="button" class="shf-arrow" title="Move down" :disabled="!visibleSelected" @click="move(1)">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 5l5 6 5-6"/></svg>
                        </button>
                    </div>
                    <div>
                        <p class="shf-col-title">Show these fields in this order</p>
                        <div class="shf-list" role="listbox" aria-label="Shown fields">
                            <template x-for="key in (Array.isArray(draft) ? draft : [])" :key="key">
                                <button
                                    type="button"
                                    role="option"
                                    class="shf-list-item"
                                    :class="{ 'is-selected': visibleSelected === key }"
                                    :aria-selected="visibleSelected === key ? 'true' : 'false'"
                                    @click="visibleSelected = key"
                                    @dblclick.prevent="visibleSelected = key; hideSelected()"
                                    x-text="catalog[key]"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div class="shf-foot">
                <button type="button" class="desk-btn desk-btn-primary" @click.prevent.stop="apply()">OK</button>
                <button type="button" class="desk-btn" @click.prevent.stop="closePicker()">Cancel</button>
            </div>
        </div>
    </div>
</div>
