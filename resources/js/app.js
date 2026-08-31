document.addEventListener('DOMContentLoaded', function () {
    initDeskColResize();
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
