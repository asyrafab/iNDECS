/* ADR-396: store only an officer's numeric layout preference; never comment/document data. */
(() => {
    document.querySelectorAll('[data-review-split]').forEach(desk => {
        const divider = desk.querySelector('[data-review-divider]');
        const reset = desk.querySelector('[data-review-split-reset]');
        if (!divider || !reset) return;
        const desktop = window.matchMedia('(min-width: 1101px)');
        const initialStyle = getComputedStyle(desk);
        const defaultRatio = Number(initialStyle.getPropertyValue('--ratio-technical-plan-default'));
        const minimum = parseFloat(initialStyle.getPropertyValue('--size-technical-pane-min'));
        const gutter = parseFloat(initialStyle.getPropertyValue('--space-6'));
        const gap = parseFloat(initialStyle.getPropertyValue('--space-2'));
        if (![defaultRatio, minimum, gutter, gap].every(Number.isFinite) || defaultRatio <= 0 || defaultRatio >= 1 || minimum <= 0 || gutter < 0 || gap < 0) return;
        const key = `indecs.technical-review.split.${desk.dataset.reviewUser || 'default'}`;
        let desired = defaultRatio, effective = defaultRatio, active = null, beforeDrag = defaultRatio, grabOffset = 0;
        let bounds = {enabled: false, available: 0, min: .5, max: .5};
        try {
            const saved = Number(localStorage.getItem(key));
            if (Number.isFinite(saved) && saved > 0 && saved < 1) desired = saved;
        } catch { /* Layout remains usable when browser storage is disabled. */ }
        const persist = () => { try { localStorage.setItem(key, String(desired)); } catch { /* Optional preference only. */ } };
        const refresh = () => {
            const available = desk.getBoundingClientRect().width - gutter - gap * 2;
            const enabled = desktop.matches && available >= minimum * 2;
            bounds = {enabled, available, min: enabled ? minimum / available : .5, max: enabled ? 1 - minimum / available : .5};
            divider.hidden = !enabled; reset.hidden = !enabled;
            desk.classList.toggle('is-resizable', enabled);
            desk.classList.toggle('is-narrow', !enabled);
            if (!enabled) { if (active !== null) finish(false); return; }
            effective = Math.min(bounds.max, Math.max(bounds.min, desired));
            desk.style.setProperty('--technical-plan-fr', `${effective.toFixed(6)}fr`);
            desk.style.setProperty('--technical-comment-fr', `${(1 - effective).toFixed(6)}fr`);
            const percent = Math.round(effective * 100);
            divider.setAttribute('aria-valuemin', String(Math.round(bounds.min * 100)));
            divider.setAttribute('aria-valuemax', String(Math.round(bounds.max * 100)));
            divider.setAttribute('aria-valuenow', String(percent));
            divider.setAttribute('aria-valuetext', `Pelan ${percent}% · Ulasan ${100 - percent}%`);
        };
        const set = value => { desired = Math.min(bounds.max, Math.max(bounds.min, value)); refresh(); };
        const finish = commit => {
            if (active === null) return;
            const pointer = active; active = null;
            desk.classList.remove('is-resizing');
            if (!commit) desired = beforeDrag;
            refresh();
            if (divider.hasPointerCapture(pointer)) divider.releasePointerCapture(pointer);
            if (commit) persist();
        };
        divider.addEventListener('pointerdown', event => {
            if (!bounds.enabled || event.button !== 0 || event.isPrimary === false || active !== null) return;
            event.preventDefault(); divider.focus({preventScroll: true});
            active = event.pointerId; beforeDrag = desired;
            const rect = divider.getBoundingClientRect(); grabOffset = event.clientX - (rect.left + rect.width / 2);
            divider.setPointerCapture(active); desk.classList.add('is-resizing');
        });
        divider.addEventListener('pointermove', event => {
            if (event.pointerId !== active || !bounds.enabled) return;
            set((event.clientX - grabOffset - desk.getBoundingClientRect().left - gap - gutter / 2) / bounds.available);
        });
        divider.addEventListener('pointerup', event => { if (event.pointerId === active) finish(true); });
        divider.addEventListener('pointercancel', event => { if (event.pointerId === active) finish(false); });
        divider.addEventListener('lostpointercapture', event => { if (event.pointerId === active) finish(true); });
        divider.addEventListener('keydown', event => {
            if (event.key === 'Escape' && active !== null) { event.preventDefault(); finish(false); return; }
            if (!bounds.enabled || active !== null) return;
            const next = {ArrowLeft: effective - .02, ArrowRight: effective + .02, Home: bounds.min, End: bounds.max, Enter: defaultRatio}[event.key];
            if (next === undefined) return;
            event.preventDefault(); set(next); persist();
        });
        const restore = () => { if (bounds.enabled) { set(defaultRatio); persist(); } };
        divider.addEventListener('dblclick', restore); reset.addEventListener('click', restore);
        window.addEventListener('blur', () => finish(false));
        document.addEventListener('visibilitychange', () => { if (document.hidden) finish(false); });
        desktop.addEventListener('change', refresh);
        if (typeof ResizeObserver === 'function') new ResizeObserver(refresh).observe(desk);
        else window.addEventListener('resize', refresh);
        refresh();
    });
})();
