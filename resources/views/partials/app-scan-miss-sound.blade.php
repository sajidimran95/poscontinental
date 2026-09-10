<script>
(function () {
    let audioCtx = null;
    let lastAt = 0;
    let scanMissTimer = 0;

    function audio() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (! AC) return null;
        if (! audioCtx) audioCtx = new AC();
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }

        return audioCtx;
    }

    document.addEventListener('pointerdown', function () { audio(); });
    document.addEventListener('touchstart', function () { audio(); }, { passive: true });
    document.addEventListener('keydown', function () { audio(); });

    window.playPosAlert = window.playPosAlert || function (kind) {
        const nowMs = Date.now();
        if (kind !== 'scan-miss' && nowMs - lastAt < 280) return;
        lastAt = nowMs;
        const ac = audio();
        if (! ac) return;
        const now = ac.currentTime;
        const beep = function (freq, start, dur, vol) {
            const osc = ac.createOscillator();
            const gain = ac.createGain();
            osc.type = 'square';
            osc.frequency.setValueAtTime(freq, now + start);
            gain.gain.setValueAtTime(0.0001, now + start);
            gain.gain.exponentialRampToValueAtTime(vol || 0.22, now + start + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + start + dur);
            osc.connect(gain);
            gain.connect(ac.destination);
            osc.start(now + start);
            osc.stop(now + start + dur + 0.02);
        };
        kind = kind || 'error';
        if (kind === 'scan-miss') {
            beep(880, 0, 0.22, 0.48);
            beep(220, 0.24, 0.38, 0.52);
            beep(880, 0.66, 0.22, 0.48);
            beep(180, 0.9, 0.45, 0.55);
        } else if (kind === 'error' || kind === 'danger') {
            beep(980, 0, 0.16, 0.28);
            beep(420, 0.18, 0.28, 0.28);
        } else if (kind === 'success' || kind === 'info') {
            beep(1320, 0, 0.08, 0.16);
            beep(1760, 0.09, 0.11, 0.16);
        } else {
            beep(1080, 0, 0.1, 0.16);
        }
    };

    window.stopPosScanMissAlarm = window.stopPosScanMissAlarm || function () {
        window.clearInterval(scanMissTimer);
        scanMissTimer = 0;
    };

    window.startPosScanMissAlarm = window.startPosScanMissAlarm || function () {
        window.stopPosScanMissAlarm();
        window.playPosAlert && window.playPosAlert('scan-miss');
        scanMissTimer = window.setInterval(function () {
            window.playPosAlert && window.playPosAlert('scan-miss');
        }, 1400);
    };

    window.notifyAppItemNotFound = function (code) {
        const q = String(code || '').trim();
        try { navigator.vibrate && navigator.vibrate([80, 60, 80, 60, 120]); } catch (e) {}
        window.startPosScanMissAlarm && window.startPosScanMissAlarm();
        const ev = new CustomEvent('app-item-not-found', { bubbles: true, cancelable: true, detail: { code: q } });
        if (! window.dispatchEvent(ev)) return;
        window.setTimeout(function () {
            alert('Item not found: ' + q + '\n\nThe scanned code does not match any item in the system.');
            window.stopPosScanMissAlarm && window.stopPosScanMissAlarm();
        }, 80);
    };
})();
</script>
