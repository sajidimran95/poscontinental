@if ($showQueryModal)
    <style>
        .iq-backdrop {
            position: fixed !important;
            inset: 0 !important;
            z-index: 90 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 1rem !important;
            background: rgba(15, 23, 42, 0.45) !important;
        }
        .iq-modal {
            width: min(36rem, 96vw) !important;
            max-width: 36rem !important;
            height: auto !important;
            max-height: 92vh !important;
            flex: 0 0 auto !important;
            align-self: center !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }
        .iq-body { padding: .75rem .85rem; background: #fff; }
        .iq-section-title {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: .45rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .iq-row {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem .5rem;
            align-items: center;
            margin-bottom: .4rem;
        }
        .iq-select, .iq-input {
            height: 2rem !important;
            font-size: 13px !important;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 0 .45rem;
            background: #fff;
            color: #0f172a;
        }
        .iq-select-field { min-width: 11rem; flex: 1 1 10rem; }
        .iq-select-op { min-width: 9rem; flex: 0 1 9rem; }
        .iq-input-val { min-width: 7rem; flex: 1 1 7rem; }
        .iq-select-join { width: 5.25rem; }
        .iq-link {
            background: none;
            border: 0;
            color: #1d4ed8;
            font-size: 12px;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }
        .iq-list-wrap { display: flex; gap: .45rem; align-items: stretch; margin-top: .35rem; }
        .iq-list {
            flex: 1 1 auto;
            min-height: 7rem;
            max-height: 10rem;
            overflow: auto;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
        }
        .iq-list-item {
            display: block;
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: .35rem .5rem;
            font-size: 12px;
            cursor: pointer;
            color: #0f172a;
        }
        .iq-list-item:hover { background: #f1f5f9; }
        .iq-list-item.is-selected { background: #316ac5; color: #fff; }
        .iq-list-empty {
            padding: .75rem .5rem;
            color: #94a3b8;
            font-style: italic;
            font-size: 12px;
        }
        .iq-side-tools { display: flex; flex-direction: column; gap: .3rem; flex-shrink: 0; }
        .iq-tool {
            width: 1.85rem;
            height: 1.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #94a3b8;
            border-radius: 3px;
            background: linear-gradient(180deg, #fff, #e8eef7);
            color: #1e293b;
            cursor: pointer;
            padding: 0;
        }
        .iq-tool:hover { background: #dbeafe; }
        .iq-foot {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem .75rem;
            padding: .65rem .85rem;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .iq-foot-links { display: flex; flex-direction: column; gap: .25rem; flex: 1 1 auto; }
        .iq-foot-actions { margin-left: auto; display: flex; gap: .5rem; }
        .iq-status { font-size: 12px; color: #b45309; margin-top: .35rem; min-height: 1rem; }
        .iq-save-row { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
        .iq-save-row .iq-input { width: 10rem; }
        .iq-saved-select { min-width: 10rem; height: 2rem !important; font-size: 12px !important; }
    </style>
    <div class="desk-modal-backdrop iq-backdrop" wire:click.self="closeDeskQuery" role="dialog" aria-modal="true" aria-labelledby="desk-query-title">
        <div class="desk-modal iq-modal" wire:keydown.escape.window="closeDeskQuery">
            <div class="desk-modal-head">
                <span id="desk-query-title">{{ $deskQueryTitle }}</span>
                <button type="button" wire:click="closeDeskQuery" class="desk-modal-close" aria-label="Close">×</button>
            </div>
            <div class="iq-body">
                <div class="iq-section-title">Select criteria</div>
                <div class="iq-row">
                    <select wire:model.live="queryField" class="iq-select iq-select-field" aria-label="Field">
                        @foreach ($queryFields as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="queryOperator" class="iq-select iq-select-op" aria-label="Operator">
                        @foreach ($queryOperators as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if (in_array($queryOperator, ['empty', 'not_empty'], true))
                        <span class="text-slate-400 text-xs" style="min-width:7rem">—</span>
                    @else
                        @php $valueInputType = ($queryFieldTypes ?? [])[$queryField] ?? 'text'; @endphp
                        <input
                            type="{{ $valueInputType }}"
                            wire:key="dq-val-{{ $queryField }}-{{ $valueInputType }}"
                            wire:model.live="queryValue"
                            class="iq-input iq-input-val"
                            aria-label="Value"
                            placeholder="{{ $valueInputType === 'date' ? 'Date' : ($valueInputType === 'number' ? 'Amount' : 'Value') }}"
                            @if ($valueInputType === 'number') step="any" @endif
                        />
                    @endif
                </div>
                <div class="iq-row">
                    <select wire:model="queryJoin" class="iq-select iq-select-join" aria-label="And/Or" title="Join for next criterion">
                        <option value="and">And</option>
                        <option value="or">Or</option>
                    </select>
                </div>
                <div class="iq-list-wrap">
                    <div class="iq-list" role="listbox" aria-label="Search criteria">
                        @forelse ($queryCriteria as $i => $crit)
                            <button
                                type="button"
                                role="option"
                                wire:key="dq-crit-{{ $i }}"
                                class="iq-list-item{{ $querySelectedIndex === $i ? ' is-selected' : '' }}"
                                wire:click="selectQueryCriterion({{ $i }})"
                                aria-selected="{{ $querySelectedIndex === $i ? 'true' : 'false' }}"
                            >{{ $crit['label'] ?? '' }}</button>
                        @empty
                            <div class="iq-list-empty">No criteria yet. Choose field / operator / value, then click + to add.</div>
                        @endforelse
                    </div>
                    <div class="iq-side-tools" aria-label="Criteria tools">
                        <button type="button" class="iq-tool" title="Add criterion" aria-label="Add criterion" wire:click="addQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 3v10M3 8h10"/></svg>
                        </button>
                        <button type="button" class="iq-tool" title="Update selected criterion" aria-label="Update selected" wire:click="addQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M11.5 2.5l2 2L6 12H4v-2l7.5-7.5z"/></svg>
                        </button>
                        <button type="button" class="iq-tool" title="Remove selected criterion" aria-label="Remove selected" wire:click="removeQueryCriterion">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 8h10"/></svg>
                        </button>
                    </div>
                </div>
                <div class="iq-status" role="status">{{ $queryStatus }}</div>
            </div>
            <div class="iq-foot">
                <div class="iq-foot-links">
                    <div class="iq-save-row">
                        <input type="text" wire:model="querySaveName" class="iq-input" placeholder="Search name…" aria-label="Saved search name" />
                        <button type="button" class="iq-link" wire:click="saveDeskQuery">Save this search</button>
                        <button type="button" class="iq-link" wire:click="deleteSavedDeskQuery">Delete this search</button>
                    </div>
                    @if (count($savedDeskQueries) > 0)
                        <div class="iq-save-row">
                            <select class="iq-select iq-saved-select" wire:model.live="querySavedPick" aria-label="Load saved search">
                                <option value="">Load saved search…</option>
                                @foreach (array_keys($savedDeskQueries) as $savedName)
                                    <option value="{{ $savedName }}" @selected($queryLoadedName === $savedName)>{{ $savedName }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div class="iq-foot-actions">
                    <button type="button" class="desk-btn desk-btn-primary" wire:click="runDeskQuery">Search</button>
                    <button type="button" class="desk-btn" wire:click="closeDeskQuery">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif
