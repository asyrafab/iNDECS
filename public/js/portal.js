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

    // Desktop: drag the panel's right edge (or use arrow keys) to resize; double-click resets. Saved per browser.
    const handle = amira.querySelector('[data-amira-resize]');
    if (handle) {
        const rootStyle = document.documentElement.style;
        const MIN = 300;
        const max = () => Math.max(MIN, Math.min(720, Math.round(window.innerWidth * .5)));
        const current = () => amira.getBoundingClientRect().width;
        const apply = (width, save = true) => {
            const value = Math.round(Math.min(max(), Math.max(MIN, width)));
            rootStyle.setProperty('--width-counter-amira', `${value}px`);
            handle.setAttribute('aria-valuenow', String(value));
            handle.setAttribute('aria-valuemax', String(max()));
            if (save) { try { localStorage.setItem('indecs.amira.width', String(value)); } catch { /* This page only. */ } }
        };
        handle.hidden = false;
        handle.setAttribute('aria-valuemin', String(MIN));
        handle.setAttribute('aria-valuenow', String(Math.round(current())));
        handle.setAttribute('aria-valuemax', String(max()));
        handle.addEventListener('pointerdown', event => {
            if (phone.matches || event.button !== 0) return;
            event.preventDefault();
            handle.setPointerCapture(event.pointerId);
            const startX = event.clientX;
            const startWidth = current();
            document.documentElement.classList.add('is-resizing-amira');
            const move = moved => apply(startWidth + moved.clientX - startX);
            const end = () => {
                handle.removeEventListener('pointermove', move);
                handle.removeEventListener('pointerup', end);
                handle.removeEventListener('pointercancel', end);
                document.documentElement.classList.remove('is-resizing-amira');
            };
            handle.addEventListener('pointermove', move);
            handle.addEventListener('pointerup', end);
            handle.addEventListener('pointercancel', end);
        });
        handle.addEventListener('keydown', event => {
            const step = event.shiftKey ? 64 : 16;
            const keys = { ArrowLeft: () => current() - step, ArrowRight: () => current() + step, Home: () => MIN, End: () => max() };
            if (!keys[event.key]) return;
            event.preventDefault();
            apply(keys[event.key]());
        });
        handle.addEventListener('dblclick', () => {
            rootStyle.removeProperty('--width-counter-amira');
            try { localStorage.removeItem('indecs.amira.width'); } catch { /* Nothing saved. */ }
            handle.setAttribute('aria-valuenow', String(Math.round(current())));
        });
    }
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
        // Never throw away the applicant's work: wait while a file is picked or focus is inside the list.
        const busy = [...section.querySelectorAll('input[type="file"]')].some(input => input.files && input.files.length > 0)
            || section.contains(document.activeElement);
        if (busy) { setTimeout(poll, 4000); return; }
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

/* Upload-first drafts: drop files, a folder or one ZIP. Batches of up to 10 files and 100 MB; a ZIP or a large file goes alone. */
(function () {
    const form = document.querySelector('[data-drop-zone]');
    if (!form || !window.fetch || !window.FormData) return;
    const target = form.querySelector('[data-drop-target]');
    const progress = form.querySelector('[data-drop-progress]');
    const token = form.querySelector('input[name="_token"]').value;
    const MAX_FILES = 10, MAX_BYTES = 100 * 1024 * 1024;
    const skip = name => name.startsWith('.') || name.toLowerCase() === 'thumbs.db';
    let busy = false;

    const line = (text, kind) => {
        const item = document.createElement('li');
        item.textContent = text;
        if (kind) item.className = kind;
        progress.appendChild(item);
        return item;
    };

    const readEntry = entry => new Promise(resolve => {
        if (entry.isFile) { entry.file(file => resolve([file]), () => resolve([])); return; }
        if (!entry.isDirectory) { resolve([]); return; }
        const reader = entry.createReader();
        const all = [];
        const next = () => reader.readEntries(entries => {
            if (!entries.length) { Promise.all(all.map(readEntry)).then(lists => resolve(lists.flat())); return; }
            all.push(...entries);
            next();
        }, () => resolve([]));
        next();
    });

    const batches = files => {
        const out = [];
        let current = [], bytes = 0;
        files.forEach(file => {
            const alone = /\.zip$/i.test(file.name) || file.size > MAX_BYTES;
            if (alone) { out.push([file]); return; }
            if (current.length === MAX_FILES || bytes + file.size > MAX_BYTES) { out.push(current); current = []; bytes = 0; }
            current.push(file);
            bytes += file.size;
        });
        if (current.length) out.push(current);
        return out;
    };

    const upload = async files => {
        files = files.filter(file => !skip(file.name));
        if (busy || !files.length) return;
        const type = form.querySelector('select[name="application_type_id"]');
        if (type && !type.value) { line('Pilih jenis permohonan dahulu.', 'is-error'); type.focus(); return; }
        busy = true;
        progress.innerHTML = '';
        let url = form.dataset.addUrl || form.dataset.dropUrl;
        let draftUrl = null;
        for (const batch of batches(files)) {
            const status = line('Memuat naik ' + batch.map(file => file.name).join(', ') + '…');
            const data = new FormData();
            data.append('_token', token);
            if (type && !form.dataset.addUrl) data.append('application_type_id', type.value);
            batch.forEach(file => data.append('files[]', file, file.name));
            try {
                const response = await fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
                const json = await response.json().catch(() => ({}));
                if (!response.ok) {
                    status.textContent = (json.errors ? Object.values(json.errors).flat().join(' ') : json.message) || 'Muat naik gagal. Cuba lagi.';
                    status.className = 'is-error';
                    if (!draftUrl) { busy = false; return; }
                    continue;
                }
                form.dataset.addUrl = json.add_url;
                url = json.add_url;
                draftUrl = json.draft_url;
                status.textContent = json.accepted.length + ' fail diterima.';
                (json.rejected || []).forEach(rejected => line(rejected.name + ' — ' + rejected.reason, 'is-error'));
            } catch (error) {
                status.textContent = 'Sambungan terputus. Cuba lagi.';
                status.className = 'is-error';
            }
        }
        busy = false;
        if (draftUrl && !progress.querySelector('.is-error')) window.location.assign(draftUrl);
        else if (draftUrl) line('Lihat draf anda untuk menyemak fail yang diterima.').appendChild(Object.assign(document.createElement('a'), { href: draftUrl, textContent: ' Buka draf' }));
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        upload([...form.querySelectorAll('input[type="file"]')].flatMap(input => [...input.files]));
    });
    form.querySelectorAll('input[type="file"]').forEach(input => input.addEventListener('change', () => upload([...input.files])));
    ['dragenter', 'dragover'].forEach(name => target.addEventListener(name, event => { event.preventDefault(); target.classList.add('is-dragover'); }));
    ['dragleave', 'drop'].forEach(name => target.addEventListener(name, () => target.classList.remove('is-dragover')));
    target.addEventListener('drop', async event => {
        event.preventDefault();
        const items = [...(event.dataTransfer.items || [])].map(item => item.webkitGetAsEntry && item.webkitGetAsEntry()).filter(Boolean);
        const files = items.length ? (await Promise.all(items.map(readEntry))).flat() : [...event.dataTransfer.files];
        upload(files);
    });
})();
