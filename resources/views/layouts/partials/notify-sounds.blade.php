<script>
(function () {
    if (window.playPosNotify && window.speakPosNotify) return;

    let ctx = null;
    let pendingTone = null;
    let pendingSpeak = null;

    function audio() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (! AC) return null;
        if (! ctx) ctx = new AC();
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }

    function pickVoice(utterance) {
        utterance.lang = 'en-GB';
        const voices = window.speechSynthesis ? (window.speechSynthesis.getVoices() || []) : [];
        const ok = function (v) {
            const lang = (v.lang || '').toLowerCase();
            const name = (v.name || '').toLowerCase();
            if (lang.indexOf('en-us') === 0 || name.indexOf('united states') !== -1) return false;
            return lang.indexOf('en-gb') === 0 || lang.indexOf('en-au') === 0 || lang.indexOf('en-in') === 0
                || name.indexOf('british') !== -1 || name.indexOf('uk english') !== -1;
        };
        const pick = voices.find(ok) || voices.find(function (v) {
            const lang = (v.lang || '').toLowerCase();
            return lang.indexOf('en') === 0 && lang.indexOf('en-us') !== 0;
        }) || voices.find(function (v) {
            return (v.lang || '').toLowerCase().indexOf('en') === 0;
        });
        if (pick) {
            utterance.voice = pick;
            utterance.lang = pick.lang || 'en-GB';
        }
    }

    function speakNow(text) {
        text = String(text || '').trim();
        if (! text) return;
        if (window.posAiSpeakText) {
            window.posAiSpeakText(text, document.getElementById('posai-insights-speak-btn'));
            return;
        }
        if (! window.speechSynthesis || ! window.SpeechSynthesisUtterance) return;
        try { window.speechSynthesis.cancel(); } catch (e) {}
        const u = new SpeechSynthesisUtterance(text);
        u.rate = 1;
        u.pitch = 1;
        pickVoice(u);
        window.speechSynthesis.speak(u);
    }

    function unlock() {
        audio();
        if (window.speechSynthesis) {
            try { window.speechSynthesis.getVoices(); } catch (e) {}
        }
        if (pendingTone) {
            const kind = pendingTone;
            pendingTone = null;
            setTimeout(function () { renderTone(kind); }, 60);
        }
        if (pendingSpeak) {
            const text = pendingSpeak;
            pendingSpeak = null;
            setTimeout(function () { speakNow(text); }, 80);
        }
    }
    document.addEventListener('pointerdown', unlock, true);
    document.addEventListener('keydown', unlock, true);
    document.addEventListener('touchstart', unlock, { capture: true, passive: true });
    if (window.speechSynthesis) {
        window.speechSynthesis.getVoices();
        window.speechSynthesis.onvoiceschanged = function () {
            window.speechSynthesis.getVoices();
        };
    }

    function renderTone(kind) {
        const ac = audio();
        if (! ac) return;
        if (ac.state !== 'running') {
            pendingTone = kind;
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
            tone(1046.5, 0, 0.12, 0.18, 'triangle');
            tone(1568, 0.1, 0.14, 0.16, 'triangle');
        } else {
            tone(880, 0, 0.1, 0.12);
            tone(1174.7, 0.1, 0.12, 0.1);
        }
    }

    /** Spoken alert (TTS). Queues until user has interacted once. */
    window.speakPosNotify = function (text) {
        text = String(text || '').trim();
        if (! text) return;
        try {
            if (window.speechSynthesis && window.speechSynthesis.getVoices && window.speechSynthesis.getVoices().length) {
                speakNow(text);
            } else {
                pendingSpeak = text;
                // Try anyway — some browsers speak without voices list ready.
                speakNow(text);
            }
        } catch (e) {
            pendingSpeak = text;
        }
    };

    /**
     * kind: 'order' | 'chat'
     * text: optional spoken phrase. Defaults are short for both kinds.
     */
    window.playPosNotify = function (kind, text) {
        kind = kind || 'chat';
        try { renderTone(kind); } catch (e) {}
        var spoken = String(text || '').trim();
        if (! spoken) {
            spoken = kind === 'order'
                ? 'You received a new ecommerce order.'
                : 'You have a new team chat message.';
        }
        try { window.speakPosNotify(spoken); } catch (e) {}
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
