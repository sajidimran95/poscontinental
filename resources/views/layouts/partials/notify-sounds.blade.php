<script>
(function () {
    if (window.playPosNotify) return;

    let ctx = null;
    let pending = null;

    function audio() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (! AC) return null;
        if (! ctx) ctx = new AC();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function unlock() {
        const ac = audio();
        if (ac && pending) {
            const kind = pending;
            pending = null;
            setTimeout(function () { render(kind); }, 60);
        }
    }
    document.addEventListener('pointerdown', unlock, true);
    document.addEventListener('keydown', unlock, true);
    document.addEventListener('touchstart', unlock, { capture: true, passive: true });

    function render(kind) {
        const ac = audio();
        if (! ac) return;
        if (ac.state !== 'running') {
            pending = kind;
            return;
        }
        const t = ac.currentTime + 0.02;
        function tone(freq, start, dur, vol, type) {
            const osc = ac.createOscillator();
            const g = ac.createGain();
            osc.type = type || 'sine';
            osc.frequency.value = freq;
            g.gain.setValueAtTime(0.0001, t + start);
            g.gain.exponentialRampToValueAtTime(vol, t + start + 0.015);
            g.gain.exponentialRampToValueAtTime(0.0001, t + start + dur);
            osc.connect(g);
            g.connect(ac.destination);
            osc.start(t + start);
            osc.stop(t + start + dur + 0.03);
        }
        if (kind === 'order') {
            [0, 0.62].forEach(function (o) {
                tone(1046.5, o, 0.16, 0.26, 'triangle');
                tone(1318.5, o + 0.11, 0.16, 0.26, 'triangle');
                tone(1568, o + 0.22, 0.16, 0.26, 'triangle');
                tone(2093, o + 0.33, 0.32, 0.24, 'triangle');
            });
        } else {
            tone(880, 0, 0.16, 0.18);
            tone(1174.7, 0.13, 0.24, 0.16);
        }
    }

    window.playPosNotify = function (kind) {
        try { render(kind || 'chat'); } catch (e) {}
    };

    /**
     * True once per new id across all open tabs (first sighting only sets the baseline).
     */
    window.posNotifyClaim = function (key, id) {
        id = Number(id || 0);
        let stored = null;
        try { stored = localStorage.getItem(key); } catch (e) {}
        if (stored === null) {
            try { localStorage.setItem(key, String(id)); } catch (e) {}
            return false;
        }
        if (! id || id <= Number(stored)) return false;
        try { localStorage.setItem(key, String(id)); } catch (e) {}
        return true;
    };
})();
</script>
