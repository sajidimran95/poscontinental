@if (\App\Sync\Desktop\DesktopPrinter::directEnabled())
{{-- Desktop app with a printer chosen in Sync -> Receipt printer: window.print() goes straight
     to that printer (no dialog). Falls back to the normal dialog if direct printing fails. --}}
<script>
(function () {
    var cfg = { url: @json(route('desktop.print')), csrf: @json(csrf_token()) };
    var nativePrint = window.print.bind(window);

    function embedImages(doc) {
        var imgs = Array.prototype.slice.call(doc.images || []);
        return Promise.all(imgs.map(function (img) {
            var src = img.getAttribute('src') || '';
            if (!src || src.indexOf('data:') === 0) return Promise.resolve();
            var abs;
            try { abs = new URL(src, window.location.href).href; } catch (e) { return Promise.resolve(); }
            return fetch(abs, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(); return r.blob(); })
                .then(function (blob) {
                    return new Promise(function (resolve) {
                        var fr = new FileReader();
                        fr.onload = function () { img.setAttribute('src', fr.result); resolve(); };
                        fr.onerror = function () { resolve(); };
                        fr.readAsDataURL(blob);
                    });
                })
                .catch(function () { img.parentNode && img.parentNode.removeChild(img); });
        }));
    }

    function inlineStyles(doc) {
        // the print window cannot load CSS from the app's own server while it waits for the job
        var css = '';
        Array.prototype.forEach.call(document.styleSheets, function (sheet) {
            try { Array.prototype.forEach.call(sheet.cssRules, function (r) { css += r.cssText + '\n'; }); } catch (e) {}
        });
        doc.querySelectorAll('link[rel="stylesheet"], script').forEach(function (n) { n.parentNode.removeChild(n); });
        var style = doc.createElement('style');
        style.textContent = css;
        doc.head.appendChild(style);
    }

    window.__desktopDirectPrint = function () {
        var doc = document.implementation.createHTMLDocument('');
        doc.documentElement.innerHTML = document.documentElement.innerHTML;
        doc.querySelectorAll('[data-no-print], .no-print, .print\\:hidden').forEach(function (n) { n.parentNode.removeChild(n); });
        inlineStyles(doc);
        return embedImages(doc).then(function () {
            return fetch(cfg.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify({ html: '<!DOCTYPE html>' + doc.documentElement.outerHTML })
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (!r.ok || !j.success) throw new Error(j.message || ('HTTP ' + r.status));
                });
            });
        });
    };

    window.print = function () {
        window.__desktopDirectPrint().catch(function (err) {
            alert((err && err.message ? err.message : 'Direct printing failed') + ' - opening the print dialog.');
            nativePrint();
        });
    };
})();
</script>
@endif
