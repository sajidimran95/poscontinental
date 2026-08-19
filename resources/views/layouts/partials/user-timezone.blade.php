        <script>
            (function () {
                try {
                    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
                    var cookie = document.cookie.split('; ').find(function (r) { return r.indexOf('pos_tz=') === 0; });
                    var current = cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
                    if (current !== tz) {
                        document.cookie = 'pos_tz=' + encodeURIComponent(tz) + ';path=/;max-age=31536000;SameSite=Lax';
                        if (!current) {
                            window.location.reload();
                        }
                    }
                } catch (e) {}
            })();
        </script>
