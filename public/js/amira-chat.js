/* ADR-369: chat with AMIRA on the local model. Answers are queued on the server; the thread is polled while one is
   pending. AMIRA only proposes particulars: "Guna butiran ini" fills the form, and the PSP saves it. */
(function () {
    'use strict';
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const reduced = () => motion.matches || document.documentElement.dataset.motion === 'reduced';
    const styles = () => getComputedStyle(document.documentElement);
    const ms = name => {
        const value = styles().getPropertyValue(name).trim();
        return (parseFloat(value) || 0) * (value.endsWith('ms') ? 1 : 1000);
    };

    // One-time AI agreement: opens once per sign-in session, after AMIRA's entrance, until the PSP agrees.
    const consent = document.querySelector('[data-amira-consent]');
    if (consent && typeof consent.showModal === 'function') {
        let asked = false;
        try { asked = sessionStorage.getItem('indecs.amira.consent.asked') === '1'; } catch { /* Ask on every page if storage is blocked. */ }
        if (!asked) {
            const entrance = reduced() ? 0 : ms('--duration-amira-emerge') + ms('--duration-amira-atmosphere') + ms('--duration-amira-rise') + ms('--duration-amira-name');
            setTimeout(() => {
                if (!consent.open && !document.querySelector('dialog[open]')) consent.showModal();
                try { sessionStorage.setItem('indecs.amira.consent.asked', '1'); } catch { /* Optional. */ }
            }, entrance);
        }
    }

    const form = document.querySelector('[data-amira-chat]');
    if (!form) return;
    const counter = form.closest('#amira-counter') || document;
    const input = form.querySelector('[data-amira-input]');
    const submit = form.querySelector('button[type="submit"]');
    const error = form.querySelector('[data-amira-error]');
    const announce = counter.querySelector('[data-amira-announce]');
    const scroll = counter.querySelector('[data-amira-scroll]');
    const bubbles = () => [...document.querySelectorAll('[data-amira-bubble]')];
    const restingMood = bubbles()[0]?.dataset.mood || 'calm';
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const POLL_MS = 3000;
    const POLL_LIMIT_MS = 5 * 60 * 1000;
    let pollingSince = 0;
    let timer = null;

    const thread = () => counter.querySelector('[data-amira-chat-thread]');
    const pending = () => thread()?.dataset.pending === 'true';
    const lastReplyId = () => [...(thread()?.querySelectorAll('[data-amira-reply]') || [])].pop()?.dataset.amiraReply || null;
    const setMood = mood => bubbles().forEach(bubble => window.AmiraBubble?.setMood(bubble, mood));
    const speaking = on => bubbles().forEach(bubble => window.AmiraBubble?.setSpeaking(bubble, on && !reduced()));
    let typing = null;

    // A new message box pops in (tokens: --duration-amira-message-enter, --scale-amira-message-enter).
    function pop(box) {
        if (!box || reduced() || document.hidden || typeof box.animate !== 'function') return;
        const mine = box.classList.contains('amira-counter-user');
        box.animate([
            { opacity: 0, transform: `scale(${styles().getPropertyValue('--scale-amira-message-enter').trim()})`, transformOrigin: mine ? 'top right' : 'top left' },
            { opacity: 1, transform: 'scale(1)', transformOrigin: mine ? 'top right' : 'top left' },
        ], { duration: ms('--duration-amira-message-enter'), easing: styles().getPropertyValue('--ease-standard').trim() });
    }

    // AMIRA's reply types out letter by letter, fast (--duration-amira-character, capped by --duration-amira-reply-max).
    // Sources, the officer line and proposal cards appear once the text is complete.
    function reveal(box) {
        typing?.finish();
        pop(box);
        const body = box.querySelector(':scope > p');
        if (!body || reduced() || document.hidden) return;
        const html = body.innerHTML;
        const text = body.innerText;
        const letters = typeof Intl.Segmenter === 'function'
            ? Array.from(new Intl.Segmenter('ms', { granularity: 'grapheme' }).segment(text), part => part.segment)
            : Array.from(text);
        const extras = [...box.children].filter(child => child !== body && !child.classList.contains('eyebrow'));
        const duration = Math.min(letters.length * ms('--duration-amira-character'), ms('--duration-amira-reply-max'));
        body.style.minHeight = `${body.getBoundingClientRect().height}px`;
        body.style.whiteSpace = 'pre-line';
        body.textContent = '';
        extras.forEach(extra => { extra.style.display = 'none'; });
        speaking(true);
        let frame = 0;
        const state = {
            finish() {
                if (typing !== state) return;
                typing = null;
                cancelAnimationFrame(frame);
                body.innerHTML = html;
                body.style.minHeight = '';
                body.style.whiteSpace = '';
                extras.forEach(extra => { extra.style.display = ''; });
                speaking(false);
                toBottom();
            },
        };
        typing = state;
        const started = performance.now();
        const step = now => {
            if (reduced() || document.hidden || !body.isConnected) { state.finish(); return; }
            const count = Math.min(letters.length, Math.floor((now - started) / Math.max(duration, 1) * letters.length));
            body.textContent = letters.slice(0, count).join('');
            toBottom();
            if (count >= letters.length) state.finish(); else frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
    }
    const showError = message => { error.textContent = message || ''; error.hidden = !message; };
    const toBottom = () => { if (scroll) scroll.scrollTop = scroll.scrollHeight; };

    function sync(previousReply) {
        const waiting = pending();
        setMood(waiting ? 'think' : restingMood);
        submit.disabled = waiting;
        const latest = lastReplyId();
        if (previousReply !== undefined && latest && latest !== previousReply) {
            const box = thread().querySelector(`[data-amira-reply="${latest}"]`);
            const body = box?.querySelector(':scope > p');
            if (announce && body) announce.textContent = `AMIRA menjawab: ${body.textContent}`;
            if (box) reveal(box);
        }
        toBottom();
        if (waiting) schedule(); else pollingSince = 0;
    }

    function swap(html) {
        const before = lastReplyId();
        const asked = thread()?.querySelectorAll('.amira-counter-user').length || 0;
        const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-amira-chat-thread]');
        if (!fresh || !thread()) return;
        typing?.finish();
        thread().replaceWith(fresh);
        const questions = fresh.querySelectorAll('.amira-counter-user');
        if (questions.length > asked) pop(questions[questions.length - 1]);
        pop(fresh.querySelector('.amira-typing'));
        sync(before);
    }

    function schedule() {
        clearTimeout(timer);
        if (!pollingSince) pollingSince = Date.now();
        if (Date.now() - pollingSince > POLL_LIMIT_MS) {
            showError('AMIRA mengambil masa terlalu lama. Muat semula halaman untuk melihat jawapan.');
            setMood(restingMood);
            return;
        }
        timer = setTimeout(poll, POLL_MS);
    }

    async function poll() {
        try {
            // X-Requested-With makes an expired session answer 401 instead of a login page.
            const response = await fetch(thread().dataset.threadUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (response.status === 401) { showError('Sesi anda telah tamat. Log masuk semula.'); setMood(restingMood); return; }
            if (!response.ok) throw new Error('thread');
            swap(await response.text());
        } catch {
            schedule();
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question || submit.disabled) return;
        showError('');
        submit.disabled = true;
        try {
            const response = await fetch(form.dataset.askUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ question }),
            });
            if (response.status === 201) {
                input.value = '';
                pollingSince = Date.now();
                swap(await response.text());
                return;
            }
            const data = await response.json().catch(() => ({}));
            showError(response.status === 419 ? 'Sesi halaman telah tamat. Muat semula halaman.' : (data.message || 'Soalan tidak dapat dihantar. Cuba lagi.'));
        } catch {
            showError('Tiada sambungan. Soalan anda dikekalkan; cuba lagi.');
        } finally {
            if (!pending()) submit.disabled = false;
        }
    });

    // Enter sends; Shift+Enter adds a line.
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
    });

    // Draft page: fill the particulars form from AMIRA's proposal. Nothing is saved until the PSP presses "Simpan draf".
    counter.addEventListener('click', event => {
        const button = event.target.closest('[data-amira-apply]');
        if (!button) return;
        let proposal;
        try { proposal = JSON.parse(button.dataset.amiraApply); } catch { return; }
        const draftForm = document.querySelector('.application-form');
        if (!draftForm) return;
        const set = (name, value) => {
            const field = draftForm.querySelector(`[name="${name}"]`);
            if (!field || value === undefined || value === null) return false;
            field.value = String(value);
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        };
        set('application_type_id', proposal.application_type);
        const filled = ['work_type', 'building_category', 'storeys', 'floor_area'].map(name => set(name, proposal[name])).some(Boolean);
        if (Array.isArray(proposal.site_conditions)) {
            draftForm.querySelectorAll('[name="site_conditions[]"]').forEach(box => { box.checked = proposal.site_conditions.includes(box.value); });
        }
        const section = draftForm.querySelector('#butiran-projek');
        button.textContent = section || filled ? 'Butiran diisi. Semak, kemudian simpan draf.' : 'Simpan draf dengan jenis Bangunan untuk mengisi butiran projek.';
        button.disabled = true;
        // Phone: close AMIRA's sheet so the filled form is visible.
        if (counter.classList?.contains('is-open')) counter.querySelector('[data-amira-sheet-close]')?.click();
        (section || draftForm).scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
        draftForm.querySelector('button[type="submit"]')?.focus({ preventScroll: true });
    });

    sync(undefined);
})();
