{{-- Company timezone is APP_TIMEZONE (America/New_York). Clear any old browser cookie. --}}
<script>
    (function () {
        try {
            document.cookie = 'pos_tz=;path=/;max-age=0;SameSite=Lax';
        } catch (e) {}
    })();
</script>
