/* Public portal choices use native modal isolation and ordinary links as fallback. */
(function () {
    const dialogs = [...document.querySelectorAll('.portal-dialog')];
    const settings = document.querySelector('[data-portal-settings]');
    const openers = new WeakMap();
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-portal-open]');
        if (trigger) {
            const dialog = document.getElementById(trigger.dataset.portalOpen);
            if (!dialogs.includes(dialog) || typeof dialog.showModal !== 'function') return;
            event.preventDefault();
            const current = dialogs.find(item => item.open);
            const isChild = current && dialog.dataset.portalParent === current.id;
            const returnTarget = isChild ? trigger : (current ? openers.get(current) : (trigger.closest('[data-portal-settings]') ? settings.querySelector('summary') : trigger));
            if (current === dialog) return;
            if (!isChild) current?.close();
            if (settings) settings.open = false;
            openers.set(dialog, returnTarget);
            dialog.showModal();
            dialog.querySelector('[data-portal-close]')?.focus();
        } else if (settings?.open && !settings.contains(event.target)) {
            settings.open = false;
        }
    });
    dialogs.forEach(dialog => {
        dialog.querySelector('[data-portal-close]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('close', () => {
            const parent = document.getElementById(dialog.dataset.portalParent);
            if (parent?.open || !dialogs.some(item => item.open)) openers.get(dialog)?.focus({preventScroll: true});
        });
        dialog.addEventListener('click', event => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
        dialog.addEventListener('keydown', event => {
            if (event.key !== 'Tab') return;
            const controls = [...dialog.querySelectorAll('a[href], button:not(:disabled), input, select, textarea')].filter(node => node.getClientRects().length);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        });
    });
    settings?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { settings.open = false; settings.querySelector('summary')?.focus(); }
    });

    /* Kaunter Maya (ADR-361): on phones AMIRA opens as a full-height sheet; on desktop she is
       already beside the file, so "open" just moves focus to her question box. */
    const amira = document.getElementById('amira-counter');
    if (!amira) return;
    const phone = window.matchMedia('(max-width: 900px)');
    const sheetOpeners = [...document.querySelectorAll('[data-amira-sheet-open]')];
    let sheetOpener = null;
    const setSheet = (open, returnFocus = true) => {
        amira.classList.toggle('is-open', open);
        document.body.classList.toggle('amira-sheet-open', open);
        if (open) { amira.setAttribute('role', 'dialog'); amira.setAttribute('aria-modal', 'true'); }
        else { amira.removeAttribute('role'); amira.removeAttribute('aria-modal'); }
        sheetOpeners.forEach(button => button.setAttribute('aria-expanded', String(open)));
        if (open) amira.querySelector('[data-amira-sheet-close]')?.focus();
        else if (returnFocus) sheetOpener?.focus();
    };
    sheetOpeners.forEach(button => button.addEventListener('click', () => {
        if (settings) settings.open = false;
        if (!phone.matches) { amira.querySelector('[data-amira-input]')?.focus(); return; }
        sheetOpener = button.closest('[data-portal-settings]') ? settings.querySelector('summary') : button;
        setSheet(true);
    }));
    amira.querySelector('[data-amira-sheet-close]')?.addEventListener('click', () => setSheet(false));
    phone.addEventListener('change', () => { if (!phone.matches && amira.classList.contains('is-open')) setSheet(false, false); });
    amira.addEventListener('keydown', event => {
        if (!amira.classList.contains('is-open')) return;
        if (event.key === 'Escape') { event.preventDefault(); setSheet(false); return; }
        if (event.key !== 'Tab') return;
        const controls = [...amira.querySelectorAll('a[href], button:not(:disabled), input, select, textarea')].filter(node => node.getClientRects().length && !node.closest('[hidden]'));
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
})();

/* ADR-363: refresh the draft checklist while free pre-checks run (stops after five minutes). */
(function () {
    const started = Date.now();
    const poll = () => {
        const section = document.querySelector('[data-precheck-pending][data-precheck-url]');
        if (!section || Date.now() - started > 300000) return;
        fetch(section.dataset.precheckUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(response => response.ok ? response.text() : Promise.reject(response.status))
            .then(html => {
                const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-precheck-url]');
                if (fresh) section.replaceWith(fresh);
            })
            .catch(() => {})
            .finally(() => setTimeout(poll, 4000));
    };
    setTimeout(poll, 4000);
})();
