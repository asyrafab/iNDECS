/* ADR-368: a staged arrival and private, saved local-guidance thread. */
(function () {
    'use strict';
    const media = window.matchMedia('(prefers-reduced-motion: reduce)');
    const reduced = () => media.matches || document.documentElement.dataset.motion === 'reduced';
    const activeEntrances = new Set();
    const styles = () => getComputedStyle(document.documentElement);
    const ms = name => {
        const value = styles().getPropertyValue(name).trim();
        return parseFloat(value) * (value.endsWith('ms') ? 1 : 1000);
    };
    const settle = () => {
        if (reduced() || document.hidden) activeEntrances.forEach(animation => animation.finish());
    };
    media.addEventListener('change', settle);
    new MutationObserver(settle).observe(document.documentElement, { attributes: true, attributeFilter: ['data-motion'] });
    document.addEventListener('visibilitychange', settle);

    async function motion(element, frames, duration) {
        if (reduced() || document.hidden || !element.animate) return;
        const animation = element.animate(frames, { duration: ms(duration), easing: styles().getPropertyValue('--ease-standard').trim(), fill: 'forwards' });
        activeEntrances.add(animation);
        try { await animation.finished; } catch { /* Complete the finite arrival if interrupted. */ }
        Object.assign(element.style, frames[frames.length - 1]);
        activeEntrances.delete(animation);
        animation.cancel();
    }

    async function entrance(counter) {
        const avatar = counter.querySelector('[data-amira-bubble]');
        const name = counter.querySelector('[data-amira-name]');
        counter.classList.add('is-entering');
        avatar.dataset.emergence = 'body';
        delete document.documentElement.dataset.amiraEntrance;
        clearTimeout(window.amiraEntranceRecovery);
        const offset = Math.max(0, counter.clientHeight / 2 - avatar.offsetHeight / 2 - parseFloat(getComputedStyle(counter).paddingTop));
        try {
            await motion(avatar, [
                { transform: `translateY(${offset}px) scale(0)`, opacity: 0 },
                { transform: `translateY(${offset}px) scale(${styles().getPropertyValue('--scale-amira-emerge-peak').trim()})`, opacity: 1, offset: .75 },
                { transform: `translateY(${offset}px) scale(1)`, opacity: 1 },
            ], '--duration-amira-emerge');
            avatar.dataset.emergence = 'atmosphere';
            await motion(avatar, [{ opacity: 1 }, { opacity: 1 }], '--duration-amira-atmosphere');
            await motion(avatar, [{ transform: `translateY(${offset}px)` }, { transform: 'translateY(0)' }], '--duration-amira-rise');
            await motion(name, [{ opacity: 0 }, { opacity: 1 }], '--duration-amira-name');
        } finally {
            avatar.style.transform = '';
            avatar.style.opacity = '';
            name.style.opacity = '';
            avatar.dataset.emergence = 'ready';
            counter.classList.remove('is-entering');
        }
    }

    function setup(counter) {
        let started = false, busy = false, before = null, retry = null;
        const saved = counter.hasAttribute('data-amira-session');
        const scroll = counter.querySelector('[data-amira-scroll]');
        const avatar = counter.querySelector('[data-amira-bubble]');
        const input = counter.querySelector('[data-amira-input]');
        const older = counter.querySelector('[data-amira-older]');
        const error = counter.querySelector('[data-amira-history-error]');
        const retryButton = counter.querySelector('[data-amira-retry]');
        const rendered = new Set();
        let following = true;
        scroll.addEventListener('scroll', () => {
            following = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < parseFloat(styles().getPropertyValue('--space-12'));
        });
        const follow = () => { if (following) scroll.scrollTop = scroll.scrollHeight; };
        function locked(on) {
            busy = on;
            counter.querySelectorAll('[data-amira-prompt], [data-amira-form] button[type="submit"]').forEach(button => { button.disabled = on; });
        }
        function failed(action) {
            if (!error) return;
            error.hidden = false;
            error.textContent = 'Perbualan belum dapat disimpan atau dimuatkan. Soalan anda dikekalkan. Cuba semula.';
            retry = action;
            retryButton.hidden = false;
        }
        async function request(url, payload) {
            const abort = new AbortController();
            const timeout = setTimeout(() => abort.abort(), ms('--duration-amira-history-timeout'));
            try {
            const response = await fetch(url, payload ? {
                signal: abort.signal,
                method: 'POST', credentials: 'same-origin', headers: {
                    'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                }, body: JSON.stringify({ ...payload, draft_id: counter.dataset.draftId || null }),
            } : { signal: abort.signal, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('guidance-request-failed');
            return response.json();
            } finally { clearTimeout(timeout); }
        }
        function historyUrl(cursor) {
            const url = new URL(counter.dataset.historyUrl, location.href);
            if (counter.dataset.draftId) url.searchParams.set('draft_id', counter.dataset.draftId);
            if (cursor) url.searchParams.set('before', cursor);
            return url.href;
        }
        async function render(turns, immediate = false, prepend = false) {
            const thread = counter.querySelector('[data-amira-thread]');
            const previous = thread.firstChild;
            for (const turn of turns) {
                if (rendered.has(turn.id)) continue;
                const box = counter.querySelector('[data-amira-turn-template]').content.firstElementChild.cloneNode(true);
                box.dataset.turnId = turn.id;
                if (prepend) thread.insertBefore(box, previous); else thread.append(box);
                rendered.add(turn.id);
                await animateAmiraReply(box, turn.heading, turn.reply, turn.question, { avatar, immediate, onUpdate: prepend ? undefined : follow });
            }
        }
        async function loadOlder() {
            if (!before || older.disabled) return;
            older.disabled = true;
            try {
                const page = await request(historyUrl(before));
                const height = scroll.scrollHeight, top = scroll.scrollTop;
                await render(page.turns, true, true);
                before = page.before;
                older.hidden = !before;
                scroll.scrollTop = top + scroll.scrollHeight - height;
                error.hidden = true;
                retryButton.hidden = true;
            } catch { failed(loadOlder); }
            finally { older.disabled = false; }
        }
        async function load() {
            locked(true);
            try {
                const page = await request(historyUrl());
                counter.classList.add('is-enhanced');
                if (page.turns.length) {
                    await render(page.turns, true);
                    before = page.before;
                } else {
                    const opening = await request(counter.dataset.messageUrl, { kind: 'opening', request_id: crypto.randomUUID() });
                    await render(opening.turns);
                }
                older.hidden = !before;
                counter.querySelectorAll('[data-amira-interactive]').forEach(node => { node.hidden = false; });
                error.hidden = true;
                retryButton.hidden = true;
                following = true;
                follow();
            } catch { failed(load); }
            finally { locked(false); }
        }
        async function send(payload) {
            if (busy) return;
            locked(true);
            try {
                const page = await request(counter.dataset.messageUrl, payload);
                following = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < parseFloat(styles().getPropertyValue('--space-12'));
                await render(page.turns);
                if (input.value.trim() === payload.question) input.value = '';
                error.hidden = true;
                retryButton.hidden = true;
                retry = null;
            } catch { failed(() => send(payload)); }
            finally { locked(false); }
        }
        if (saved) {
            older.addEventListener('click', loadOlder);
            retryButton.addEventListener('click', () => { if (!busy) retry?.(); });
            counter.querySelectorAll('[data-amira-prompt]').forEach(button => button.addEventListener('click', () => send({ kind: button.dataset.amiraPrompt, question: button.textContent.trim(), request_id: crypto.randomUUID() })));
            counter.querySelector('[data-amira-form]').addEventListener('submit', event => {
                event.preventDefault();
                const question = input.value.trim();
                if (question) send({ kind: 'question', question, request_id: crypto.randomUUID() });
            });
        }
        async function start() {
            if (started || !counter.getClientRects().length) return;
            started = true;
            await entrance(counter);
            if (saved) await load();
        }
        if (window.IntersectionObserver) {
            const observer = new IntersectionObserver(entries => { if (entries[0].isIntersecting) { start(); observer.disconnect(); } });
            observer.observe(counter);
        } else start();
    }
    document.querySelectorAll('[data-amira-entrance]').forEach(setup);
})();
