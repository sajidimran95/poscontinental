document.addEventListener('DOMContentLoaded', function () {
    initDeskColResize();
    initExcelGrid();
    initPosTabUrlMemory();
    bindPosScanEntry();
    posFocusScanEntry();
    initPosTabKeepAlive();
    initDeskFastSelect();
});

document.addEventListener('livewire:navigated', function () {
    applyDeskColWidths();
    bindPosScanEntry();
    posFocusScanEntry();
});

function initDeskFastSelect() {
    if (document.documentElement.dataset.deskFastSelect === '1') {
        return;
    }
    document.documentElement.dataset.deskFastSelect = '1';

    const methodCall = (attr) => {
        const text = String(attr || '').trim();
        const m = text.match(/^([A-Za-z_]\w*)\((\d+)\)$/);
        if (! m) {
            return null;
        }

        return { method: m[1], id: parseInt(m[2], 10) };
    };

    const paintSelected = (row) => {
        const scope = row.closest('tbody, .desk-list-cards, table') || row.parentElement;
        if (scope) {
            scope.querySelectorAll('.is-selected').forEach(function (el) {
                if (el !== row) {
                    el.classList.remove('is-selected');
                }
            });
        }
        row.classList.add('is-selected');
        const radio = row.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
    };

    document.addEventListener('click', function (e) {
        const hit = e.target.closest('[wire\\:click*="selectRow("]');
        if (! hit) {
            return;
        }
        const row = hit.closest('tr, .desk-list-card') || hit;
        if (row.querySelector && row.querySelector('input[type="checkbox"]')) {
            return;
        }
        const call = methodCall(hit.getAttribute('wire:click'));
        if (! call || call.method !== 'selectRow') {
            return;
        }
        paintSelected(row);
        const wire = posWireFromEl(hit);
        if (wire && typeof wire.set === 'function') {
            e.preventDefault();
            e.stopImmediatePropagation();
            wire.set('selectedId', call.id, false);
        }
    }, true);

    document.addEventListener('dblclick', function (e) {
        const hit = e.target.closest('[wire\\:dblclick]');
        if (! hit) {
            return;
        }
        const call = methodCall(hit.getAttribute('wire:dblclick'));
        if (! call) {
            return;
        }
        const wire = posWireFromEl(hit);
        if (! wire || typeof wire.call !== 'function') {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        wire.set('selectedId', call.id, false);
        wire.call(call.method, call.id);
    }, true);
}

function posParseJsonResponse(res) {
    if (! res || ! res.ok) {
        return Promise.resolve(null);
    }
    const ct = res.headers.get('content-type') || '';
    if (ct.indexOf('json') === -1) {
        return Promise.resolve(null);
    }

    return res.json();
}

function posScanEntryEl() {
    return document.querySelector('#ss-code, #iv-code, #sc-item-entry');
}

function posWireFromEl(el) {
    if (! el || ! window.Livewire || typeof Livewire.find !== 'function') {
        return null;
    }
    const root = el.closest('[wire\\:id]');
    if (! root) {
        return null;
    }

    return Livewire.find(root.getAttribute('wire:id'));
}

function posCallScanLookup(el) {
    const w = posWireFromEl(el);
    if (! w) {
        return;
    }
    const v = (el.value || '').trim();
    if (el.id === 'sc-item-entry') {
        w.call('addItemFromEntry', v);
        return;
    }
    w.call('lookupItem', v);
}

function bindPosScanEntry() {
    if (window.__posScanEntryBound) {
        return;
    }
    window.__posScanEntryBound = true;

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') {
            return;
        }
        const el = e.target;
        if (! (el instanceof HTMLInputElement) || ! el.matches('#ss-code, #iv-code, #sc-item-entry')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        posCallScanLookup(el);
    }, true);

    let scAutoTimer = null;
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (! (el instanceof HTMLInputElement) || el.id !== 'sc-item-entry') {
            return;
        }
        window.clearTimeout(scAutoTimer);
        scAutoTimer = window.setTimeout(function () {
            const v = (el.value || '').trim();
            if (v.length < 2) {
                return;
            }
            const w = posWireFromEl(el);
            if (w) {
                w.call('autoAddEntryIfExactMatch', v);
            }
        }, 150);
    });
}

function posFocusScanEntry() {
    function run() {
        const el = posScanEntryEl();
        if (! el || el.disabled) {
            return;
        }
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') {
            return;
        }
        if (el.getBoundingClientRect().height < 1) {
            return;
        }
        const a = document.activeElement;
        if (a && a !== el && a.closest) {
            if (a.closest('.item-browse, [data-item-browse], .desk-modal, dialog, .sc-lines-table')) {
                return;
            }
        }
        el.focus();
    }

    run();
    window.requestAnimationFrame(run);
    window.setTimeout(run, 50);
    window.setTimeout(run, 250);
}

function initDeskColResize() {
    const MIN = 48;
    const MAX = 720;
    let drag = null;

    function storageKey(table) {
        return 'desk.colw.' + (table.getAttribute('data-col-resize') || 'desk');
    }

    function loadMap(table) {
        try {
            return JSON.parse(localStorage.getItem(storageKey(table)) || '{}');
        } catch (e) {
            return {};
        }
    }

    function saveMap(table, map) {
        localStorage.setItem(storageKey(table), JSON.stringify(map));
    }

    window.applyDeskColWidths = function applyDeskColWidths() {
        document.querySelectorAll('table.desk-table-resizable').forEach(function (table) {
            const map = loadMap(table);
            table.style.tableLayout = 'fixed';
            const ths = Array.from(table.querySelectorAll('thead th[data-col]'));
            ths.forEach(function (th, index) {
                const col = th.getAttribute('data-col');
                const w = map[col];
                if (! w) {
                    return;
                }
                setColumnWidth(table, index, w);
            });
        });
    };

    function setColumnWidth(table, index, w) {
        const px = w + 'px';
        const th = table.querySelectorAll('thead th')[index];
        if (th) {
            th.style.width = px;
            th.style.minWidth = px;
            th.style.maxWidth = px;
        }
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            const td = tr.cells[index];
            if (! td) {
                return;
            }
            td.style.width = px;
            td.style.minWidth = px;
            td.style.maxWidth = px;
        });
    }

    applyDeskColWidths();

    document.addEventListener('mousedown', function (e) {
        const handle = e.target.closest('.desk-col-resizer');
        if (! handle) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const th = handle.closest('th');
        const table = handle.closest('table');
        if (! th || ! table) {
            return;
        }
        drag = {
            th: th,
            table: table,
            handle: handle,
            startX: e.pageX,
            startW: th.getBoundingClientRect().width,
        };
        handle.classList.add('is-dragging');
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
    }, true);

    document.addEventListener('mousemove', function (e) {
        if (! drag) {
            return;
        }
        const w = Math.min(MAX, Math.max(MIN, drag.startW + (e.pageX - drag.startX)));
        const index = Array.from(drag.th.parentNode.children).indexOf(drag.th);
        setColumnWidth(drag.table, index, w);
    });

    document.addEventListener('mouseup', function () {
        if (! drag) {
            return;
        }
        const col = drag.th.getAttribute('data-col');
        const w = Math.round(drag.th.getBoundingClientRect().width);
        if (col) {
            const map = loadMap(drag.table);
            map[col] = w;
            saveMap(drag.table, map);
        }
        drag.handle.classList.remove('is-dragging');
        drag = null;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';
    });

    document.addEventListener('livewire:init', function () {
        if (window.Livewire && Livewire.hook) {
            Livewire.hook('morph.updated', function () {
                applyDeskColWidths();
            });
            Livewire.hook('commit', function ({ succeed }) {
                succeed(function () {
                    requestAnimationFrame(applyDeskColWidths);
                });
            });
        }
    });
}

