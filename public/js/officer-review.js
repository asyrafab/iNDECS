/* ADR-394: progressive, page-local tools. No autosave, remote calls or browser storage. */
(() => {
    const dirtyForms = new Set();
    let navigating = false;
    const normalize = value => value.normalize('NFKC').toLocaleLowerCase('ms').trim();
    document.querySelectorAll('[data-technical-review]').forEach(root => {
        const rows = [...root.querySelectorAll('[data-review-finding]')];
        const search = root.querySelector('[data-review-search]');
        const filter = root.querySelector('[data-review-filter]');
        const section = root.querySelector('[data-review-section]');
        const result = root.querySelector('[data-review-results]');
        const empty = root.querySelector('[data-review-empty]');
        const queue = root.querySelector('[data-review-queue]');
        const queueSelect = root.querySelector('[data-review-queue-select]');
        const position = root.querySelector('[data-review-active-position]');
        const focus = root.dataset.reviewFocus === 'true' && !!queueSelect;
        const failed = rows.find(row => row.dataset.reviewFailed === 'true');
        let recoverFailed = failed;
        let active = failed || rows.find(row => `#${row.id}` === location.hash)
            || rows.find(row => row.id === `finding-${root.dataset.reviewSelected}`)
            || rows.find(row => row.dataset.reviewReviewed === 'false') || rows[0] || null;
        let eligible = rows;
        const dirtyRow = row => [...row.querySelectorAll('[data-review-form]')].some(form => dirtyForms.has(form));
        const refresh = () => {
            const query = normalize(search.value);
            let retained = 0;
            eligible = rows.filter(row => {
                const state = filter.value;
                const matches = (!query || normalize(row.dataset.reviewCorpus).includes(query))
                    && (!section.value || row.dataset.reviewSectionValue === section.value)
                    && (state === 'all' || (state === 'pending' && row.dataset.reviewReviewed === 'false')
                        || (state === 'reviewed' && row.dataset.reviewReviewed === 'true')
                        || (state === 'attention' && row.dataset.reviewAttention === 'true'));
                const dirty = dirtyRow(row);
                if (!matches && dirty) retained++;
                return matches || dirty;
            });
            if (focus && !eligible.includes(active)) active = eligible.find(row => row.dataset.reviewReviewed === 'false') || eligible[0] || null;
            rows.forEach(row => {
                row.hidden = focus ? row !== active : !eligible.includes(row);
                if (focus) row.open = row === active;
            });
            if (focus) {
                queue.hidden = false;
                [...queueSelect.options].forEach(option => {
                    const row = rows.find(item => item.id === option.value);
                    option.disabled = !eligible.includes(row);
                    option.textContent = `${option.dataset.queueLabel} · ${dirtyRow(row) ? 'Ulasan belum disimpan' : row.dataset.reviewReviewed === 'true' ? 'Telah diulas' : 'Belum diulas'}`;
                });
                queueSelect.value = active?.id || '';
                const unsaved = rows.filter(dirtyRow).length;
                position.textContent = active ? `Dapatan ${rows.indexOf(active) + 1} / ${rows.length}${unsaved ? ` · ${unsaved} ulasan belum disimpan` : ''}` : 'Tiada dapatan sepadan';
                root.querySelectorAll('[data-review-queue-prev], [data-review-queue-next]').forEach(button => { button.disabled = eligible.length < 2; });
            }
            const count = eligible.length;
            result.textContent = `${count} daripada ${rows.length} dapatan ${focus ? `dalam senarai; ${count ? 'satu' : 'tiada'} dapatan dipaparkan` : 'dipaparkan'}.${retained ? ` ${retained} dapatan dengan ulasan belum disimpan dikekalkan.` : ''}`;
            empty.hidden = count !== 0;
        };
        const openRow = row => {
            if (focus) { active = row; refresh(); }
            row.open = true;
            const parent = root.closest('[data-review-check-history]');
            if (parent) parent.open = true;
            row.querySelector('summary').focus({preventScroll: true});
            row.scrollIntoView({block: 'start', behavior: 'instant'});
            history.replaceState(null, '', `#${row.id}`);
        };
        const fromHash = () => {
            const row = recoverFailed || rows.find(item => `#${item.id}` === location.hash);
            recoverFailed = null;
            if (row) {
                search.value = ''; filter.value = 'all'; section.value = '';
                refresh(); openRow(row);
            }
        };
        [search, filter, section].forEach(control => control.addEventListener(control === search ? 'input' : 'change', refresh));
        root.querySelector('[data-review-reset]').addEventListener('click', () => {
            search.value = ''; filter.value = 'all'; section.value = ''; refresh(); search.focus();
        });
        root.querySelector('[data-review-collapse]').addEventListener('click', () => {
            rows.forEach(row => { if (!dirtyRow(row)) row.open = false; });
        });
        root.querySelector('[data-review-collapse]').hidden = focus;
        if (focus) {
            queueSelect.addEventListener('change', () => {
                const row = eligible.find(item => item.id === queueSelect.value);
                if (row) openRow(row);
            });
            const move = direction => {
                if (eligible.length < 2) return;
                const index = eligible.indexOf(active);
                openRow(eligible[(index + direction + eligible.length) % eligible.length]);
            };
            root.querySelector('[data-review-queue-prev]').addEventListener('click', () => move(-1));
            root.querySelector('[data-review-queue-next]').addEventListener('click', () => move(1));
        }
        root.querySelectorAll('[data-review-next]').forEach(button => {
            button.hidden = focus && 'reviewOverviewNext' in button.dataset;
            button.addEventListener('click', () => {
                const current = button.closest('[data-review-finding]') || (focus ? active : rows.find(row => `#${row.id}` === location.hash));
                const start = rows.indexOf(current);
                const sequence = [...rows.slice(start + 1), ...rows.slice(0, start + 1)];
                const next = sequence.find(row => eligible.includes(row) && row !== current && row.dataset.reviewReviewed === 'false');
                if (next) openRow(next);
                else result.textContent = 'Tiada dapatan lain yang belum diulas dalam paparan ini.';
            });
        });
        root.querySelectorAll('[data-review-form]').forEach(form => {
            const row = form.closest('[data-review-finding]');
            const inputs = [...form.querySelectorAll('select, textarea')];
            const initial = inputs.map(input => input.value);
            const update = () => {
                navigating = false;
                const dirty = row.dataset.reviewFailed === 'true' || inputs.some((input, index) => input.value !== initial[index]);
                if (dirty) dirtyForms.add(form); else dirtyForms.delete(form);
                row.querySelector('[data-review-dirty]').hidden = !dirty;
                refresh();
            };
            inputs.forEach(input => input.addEventListener('input', update));
            if (row.dataset.reviewFailed === 'true') { dirtyForms.add(form); row.querySelector('[data-review-dirty]').hidden = false; }
            form.addEventListener('submit', () => {
                // A successful save reloads the page. Retain the navigation warning for other edited rows.
                navigating = ![...dirtyForms].some(item => item !== form);
            });
            const standard = form.querySelector('[data-review-use-standard]');
            if (standard) {
                standard.hidden = false;
                standard.addEventListener('click', () => {
                    const text = form.querySelector('[data-review-standard-text]').textContent.trim();
                    const comment = form.querySelector('[data-review-comment]');
                    const addition = comment.value.trim() ? `\n\n${text}` : text;
                    if (comment.value.length + addition.length > comment.maxLength) {
                        result.textContent = 'Ulasan melebihi had 2,000 aksara. Ringkaskan teks sebelum memasukkan ulasan piawai.';
                        comment.focus(); return;
                    }
                    comment.value += addition; update(); comment.focus();
                });
            }
        });
        root.querySelector('[data-review-tools]').hidden = false;
        refresh(); fromHash();
        window.addEventListener('hashchange', fromHash);
    });
    window.addEventListener('beforeunload', event => {
        if (dirtyForms.size && !navigating) { event.preventDefault(); event.returnValue = ''; }
    });
    const revealContext = () => {
        const target = document.getElementById?.(location.hash.slice(1));
        if (!target) return;
        for (let parent = target.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
        }
    };
    revealContext(); window.addEventListener('hashchange', revealContext);
    // ADR-398: keep the task summary and plan clear of the wrapping workspace header.
    const reviewPage = document.querySelector('[data-review-page]');
    const taskStatus = document.querySelector('[data-review-task-status]');
    const workspaceHeader = document.querySelector('.topbar');
    if (reviewPage && taskStatus && workspaceHeader) {
        const measure = () => {
            reviewPage.style.setProperty('--review-header-offset', `${Math.ceil(workspaceHeader.getBoundingClientRect().height)}px`);
            reviewPage.style.setProperty('--review-status-height', `${Math.ceil(taskStatus.getBoundingClientRect().height)}px`);
        };
        measure();
        if (typeof ResizeObserver === 'function') {
            const observer = new ResizeObserver(measure);
            observer.observe(workspaceHeader); observer.observe(taskStatus);
        } else window.addEventListener('resize', measure);
    }
})();
