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
        const dirtyRow = row => [...row.querySelectorAll('[data-review-form]')].some(form => dirtyForms.has(form));
        const refresh = () => {
            const query = normalize(search.value);
            let retained = 0;
            rows.forEach(row => {
                const state = filter.value;
                const matches = (!query || normalize(row.dataset.reviewCorpus).includes(query))
                    && (!section.value || row.dataset.reviewSectionValue === section.value)
                    && (state === 'all' || (state === 'pending' && row.dataset.reviewReviewed === 'false')
                        || (state === 'reviewed' && row.dataset.reviewReviewed === 'true')
                        || (state === 'attention' && row.dataset.reviewAttention === 'true'));
                const dirty = dirtyRow(row);
                row.hidden = !matches && !dirty;
                if (!matches && dirty) retained++;
            });
            const count = rows.filter(row => !row.hidden).length;
            result.textContent = `${count} daripada ${rows.length} dapatan dipaparkan.${retained ? ` ${retained} dapatan dengan ulasan belum disimpan dikekalkan.` : ''}`;
            empty.hidden = count !== 0;
        };
        const openRow = row => {
            row.open = true;
            const parent = root.closest('[data-review-check-history]');
            if (parent) parent.open = true;
            row.querySelector('summary').focus({preventScroll: true});
            row.scrollIntoView({block: 'start', behavior: 'instant'});
            history.replaceState(null, '', `#${row.id}`);
        };
        const fromHash = () => {
            const row = rows.find(item => `#${item.id}` === location.hash);
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
        root.querySelectorAll('[data-review-next]').forEach(button => {
            button.hidden = false;
            button.addEventListener('click', () => {
                const current = button.closest('[data-review-finding]') || rows.find(row => `#${row.id}` === location.hash);
                const start = rows.indexOf(current);
                const sequence = [...rows.slice(start + 1), ...rows.slice(0, start + 1)];
                const next = sequence.find(row => !row.hidden && row !== current && row.dataset.reviewReviewed === 'false');
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
                const dirty = inputs.some((input, index) => input.value !== initial[index]);
                if (dirty) dirtyForms.add(form); else dirtyForms.delete(form);
                row.querySelector('[data-review-dirty]').hidden = !dirty;
                refresh();
            };
            inputs.forEach(input => input.addEventListener('input', update));
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
})();