function initExcelGrid() {
    const GRID = 'table.so-lines-table, table.so-item-browse-table, table[data-excel-grid]';
    const picks = new WeakMap();

    function isGrid(el) {
        return el && el.matches && el.matches(GRID);
    }

    function closestGrid(node) {
        return node && node.closest ? node.closest(GRID) : null;
    }

    function skipCell(cell) {
        if (! cell) {
            return true;
        }
        if (cell.matches('.col-action, .is-check, [data-excel-skip]')) {
            return true;
        }
        if (cell.getAttribute('data-col') === '_select') {
            return true;
        }
        if (cell.querySelector('input[type="radio"], input[type="checkbox"]')
            && ! cell.querySelector('a, .desk-sort-btn, input:not([type="radio"]):not([type="checkbox"]), select, textarea')) {
            return true;
        }
        if (cell.querySelector('.desk-col-resizer') && ! cell.querySelector('.desk-sort-btn')) {
            return true;
        }
        return false;
    }

    function columnIndexes(table) {
        const head = table.tHead && table.tHead.rows[0];
        if (! head) {
            return [];
        }
        const sample = bodyRows(table).find(function (tr) {
            return ! tr.classList.contains('is-empty');
        });
        const idx = [];
        Array.from(head.cells).forEach(function (th, i) {
            const td = sample ? sample.cells[i] : null;
            if (! skipCell(th) && ! skipCell(td)) {
                idx.push(i);
            }
        });
        return idx;
    }

    function cellValue(td) {
        if (td.hasAttribute('data-excel-value')) {
            return String(td.getAttribute('data-excel-value') || '').replace(/\s+/g, ' ').trim();
        }
        const field = td.querySelector('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="button"]):not([type="submit"]), select, textarea');
        if (field) {
            return String(field.value || '').replace(/\s+/g, ' ').trim();
        }
        const clone = td.cloneNode(true);
        clone.querySelectorAll('svg, .so-browse-sort-ico, .desk-sort-ico, .desk-col-resizer').forEach(function (n) {
            n.remove();
        });
        return String(clone.innerText || '').replace(/\s+/g, ' ').trim();
    }

    function cellsAt(row, indexes) {
        return indexes.map(function (i) {
            const cell = row.cells[i];
            return cell ? cellValue(cell) : '';
        });
    }

    function tsvFrom(headers, rows) {
        const lines = [];
        if (headers.length) {
            lines.push(headers.join('\t'));
        }
        rows.forEach(function (cols) {
            lines.push(cols.map(function (c) {
                return String(c).replace(/\t/g, ' ').replace(/\r?\n/g, ' ');
            }).join('\t'));
        });
        return lines.join('\r\n');
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function htmlTableFrom(headers, rows) {
        let html = '<table border="1" cellspacing="0" cellpadding="2"><thead><tr>';
        headers.forEach(function (h) {
            html += '<th>' + escapeHtml(h) + '</th>';
        });
        html += '</tr></thead><tbody>';
        rows.forEach(function (cols) {
            html += '<tr>';
            cols.forEach(function (c) {
                html += '<td>' + escapeHtml(c) + '</td>';
            });
            html += '</tr>';
        });
        return html + '</tbody></table>';
    }

    function cfHtml(tableHtml) {
        const start = '<!--StartFragment-->';
        const end = '<!--EndFragment-->';
        const inner = '<html><head><meta charset="utf-8"></head><body>' + start + tableHtml + end + '</body></html>';
        const header =
            'Version:0.9\r\n' +
            'StartHTML:00000000\r\n' +
            'EndHTML:00000000\r\n' +
            'StartFragment:00000000\r\n' +
            'EndFragment:00000000\r\n';
        let out = header + inner;
        const startHtml = header.length;
        const startFragment = startHtml + inner.indexOf(start);
        const endFragment = startHtml + inner.indexOf(end) + end.length;
        const endHtml = startHtml + inner.length;
        function pad(n) {
            return ('00000000' + n).slice(-8);
        }
        return (
            'Version:0.9\r\n' +
            'StartHTML:' + pad(startHtml) + '\r\n' +
            'EndHTML:' + pad(endHtml) + '\r\n' +
            'StartFragment:' + pad(startFragment) + '\r\n' +
            'EndFragment:' + pad(endFragment) + '\r\n' +
            inner
        );
    }

    function stashExcelSkipCols(table) {
        const stash = [];
        Array.from(table.querySelectorAll('tr')).forEach(function (row) {
            Array.from(row.cells).forEach(function (cell) {
                if (skipCell(cell)) {
                    stash.push({ parent: row, cell: cell, next: cell.nextSibling });
                    row.removeChild(cell);
                }
            });
        });
        table.__excelStash = stash;
    }

    function restoreExcelSkipCols(table) {
        const stash = table.__excelStash || [];
        stash.forEach(function (s) {
            if (s.next && s.next.parentNode === s.parent) {
                s.parent.insertBefore(s.cell, s.next);
            } else {
                s.parent.appendChild(s.cell);
            }
        });
        table.__excelStash = null;
    }

    function dropLeadingEmpty(headers, rows) {
        while (headers.length && headers[0] === '' && rows.every(function (cols) {
            return ! cols.length || cols[0] === '';
        })) {
            headers = headers.slice(1);
            rows = rows.map(function (cols) {
                return cols.slice(1);
            });
        }
        return { headers: headers, rows: rows };
    }

    function pickSet(table) {
        let set = picks.get(table);
        if (! set) {
            set = new Set();
            picks.set(table, set);
        }
        return set;
    }

    function rowKey(tr) {
        return tr.id || tr.getAttribute('data-browse-id') || String(Array.from(tr.parentNode.children).indexOf(tr));
    }

    function bodyRows(table) {
        return Array.from(table.tBodies[0] ? table.tBodies[0].rows : []);
    }

    function allowsRowDrag(table) {
        return true;
    }

    function paint(table) {
        const set = pickSet(table);
        const always = table.classList.contains('so-lines-table') || table.classList.contains('so-item-browse-table');
        bodyRows(table).forEach(function (tr) {
            tr.classList.toggle('is-excel-picked', set.has(rowKey(tr)));
            if (always || set.has(rowKey(tr))) {
                tr.setAttribute('draggable', 'true');
            } else {
                tr.removeAttribute('draggable');
            }
        });
    }

    function selectedRows(table) {
        const set = pickSet(table);
        const rows = bodyRows(table).filter(function (tr) {
            return ! tr.classList.contains('is-empty');
        });
        const picked = rows.filter(function (tr) {
            return set.has(rowKey(tr));
        });
        if (picked.length) {
            return picked;
        }
        if (table.getAttribute('data-excel-copy-all') === null) {
            const live = rows.filter(function (tr) {
                return tr.classList.contains('is-selected');
            });
            if (live.length) {
                return live;
            }
        }
        return rows;
    }

    function payload(table, rows) {
        const indexes = columnIndexes(table);
        const head = table.tHead && table.tHead.rows[0];
        let headers = head ? cellsAt(head, indexes) : [];
        let data = rows.map(function (tr) {
            return cellsAt(tr, indexes);
        }).filter(function (cols) {
            return cols.some(function (c) {
                return c !== '';
            });
        });
        const trimmed = dropLeadingEmpty(headers, data);
        headers = trimmed.headers;
        data = trimmed.rows;
        return {
            tsv: tsvFrom(headers, data),
            html: htmlTableFrom(headers, data),
        };
    }

    function writeClipboard(e, pack) {
        if (! pack.tsv) {
            return false;
        }
        e.preventDefault();
        try {
            e.clipboardData.clearData();
        } catch (err) {}
        e.clipboardData.setData('text/plain', pack.tsv);
        if (pack.html) {
            e.clipboardData.setData('text/html', pack.html);
            try {
                e.clipboardData.setData('application/vnd.ms-excel', pack.html);
            } catch (err) {}
        }
        return true;
    }

    function inEditable(el) {
        if (! el || ! el.closest) {
            return false;
        }
        const field = el.closest('input, textarea, select, [contenteditable="true"]');
        if (! field) {
            return false;
        }
        if (field.tagName === 'INPUT' && (field.type === 'button' || field.type === 'submit' || field.type === 'checkbox' || field.type === 'radio')) {
            return false;
        }
        return true;
    }

    document.addEventListener('click', function (e) {
        const tr = e.target.closest('tbody tr');
        if (! tr) {
            return;
        }
        const table = closestGrid(tr);
        if (! table) {
            return;
        }
        if (e.target.closest('input, textarea, select, button, a, label')) {
            return;
        }
        const set = pickSet(table);
        const key = rowKey(tr);
        const rows = bodyRows(table);
        if (e.shiftKey) {
            const keys = rows.map(rowKey);
            let from = -1;
            keys.forEach(function (k, i) {
                if (set.has(k)) {
                    from = i;
                }
            });
            const to = rows.indexOf(tr);
            if (from < 0) {
                from = rows.findIndex(function (r) {
                    return r.classList.contains('is-selected');
                });
            }
            if (from < 0) {
                from = to;
            }
            const a = Math.min(from, to);
            const b = Math.max(from, to);
            set.clear();
            for (let i = a; i <= b; i++) {
                set.add(rowKey(rows[i]));
            }
        } else if (e.ctrlKey || e.metaKey) {
            if (set.has(key)) {
                set.delete(key);
            } else {
                set.add(key);
            }
        } else {
            set.clear();
            set.add(key);
        }
        paint(table);
    }, true);

    document.addEventListener('keydown', function (e) {
        if (! (e.ctrlKey || e.metaKey) || e.key.toLowerCase() !== 'a') {
            return;
        }
        if (inEditable(e.target)) {
            return;
        }
        const table = closestGrid(e.target) || document.querySelector(GRID);
        if (! table) {
            return;
        }
        e.preventDefault();
        const set = pickSet(table);
        set.clear();
        bodyRows(table).forEach(function (tr) {
            set.add(rowKey(tr));
        });
        paint(table);
    });

    function activeGrid(from) {
        const near = closestGrid(from);
        if (near) {
            return near;
        }
        const picked = document.querySelector('tr.is-excel-picked');
        if (picked) {
            return picked.closest('table');
        }
        const selected = document.querySelector('table.so-lines-table tr.is-selected, table[data-excel-grid] tr.is-selected');
        if (selected) {
            return selected.closest('table');
        }
        return document.querySelector('table.so-lines-table[data-excel-grid], table[data-excel-grid]');
    }

    document.addEventListener('copy', function (e) {
        if (inEditable(e.target)) {
            const sel = String(window.getSelection ? window.getSelection() : '');
            const field = e.target;
            if (field && typeof field.selectionStart === 'number' && field.selectionStart !== field.selectionEnd) {
                return;
            }
            if (sel.length) {
                return;
            }
        }
        const table = activeGrid(e.target);
        if (! table || ! isGrid(table)) {
            return;
        }
        writeClipboard(e, payload(table, selectedRows(table)));
    });

    document.addEventListener('dragstart', function (e) {
        const tr = e.target.closest && e.target.closest('tbody tr');
        if (! tr || ! e.dataTransfer) {
            return;
        }
        const table = closestGrid(tr);
        if (! table || ! allowsRowDrag(table)) {
            e.preventDefault();
            return;
        }
        if (e.target.closest('input, textarea, select, button')) {
            e.preventDefault();
            return;
        }
        const set = pickSet(table);
        const key = rowKey(tr);
        if (! set.has(key)) {
            set.clear();
            set.add(key);
            paint(table);
        }
        const pack = payload(table, selectedRows(table));
        stashExcelSkipCols(table);
        e.dataTransfer.effectAllowed = 'copy';
        try {
            e.dataTransfer.clearData();
        } catch (err) {}
        e.dataTransfer.setData('text/plain', pack.tsv);
        e.dataTransfer.setData('text', pack.tsv);
        if (pack.html) {
            e.dataTransfer.setData('text/html', pack.html);
            try {
                e.dataTransfer.setData('HTML Format', cfHtml(pack.html));
                e.dataTransfer.setData('application/vnd.ms-excel', pack.html);
            } catch (err) {}
        }
        table.setAttribute('data-excel-dragging', '1');
    }, true);

    document.addEventListener('dragend', function (e) {
        document.querySelectorAll('table[data-excel-dragging]').forEach(function (table) {
            restoreExcelSkipCols(table);
            table.removeAttribute('data-excel-dragging');
        });
    }, true);

    window.posExcelCopyRowIndex = function (rowIdPrefix, index) {
        const tr = document.getElementById(rowIdPrefix + index);
        if (! tr) {
            return;
        }
        const table = tr.closest(GRID);
        const set = pickSet(table);
        set.clear();
        set.add(rowKey(tr));
        paint(table);
        const pack = payload(table, [tr]);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(pack.tsv);
        }
    };

    function enableDragRows() {
        document.querySelectorAll(GRID).forEach(function (table) {
            paint(table);
        });
    }

    document.addEventListener('livewire:init', function () {
        if (window.Livewire && Livewire.hook) {
            Livewire.hook('morph.updated', enableDragRows);
        }
    });
    document.addEventListener('livewire:navigated', enableDragRows);
    enableDragRows();
}

