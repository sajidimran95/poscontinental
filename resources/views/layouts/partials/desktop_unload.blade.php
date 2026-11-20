@if (config('sync.mode') === 'client')
{{-- Desktop app: Electron silently cancels closing the window when a page asks
     "leave this page?" (POS cart, edited forms), so the close button did nothing.
     There is no dialog in Electron, so page leave warnings are switched off here. --}}
<script>
    (function () {
        try {
            Object.defineProperty(window, 'onbeforeunload', {
                configurable: true,
                get: function () { return null; },
                set: function () {}
            });
            var add = window.addEventListener;
            window.addEventListener = function (type) {
                if (type === 'beforeunload') return;
                return add.apply(this, arguments);
            };
        } catch (e) {}
    })();
</script>
@endif
