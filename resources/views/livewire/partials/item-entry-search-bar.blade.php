@php
    $entryInputId = $entryInputId ?? 'item-entry';
    $entryCommit = $entryCommit ?? 'lookupItem';
    $entryClear = $entryClear ?? 'clearLookup';
    $entryFocus = $entryFocus ?? 'focusItemScan';
    $entryDisabled = $entryDisabled ?? false;
    $entryPlaceholder = $entryPlaceholder ?? 'Scan to add instantly · type name or code to search';
@endphp
<div class="so-scan-bar" role="search" style="max-width:28rem;min-width:16rem;height:2.15rem">
    <button
        type="button"
        wire:click="{{ $entryFocus }}"
        class="so-scan-btn"
        title="Scan: click to focus"
        @disabled($entryDisabled)
    >
        <svg class="so-scan-ico" viewBox="0 0 20 16" fill="none" aria-hidden="true">
            <path d="M1 1h3v14H1V1zm5 0h1.2v14H6V1zm2.5 0h2v14h-2V1zm3.5 0h1.2v14H12V1zm2.5 0h1.5v14H14.5V1zm2.8 0H19v14h-1.7V1z" fill="currentColor"/>
        </svg>
        <span>Scan</span>
    </button>
    <input
        wire:ignore.self
        type="text"
        class="so-input so-entry-input font-mono"
        id="{{ $entryInputId }}"
        data-pos-item-entry
        placeholder="{{ $entryPlaceholder }}"
        autocomplete="off"
        inputmode="text"
        @disabled($entryDisabled)
        x-data="{
            timer: null,
            lastKeyAt: 0,
            rapid: false,
            lastClaim: '',
            lastClaimAt: 0,
            inputId: {{ json_encode($entryInputId) }},
            commit: {{ json_encode($entryCommit) }},
            claim(v) {
                const n = (v || '').trim().toLowerCase();
                if (!n) return false;
                const now = Date.now();
                if (n === this.lastClaim && (now - this.lastClaimAt) < 400) return false;
                this.lastClaim = n;
                this.lastClaimAt = now;
                return true;
            },
            el() { return document.getElementById(this.inputId); },
            scheduleAuto() {
                clearTimeout(this.timer);
                const delay = this.rapid ? 0 : 400;
                this.timer = setTimeout(() => {
                    const box = this.el();
                    const v = (box?.value || '').trim();
                    if (v.length < 2) {
                        this.rapid = false;
                        $wire.searchEntryHits('');
                        return;
                    }
                    if (this.rapid) {
                        if (this.claim(v)) {
                            box.value = '';
                            $wire[this.commit](v);
                        }
                        this.rapid = false;
                        return;
                    }
                    $wire.searchEntryHits(v);
                    this.rapid = false;
                }, delay);
            },
            onKey(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                    clearTimeout(this.timer);
                    const box = this.el();
                    const v = (box?.value || '').trim();
                    if (this.rapid || this.scanLike(v)) {
                        if (box) box.value = '';
                        if (v && this.claim(v)) {
                            $wire[this.commit](v);
                        }
                        this.rapid = false;
                        return;
                    }
                    if (v) {
                        $wire.searchEntryHits(v);
                    }
                    this.rapid = false;
                    return;
                }
                if (e.key === 'F2') {
                    e.preventDefault();
                    clearTimeout(this.timer);
                    const box = this.el();
                    box?.focus();
                    box?.select?.();
                    return;
                }
                if (e.key === 'F3') {
                    e.preventDefault();
                    clearTimeout(this.timer);
                    $wire.openItemBrowse();
                    return;
                }
                const now = Date.now();
                if (this.lastKeyAt && (now - this.lastKeyAt) < 50) {
                    this.rapid = true;
                }
                this.lastKeyAt = now;
            },
            scanLike(v) {
                const s = (v || '').trim();
                return s.length >= 8 && !/\s/.test(s);
            },
            onInput() {
                this.scheduleAuto();
            }
        }"
        x-on:keydown="onKey($event)"
        x-on:input="onInput()"
        x-on:paste.prevent="
            clearTimeout(timer);
            const t = ($event.clipboardData || window.clipboardData).getData('text') || '';
            $el.value = t.replace(/[\x00-\x1F\x7F]+/g, '').trim();
            rapid = false;
            const v = ($el.value || '').trim();
            if (v.length >= 2) {
                $wire.searchEntryHits(v);
            }
        "
    />
    @unless ($entryDisabled)
        <button type="button" wire:click="{{ $entryClear }}" class="so-icon-btn" title="Clear" aria-label="Clear">
            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 3l6 6M9 3L3 9"/></svg>
        </button>
        <button
            type="button"
            x-on:click.prevent="
                const el = document.getElementById({{ json_encode($entryInputId) }});
                const v = (el?.value || '').trim();
                $wire.{{ $entryCommit }}(v);
            "
            class="so-icon-btn so-entry-add-btn"
            title="Use typed code (✓)"
            aria-label="Use item"
        >
            <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 6.5l2.5 2.5 4.5-5"/></svg>
        </button>
    @endunless
</div>
@if ($entryHits !== [])
    <div class="so-entry-hits" role="listbox" aria-label="Matching items" style="flex:1 1 100%;max-height:12rem;overflow-y:auto;background:#fff;border:1px solid #94a3b8;border-radius:6px;margin-top:.35rem">
        @foreach ($entryHits as $hit)
            <button type="button" class="so-entry-hit" wire:click="pickEntryHit({{ (int) $hit['id'] }})" wire:key="entry-hit-{{ $hit['id'] }}" style="display:flex;align-items:baseline;gap:.6rem;width:100%;padding:.42rem .7rem;text-align:left;border:0;border-bottom:1px solid #eef2f6;background:#fff;cursor:pointer;font-size:13px">
                <span class="so-entry-hit-code" style="flex:0 0 6.5rem;font-family:ui-monospace,monospace;font-weight:700">{{ $hit['item_code'] }}</span>
                <span class="so-entry-hit-desc" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#334155">{{ $hit['description'] }}</span>
                <span class="so-entry-hit-price" style="font-weight:600">${{ $hit['price'] }}</span>
            </button>
        @endforeach
    </div>
@endif