(function initListStayPut() {
    const SCROLL = '.desk-grid, .so-item-browse-scroll, .so-items-grid';

    function restoreDesc() {
        const map = window.__itemListDescExpanded || {};
        document.querySelectorAll('[data-desc-expand]').forEach(function (btn) {
            const key = btn.getAttribute('data-desc-expand');
            btn.classList.toggle('is-open', !!map[key]);
        });
        const browse = window.__browseDescExpanded || {};
        document.querySelectorAll('[data-browse-desc]').forEach(function (btn) {
            const id = btn.getAttribute('data-browse-desc');
            btn.classList.toggle('is-open', !!browse[Number(id)]);
        });
    }

    document.addEventListener('click', function (e) {
        const browseBtn = e.target.closest('[data-browse-desc]');
        if (browseBtn) {
            e.preventDefault();
            e.stopPropagation();
            const id = Number(browseBtn.getAttribute('data-browse-desc'));
            const map = window.__browseDescExpanded || {};
            map[id] = ! map[id];
            window.__browseDescExpanded = map;
            browseBtn.classList.toggle('is-open', !!map[id]);
            return;
        }
        const btn = e.target.closest('[data-desc-expand]');
        if (! btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const key = btn.getAttribute('data-desc-expand');
        const listMap = window.__itemListDescExpanded || {};
        listMap[key] = ! listMap[key];
        window.__itemListDescExpanded = listMap;
        btn.classList.toggle('is-open', !!listMap[key]);
    }, true);

    document.addEventListener('livewire:init', function () {
        if (! window.Livewire || ! Livewire.hook) {
            return;
        }
        Livewire.hook('commit', function ({ succeed }) {
            const saved = Array.from(document.querySelectorAll(SCROLL)).map(function (el) {
                return { el: el, top: el.scrollTop, left: el.scrollLeft };
            });
            succeed(function () {
                requestAnimationFrame(function () {
                    saved.forEach(function (s) {
                        if (s.el.isConnected) {
                            s.el.scrollTop = s.top;
                            s.el.scrollLeft = s.left;
                        }
                    });
                    restoreDesc();
                    if (typeof applyDeskColWidths === 'function') {
                        applyDeskColWidths();
                    }
                });
            });
        });
        Livewire.hook('morph.updated', restoreDesc);
    });
})();

function initPosTabUrlMemory() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const endpoint = document.querySelector('meta[name="pos-tab-remember"]')?.getAttribute('content') || '';
    if (! endpoint) {
        return;
    }

    let last = '';
    function remember() {
        const url = window.location.pathname + window.location.search;
        if (url === last) {
            return;
        }
        last = url;
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ url: url }),
                credentials: 'same-origin',
            });
        } catch (e) {}
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.chief-tab-link, .chief-tab-close, a[href*="pos/tabs"]')) {
            remember();
        }
    }, true);

    window.addEventListener('popstate', remember);
    document.addEventListener('livewire:navigated', remember);
    document.addEventListener('livewire:init', function () {
        if (window.Livewire && Livewire.hook) {
            Livewire.hook('commit', function ({ succeed }) {
                succeed(remember);
            });
        }
    });
    remember();
}

