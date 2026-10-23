<script>
(function () {
    if (window.__posUserSoundBound) return;
    window.__posUserSoundBound = true;

    const userId = @json((int) (auth()->id() ?? 0));

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
        });
        if (pick) {
            utterance.voice = pick;
            utterance.lang = pick.lang || 'en-GB';
        }
    }

    function speak(text) {
        text = String(text || '').trim();
        if (! text) return;
        if (window.posAiSpeakText) {
            window.posAiSpeakText(text, document.getElementById('posai-insights-speak-btn'));
            return;
        }
        if (! window.speechSynthesis || ! window.SpeechSynthesisUtterance) return;
        window.speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.rate = 1;
        u.pitch = 1;
        pickVoice(u);
        window.speechSynthesis.speak(u);
    }

    function payloadOf(e) {
        if (e && (e.text || e.fingerprint || e.userId || e.user_id)) return e;
        if (Array.isArray(e) && e[0]) return e[0];
        return {};
    }

    function onSpeak(e) {
        const payload = payloadOf(e);
        const uid = Number(payload.userId || payload.user_id || 0);
        if (! uid || uid !== userId) return;
        speak(payload.text);
    }

    function bind() {
        if (! window.Livewire || ! Livewire.on || window.__posUserSoundListen) return;
        window.__posUserSoundListen = true;
        Livewire.on('pos-ai-speak-alert', onSpeak);
    }
    document.addEventListener('livewire:init', bind);
    bind();
})();
</script>
