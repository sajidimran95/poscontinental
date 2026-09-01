document.addEventListener('DOMContentLoaded', function () {
    initDeskColResize();
    initExcelGrid();
});

document.addEventListener('livewire:navigated', function () {
    applyDeskColWidths();
});

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
            table.querySelectorAll('thead th[data-col]').forEach(function (th) {
                const col = th.getAttribute('data-col');
                const w = map[col];
                if (! w) {
                    return;
                }
                th.style.width = w + 'px';
                th.style.minWidth = w + 'px';
                th.style.maxWidth = w + 'px';
            });
        });
    };

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
        drag.th.style.width = w + 'px';
        drag.th.style.minWidth = w + 'px';
        drag.th.style.maxWidth = w + 'px';
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
        return false;
    }

    function columnIndexes(table) {
        const head = table.tHead && table.tHead.rows[0];
        if (! head) {
            return [];
        }
        const idx = [];
        Array.from(head.cells).forEach(function (th, i) {
            if (! skipCell(th)) {
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
        return table.classList.contains('so-lines-table') || table.classList.contains('so-item-browse-table');
    }

    function paint(table) {
        const set = pickSet(table);
        const canDrag = allowsRowDrag(table);
        bodyRows(table).forEach(function (tr) {
            tr.classList.toggle('is-excel-picked', set.has(rowKey(tr)));
            if (canDrag) {
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
        return { tsv: tsvFrom(headers, data) };
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
        if (e.target.closest('input, textarea, select, button, a')) {
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
        e.dataTransfer.effectAllowed = 'copy';
        e.dataTransfer.setData('text/plain', pack.tsv);
    });

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