window.__posDownloadXlsx = function (url) {
    if (! url) {
        return;
    }
    const frame = document.createElement('iframe');
    frame.setAttribute('hidden', 'hidden');
    frame.setAttribute('aria-hidden', 'true');
    frame.src = url;
    document.body.appendChild(frame);
    window.setTimeout(function () {
        frame.remove();
    }, 120000);
};

function posDeskKey(href) {
    let u;
    try {
        u = new URL(href, window.location.origin);
    } catch (e) {
        return '/home';
    }
    u.searchParams.delete('pos_embed');
    let p = u.pathname.replace(/\/+$/, '') || '/home';
    if (p === '/') {
        p = '/home';
    }
    if (p.indexOf('/sales/orders/create') !== -1) {
        return 'so:' + (u.searchParams.get('w') || 'active');
    }
    if (/\/create$/.test(p)) {
        return p;
    }
    const rec = p.match(/^(.+)\/(\d+)(?:\/(?:edit|show|print))?$/);
    if (rec) {
        return rec[1] + '/edit';
    }
    const uuid = p.match(/^(.+)\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?:\/(?:edit|show))?$/i);
    if (uuid) {
        return uuid[1] + '/edit';
    }
    return p || '/home';
}

function posCleanHref(href) {
    const u = new URL(href, window.location.origin);
    u.searchParams.delete('pos_embed');
    return u.pathname + u.search + u.hash;
}

function posEmbedSrc(href) {
    const u = new URL(href, window.location.origin);
    u.searchParams.set('pos_embed', '1');
    return u.pathname + u.search + u.hash;
}

function posIsTabsOpen(href) {
    try {
        return new URL(href, window.location.origin).pathname.indexOf('/pos/tabs/open') !== -1;
    } catch (e) {
        return false;
    }
}

function initPosTabKeepAlive() {
    const host = document.querySelector('.pos-frame-host');
    const inIframe = window.parent !== window;

    if (inIframe) {
        const selfHref = posCleanHref(window.location.href);
        const selfDeskKey = posDeskKey(selfHref);

        function withEmbed(href) {
            const u = new URL(href, window.location.origin);
            u.searchParams.set('pos_embed', '1');
            return u.pathname + u.search + u.hash;
        }

        function leaveDeskMessage(href) {
            let to = href;
            try {
                const u = new URL(href, window.location.origin);
                to = u.pathname + u.search + u.hash;
            } catch (err) {}
            const fromKey = posDeskKey(window.location.href);
            const toKey = posDeskKey(to);
            const msg = { type: 'pos-desk-open', href: to };
            const leavingDoc = /\/(edit|create|show)$/.test(fromKey) || fromKey.indexOf('so:') === 0;
            const stayingOnCreate = fromKey.indexOf('so:') === 0 && toKey.indexOf('so:') === 0;
            if (leavingDoc && toKey !== fromKey && ! stayingOnCreate && ! /\/(edit|create)$/.test(toKey)) {
                msg.close_desk = fromKey;
            } else if (toKey !== fromKey) {
                msg.restore = selfHref;
            }
            return msg;
        }

        document.addEventListener('click', function (e) {
            const a = e.target.closest('a[href]');
            if (! a || a.target === '_blank' || a.hasAttribute('download')) {
                return;
            }
            const raw = a.getAttribute('href') || '';
            if (raw.startsWith('#') || raw.toLowerCase().startsWith('javascript:')) {
                return;
            }
            let href;
            try {
                href = new URL(a.href, window.location.origin);
            } catch (err) {
                return;
            }
            if (href.origin !== window.location.origin) {
                return;
            }
            if (posIsTabsOpen(href.href) || posDeskKey(href.href) !== posDeskKey(window.location.href)) {
                e.preventDefault();
                e.stopPropagation();
                window.parent.postMessage(leaveDeskMessage(href.href), window.location.origin);
                return;
            }
            if (! href.searchParams.has('pos_embed')) {
                e.preventDefault();
                e.stopPropagation();
                const next = withEmbed(href.href);
                if (window.Livewire && typeof Livewire.navigate === 'function') {
                    Livewire.navigate(next);
                } else {
                    window.location.href = next;
                }
            }
        }, true);
        document.addEventListener('livewire:navigate', function (event) {
            const detail = event.detail || {};
            let next = '';
            if (detail.url) {
                next = typeof detail.url === 'string' ? detail.url : (detail.url.href || '');
            } else if (detail.href) {
                next = detail.href;
            }
            if (! next) {
                return;
            }
            try {
                const u = new URL(next, window.location.origin);
                // Leaving list for edit (or any other desk) must open in parent — never replace this iframe.
                if (posDeskKey(u.href) !== posDeskKey(window.location.href)) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.parent.postMessage(leaveDeskMessage(u.pathname + u.search + u.hash), window.location.origin);
                    return;
                }
                if (! u.searchParams.has('pos_embed')) {
                    event.preventDefault();
                    u.searchParams.set('pos_embed', '1');
                    if (window.Livewire && typeof Livewire.navigate === 'function') {
                        Livewire.navigate(u.pathname + u.search + u.hash);
                    }
                }
            } catch (err) {}
        });
        document.addEventListener('livewire:navigated', function () {
            const nowHref = window.location.pathname + window.location.search + window.location.hash;
            if (posDeskKey(nowHref) === selfDeskKey) {
                return;
            }
            window.parent.postMessage(leaveDeskMessage(nowHref), window.location.origin);
        });
        document.addEventListener('livewire:init', function () {
            if (! window.Livewire || ! Livewire.on) {
                return;
            }
            Livewire.on('pos-return-list', function (payload) {
                const data = payload && payload.listUrl ? payload : (payload && payload[0] ? payload[0] : payload || {});
                window.parent.postMessage({
                    type: 'pos-return-list',
                    list_url: data.listUrl || data.list_url || '',
                    close_desk: data.closeDesk || data.close_desk || selfDeskKey,
                    message: data.message || '',
                }, window.location.origin);
            });
        });
        return;
    }

    if (! host) {
        return;
    }

    const frames = new Map();
    let currentDeskKey = posDeskKey(window.location.href);
    let deskEpoch = 0;
    const boot = document.getElementById('pos-boot-slot');
    if (boot) {
        const bootKey = boot.getAttribute('data-desk-key') || posDeskKey(window.location.href);
        boot.setAttribute('data-desk-key', bootKey);
        frames.set(bootKey, boot);
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function setActiveTab(key) {
        const homeActive = key === '/home' || key === '/';
        document.querySelectorAll('.chief-tab[data-desk-key]').forEach(function (el) {
            const isHome = el.hasAttribute('data-desk-home');
            const active = homeActive
                ? isHome
                : (! isHome && el.getAttribute('data-desk-key') === key);
            el.classList.toggle('chief-tab-active', active);
        });
        syncWindowMenu(key);
    }

    function syncWindowMenu(activeKey) {
        const menu = document.querySelector('[data-pos-window-menu]');
        if (! menu) {
            return;
        }
        if (activeKey === undefined || activeKey === null) {
            const activeTab = document.querySelector('.chief-tab.chief-tab-active[data-desk-key]');
            activeKey = activeTab ? activeTab.getAttribute('data-desk-key') : '/home';
        }
        const homeActive = activeKey === '/home' || activeKey === '/';
        const homeLink = menu.querySelector('[data-pos-window-home]');
        if (homeLink) {
            homeLink.classList.toggle('font-semibold', homeActive);
            homeLink.classList.toggle('bg-sky-50', homeActive);
            homeLink.textContent = homeActive ? '✓ Home' : 'Home';
        }

        const tabs = Array.from(document.querySelectorAll('.chief-tab[data-desk-key]:not([data-desk-home])'));
        const listWrap = menu.querySelector('[data-pos-window-list]');
        const itemsHost = menu.querySelector('[data-pos-window-items]');
        if (itemsHost) {
            itemsHost.innerHTML = '';
            tabs.forEach(function (tab, index) {
                const link = tab.querySelector('a.chief-tab-link');
                if (! link) {
                    return;
                }
                const key = tab.getAttribute('data-desk-key') || '';
                const isActive = ! homeActive && key === activeKey;
                const a = document.createElement('a');
                a.href = link.getAttribute('href') || '#';
                a.className = 'block px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap'
                    + (isActive ? ' font-semibold bg-sky-50' : '');
                a.setAttribute('role', 'menuitem');
                a.setAttribute('data-pos-window-item', '');
                a.textContent = (isActive ? '✓ ' : '') + (index + 1) + '. ' + (link.textContent || 'Window').trim();
                itemsHost.appendChild(a);
            });
        }
        if (listWrap) {
            listWrap.hidden = tabs.length === 0;
        }

        const countEl = menu.querySelector('[data-pos-window-count]');
        const max = countEl ? (parseInt(countEl.getAttribute('data-max') || '9', 10) || 9) : 9;
        if (countEl) {
            countEl.textContent = tabs.length + '/' + max + ' windows';
        }

        const closeWrap = menu.querySelector('[data-pos-window-close-all-wrap]');
        if (closeWrap) {
            if (tabs.length > 0) {
                if (! closeWrap.querySelector('[data-pos-window-close-all]')) {
                    const closeAllBar = document.querySelector('.chief-tabs .chief-tab-close-all-form');
                    const action = closeAllBar ? closeAllBar.getAttribute('action') : '/pos/tabs/close-all';
                    closeWrap.innerHTML = '<form method="POST" action="' + String(action).replace(/"/g, '&quot;') + '" class="m-0" data-pos-window-close-all>'
                        + '<input type="hidden" name="_token" value="' + csrf().replace(/"/g, '&quot;') + '">'
                        + '<button type="submit" class="block w-full text-left px-3 py-1.5 hover:bg-sky-100 whitespace-nowrap" role="menuitem">Close All</button>'
                        + '</form>';
                }
            } else {
                closeWrap.innerHTML = '<button type="button" class="chief-menu-item-disabled block w-full text-left" role="menuitem" disabled data-pos-window-close-all-disabled>Close All</button>';
            }
        }
    }

    function rememberParentUrl() {
        const endpoint = document.querySelector('meta[name="pos-tab-remember"]')?.getAttribute('content') || '';
        if (! endpoint) {
            return;
        }
        const url = window.location.pathname + window.location.search;
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ url: url }),
                credentials: 'same-origin',
            });
        } catch (e) {}
    }

    function iframeLocationHref(iframe) {
        if (! iframe || iframe.tagName !== 'IFRAME') {
            return '';
        }
        try {
            const loc = iframe.contentWindow && iframe.contentWindow.location;
            if (! loc || loc.href === 'about:blank') {
                return '';
            }
            return loc.pathname + loc.search + loc.hash;
        } catch (err) {
            return iframe.getAttribute('src') || '';
        }
    }

    function showFrame(href, opts) {
        opts = opts || {};
        const clean = posCleanHref(href);
        const key = posDeskKey(clean);
        let iframe = frames.get(key);
        if (! iframe) {
            iframe = document.createElement('iframe');
            iframe.className = 'pos-keep-frame';
            iframe.setAttribute('data-desk-key', key);
            iframe.title = 'Document';
            iframe.src = posEmbedSrc(clean);
            host.appendChild(iframe);
            frames.set(key, iframe);
        } else if (iframe.tagName === 'IFRAME') {
            const cur = posCleanHref(iframeLocationHref(iframe) || iframe.getAttribute('src') || '');
            const curKey = cur ? posDeskKey(cur) : '';
            const want = clean.split('#')[0];
            const have = cur.split('#')[0];
            // List iframe must not stay on edit; edit tab reloads when opening another record.
            if (curKey !== key || have !== want) {
                iframe.src = posEmbedSrc(clean);
            }
        }
        frames.forEach(function (f, k) {
            f.classList.toggle('is-active', k === key);
        });
        currentDeskKey = key;
        setActiveTab(key);
        window.setTimeout(function () {
            try {
                if (iframe.tagName === 'IFRAME' && iframe.contentWindow) {
                    iframe.contentWindow.focus();
                } else if (typeof iframe.focus === 'function') {
                    iframe.focus();
                }
            } catch (err) {}
        }, 0);
        const next = clean.split('#')[0];
        if (opts.history !== false && (window.location.pathname + window.location.search) !== next) {
            window.history.pushState({ posDesk: key }, '', next);
        }
        rememberParentUrl();
        persistDocTab(clean);
    }

    function closeDeskFrame(key) {
        if (! key || key === '/home') {
            return;
        }
        deskEpoch += 1;
        const removeKeys = [];
        frames.forEach(function (iframe, k) {
            const iframeKey = (iframe && iframe.getAttribute) ? (iframe.getAttribute('data-desk-key') || k) : k;
            if (k === key || iframeKey === key) {
                iframe.remove();
                removeKeys.push(k);
            }
        });
        removeKeys.forEach(function (k) {
            frames.delete(k);
        });
        document.querySelectorAll('.chief-tab[data-desk-key]:not([data-desk-home])').forEach(function (tab) {
            const tabKey = tab.getAttribute('data-desk-key') || '';
            const link = tab.querySelector('a.chief-tab-link');
            const hrefKey = (link && link.href) ? posDeskKey(link.href) : '';
            if (tabKey === key || hrefKey === key) {
                tab.remove();
            }
        });
        ensureCloseAllButton();
    }

    function persistDocTab(href) {
        const key = posDeskKey(href);
        if (key === '/home' || key === '/' || (key && key.indexOf('so:') === 0)) {
            return;
        }
        const existingTab = document.querySelector('.chief-tab[data-desk-key="' + String(key).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
        if (existingTab) {
            setActiveTab(currentDeskKey);
            return;
        }
        const epoch = deskEpoch;
        const endpoint = document.querySelector('meta[name="pos-tab-ensure"]')?.getAttribute('content') || '';
        if (! endpoint) {
            ensureDocTab({ url: href, label: 'Window' });
            setActiveTab(currentDeskKey);
            return;
        }
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ url: href }),
            credentials: 'same-origin',
        }).then(posParseJsonResponse).then(function (data) {
            if (epoch !== deskEpoch) {
                return;
            }
            if (data && data.ok && data.url) {
                ensureDocTab(data);
            }
            setActiveTab(currentDeskKey);
        }).catch(function () {
            if (epoch !== deskEpoch) {
                return;
            }
            ensureDocTab({ url: href, label: 'Window' });
            setActiveTab(currentDeskKey);
        });
    }

    // Repair desk-keys if a document tab href/url was previously collapsed to Home or mixed with edit.
    document.querySelectorAll('.chief-tab[data-desk-key]:not([data-desk-home])').forEach(function (el) {
        const link = el.querySelector('a.chief-tab-link');
        if (! link || ! link.href) {
            return;
        }
        const hrefKey = posDeskKey(link.href);
        let key = el.getAttribute('data-desk-key') || '';
        if (hrefKey && hrefKey !== '/home') {
            // List tab must not keep an edit URL
            if (key && ! /\/edit$/.test(key) && /\/edit$/.test(hrefKey)) {
                link.setAttribute('href', key);
                el.setAttribute('data-desk-key', key);
                return;
            }
            el.setAttribute('data-desk-key', hrefKey);
        }
    });

    function ensureDocTab(data) {
        if (! data || ! data.url) {
            return;
        }
        const key = posDeskKey(data.url);
        let existing = null;
        if (data.id) {
            existing = document.querySelector('.chief-tab[data-tab-id="' + String(data.id).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
        }
        if (! existing) {
            existing = document.querySelector('.chief-tab[data-desk-key="' + key.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]:not([data-desk-home])');
        }
        if (existing) {
            existing.setAttribute('data-desk-key', key);
            const link = existing.querySelector('.chief-tab-link');
            if (link && data.label) {
                link.textContent = data.label;
            }
            if (data.id) {
                existing.setAttribute('data-tab-id', data.id);
            }
            const a = existing.querySelector('a.chief-tab-link');
            if (a && data.url) {
                a.setAttribute('href', data.url);
            }
            const form = existing.querySelector('form.chief-tab-close-form');
            if (form && data.close_url) {
                form.setAttribute('action', data.close_url);
            }
            syncWindowMenu();
            setActiveTab(currentDeskKey);
            return;
        }
        const bar = document.querySelector('.chief-tabs');
        if (! bar) {
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'chief-tab';
        wrap.setAttribute('data-desk-key', key);
        if (data.id) {
            wrap.setAttribute('data-tab-id', data.id);
        }
        wrap.innerHTML = '<a href="' + String(data.url).replace(/"/g, '&quot;') + '" class="chief-tab-link"></a>'
            + '<form method="POST" action="' + String(data.close_url || '').replace(/"/g, '&quot;') + '" class="chief-tab-close-form">'
            + '<input type="hidden" name="_token" value="' + csrf().replace(/"/g, '&quot;') + '">'
            + '<button type="submit" class="chief-tab-close" title="Close" aria-label="Close">×</button></form>';
        wrap.querySelector('.chief-tab-link').textContent = data.label || 'Window';
        const closeAll = bar.querySelector('.chief-tab-close-all-form');
        if (closeAll) {
            bar.insertBefore(wrap, closeAll);
        } else {
            bar.appendChild(wrap);
        }
        ensureCloseAllButton();
        setActiveTab(currentDeskKey);
    }

    function syncSoTabs(windows) {
        if (! Array.isArray(windows)) {
            return;
        }
        const keep = {};
        windows.forEach(function (win) {
            if (! win || ! win.url) {
                return;
            }
            keep[posDeskKey(win.url)] = true;
            ensureDocTab(win);
        });
        document.querySelectorAll('.chief-tab[data-desk-key^="so:"]').forEach(function (tab) {
            const key = tab.getAttribute('data-desk-key');
            if (! keep[key]) {
                tab.remove();
                if (frames.has(key)) {
                    frames.get(key).remove();
                    frames.delete(key);
                }
            }
        });
        // Re-order SO tabs to match server serial (1,2,3…)
        const bar = document.querySelector('.chief-tabs');
        if (! bar) {
            return;
        }
        const closeAll = bar.querySelector('.chief-tab-close-all-form');
        windows.forEach(function (win) {
            if (! win || ! win.url) {
                return;
            }
            const key = posDeskKey(win.url);
            const tab = document.querySelector('.chief-tab[data-desk-key="' + key.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
            if (! tab) {
                return;
            }
            if (closeAll) {
                bar.insertBefore(tab, closeAll);
            } else {
                bar.appendChild(tab);
            }
        });
        ensureCloseAllButton();
    }

    function ensureCloseAllButton() {
        const bar = document.querySelector('.chief-tabs');
        if (! bar) {
            return;
        }
        const tabCount = document.querySelectorAll('.chief-tab[data-desk-key]:not([data-desk-home])').length;
        let closeAll = bar.querySelector('.chief-tab-close-all-form');
        
        if (tabCount > 0 && ! closeAll) {
            closeAll = document.createElement('form');
            closeAll.method = 'POST';
            closeAll.action = '/pos/tabs/close-all';
            closeAll.className = 'chief-tab-close-all-form';
            closeAll.style.cssText = 'display:inline-flex;align-self:stretch;margin:0;margin-left:auto;height:100%;position:sticky;right:0;background:var(--chief-chrome);z-index:10;';
            closeAll.innerHTML = '<input type="hidden" name="_token" value="' + csrf().replace(/"/g, '&quot;') + '">'
                + '<button type="submit" class="chief-tab-close-all" title="Close all windows" aria-label="Close all windows" '
                + 'style="display:inline-flex;align-items:center;justify-content:center;align-self:stretch;box-sizing:border-box;height:100%;min-width:auto;padding:0 0.85rem;margin:0;border:none;border-left:1px solid #94a3b8;border-radius:0;background:#f1f5f9;color:#334155;font-size:12px;font-weight:700;line-height:1;cursor:pointer;flex:0 0 auto;white-space:nowrap;">Close all</button>';
            bar.appendChild(closeAll);
        } else if (tabCount === 0 && closeAll) {
            closeAll.remove();
        }
        syncWindowMenu();
    }

    let openQueue = Promise.resolve();
    let soPending = 0;

    function setSoAddBusy(busy) {
        const btn = document.querySelector('.chief-tab-add');
        if (btn) {
            btn.disabled = !! busy;
            btn.style.opacity = busy ? '0.55' : '';
            btn.style.pointerEvents = busy ? 'none' : '';
        }
    }

    async function openHref(href) {
        if (! href) {
            return;
        }
        const abs = new URL(href, window.location.origin);
        const isSoOpen = posIsTabsOpen(abs.href) && abs.searchParams.get('route') === 'sales.orders.create';

        if (isSoOpen) {
            soPending += 1;
            setSoAddBusy(true);
        }

        openQueue = openQueue.then(async function () {
            if (posIsTabsOpen(abs.href)) {
                try {
                    const res = await fetch(abs.href, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });
                    const data = await posParseJsonResponse(res);
                    if (data && data.limit) {
                        window.showPosTabLimit && window.showPosTabLimit();
                        if (data.windows) {
                            syncSoTabs(data.windows);
                        }
                        if (data.url) {
                            showFrame(data.url);
                        }
                        return;
                    }
                    if (data && data.ok && data.url) {
                        if (data.kind === 'so' && data.windows) {
                            syncSoTabs(data.windows);
                        } else {
                            ensureDocTab(data);
                        }
                        showFrame(data.url);
                        return;
                    }
                    if (data && data.home) {
                        showFrame('/home');
                        return;
                    }
                } catch (err) {
                    window.location.href = abs.href;
                }
                return;
            }
            showFrame(abs.href);
        }).catch(function () {}).finally(function () {
            if (isSoOpen) {
                soPending = Math.max(0, soPending - 1);
                if (soPending === 0) {
                    setSoAddBusy(false);
                }
            }
        });

        return openQueue;
    }

    window.__posOpenHref = function (href) {
        openHref(href);
    };

    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) {
            return;
        }
        if (e.data && e.data.type === 'pos-desk-open' && e.data.href) {
            if (e.data.close_desk) {
                closeDeskFrame(e.data.close_desk);
            }
            if (e.data.restore) {
                const restoreKey = posDeskKey(e.data.restore);
                const drifted = frames.get(restoreKey);
                if (drifted && drifted.tagName === 'IFRAME') {
                    const cur = iframeLocationHref(drifted) || drifted.getAttribute('src') || '';
                    if (posDeskKey(cur) !== restoreKey) {
                        drifted.src = posEmbedSrc(e.data.restore);
                    }
                }
            }
            openHref(e.data.href);
            return;
        }
        if (e.data && e.data.type === 'pos-return-list') {
            if (e.data.close_desk) {
                closeDeskFrame(e.data.close_desk);
            }
            if (e.data.list_url) {
                showFrame(e.data.list_url);
            }
            if (e.data.message) {
                window.showPosSaveToast && window.showPosSaveToast(e.data.message);
            }
            return;
        }
        if (e.data && e.data.type === 'pos-so-after-save') {
            if (e.data.closed_window) {
                closeDeskFrame('so:' + e.data.closed_window);
            }
            if (e.data.windows) {
                syncSoTabs(e.data.windows);
            }
            if (e.data.edit && e.data.edit.url) {
                ensureDocTab(e.data.edit);
                showFrame(e.data.edit.url);
                return;
            }
            if (e.data.next_url) {
                showFrame(e.data.next_url);
                return;
            }
            const nextSo = document.querySelector('.chief-tab[data-desk-key^="so:"] a.chief-tab-link');
            if (nextSo) {
                showFrame(nextSo.href);
            }
            return;
        }
    });

    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (! a || a.target === '_blank' || a.hasAttribute('download')) {
            return;
        }
        if (a.closest('[wire\\:id]')) {
            return;
        }
        const rawHref = a.getAttribute('href') || '';
        if (rawHref.startsWith('#') || rawHref.toLowerCase().startsWith('javascript:')) {
            return;
        }
        let href;
        try {
            href = new URL(a.href, window.location.origin);
        } catch (err) {
            return;
        }
        if (href.origin !== window.location.origin) {
            return;
        }
        if (href.pathname.indexOf('/logout') !== -1) {
            return;
        }
        if (href.protocol !== 'http:' && href.protocol !== 'https:') {
            return;
        }
        const goingHome = a.closest('[data-desk-home]')
            || a.hasAttribute('data-pos-window-home')
            || posDeskKey(href.href) === '/home';
        if (goingHome) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = window.location.origin + '/home';
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const tab = a.closest('.chief-tab[data-desk-key]');
        if (tab && ! tab.hasAttribute('data-desk-home')) {
            const desk = tab.getAttribute('data-desk-key') || '';
            // List tabs always open the list URL — never the edit page that used to overwrite them.
            if (desk && desk !== '/home' && desk.indexOf('so:') !== 0 && ! /\/edit$/.test(desk) && ! /\/create$/.test(desk)) {
                openHref(desk);
                return;
            }
        }
        openHref(href.href);
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (! (form instanceof HTMLFormElement)) {
            return;
        }
        
        if (form.classList.contains('chief-tab-close-all-form') || form.hasAttribute('data-pos-window-close-all')) {
            e.preventDefault();
            if (! confirm('Close all open windows and return to Home?')) {
                return;
            }
            deskEpoch += 1;
            const removeKeys = [];
            frames.forEach(function (iframe, key) {
                iframe.remove();
                removeKeys.push(key);
            });
            removeKeys.forEach(function (key) {
                frames.delete(key);
            });
            document.querySelectorAll('.chief-tab[data-desk-key]:not([data-desk-home])').forEach(function (tab) {
                tab.remove();
            });
            ensureCloseAllButton();
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
            }).finally(function () {
                showFrame(window.location.origin + '/home');
            });
            return;
        }
        
        if (! form.classList.contains('chief-tab-close-form')) {
            return;
        }
        e.preventDefault();
        const tabEl = form.closest('.chief-tab');
        const key = tabEl ? tabEl.getAttribute('data-desk-key') : '';
        const isSoTab = key && key.startsWith('so:');
        
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
            redirect: 'manual',
        }).then(posParseJsonResponse).then(function (data) {
            if (key && frames.has(key)) {
                frames.get(key).remove();
                frames.delete(key);
            }

            if (isSoTab && data && data.windows && Array.isArray(data.windows)) {
                syncSoTabs(data.windows);
            } else if (tabEl) {
                tabEl.remove();
            }

            ensureCloseAllButton();

            const nextSo = document.querySelector('.chief-tab[data-desk-key^="so:"] a.chief-tab-link');
            const next = nextSo
                || document.querySelector('.chief-tab[data-desk-key]:not([data-desk-home]) a.chief-tab-link')
                || document.querySelector('.chief-tab[data-desk-home] a.chief-tab-link');
            if (next) {
                showFrame(next.href);
            } else {
                showFrame(window.location.origin + '/home');
            }
        }).catch(function () {
            if (key && frames.has(key)) {
                frames.get(key).remove();
                frames.delete(key);
            }
            if (tabEl) {
                tabEl.remove();
            }
            ensureCloseAllButton();
            const next = document.querySelector('.chief-tab[data-desk-key] a.chief-tab-link');
            if (next) {
                showFrame(next.href);
            } else {
                showFrame(window.location.origin + '/home');
            }
        });
    }, true);

    window.addEventListener('popstate', function () {
        showFrame(window.location.href, { history: false });
    });

    showFrame(window.location.href, { history: false });
}
