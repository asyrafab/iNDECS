/* ADR-395: only same-origin document URLs supplied by this check's server-rendered manifest. */
(() => {
    document.querySelectorAll('[data-technical-review]').forEach(root => {
        const panel = root.querySelector('[data-review-viewer]');
        const selector = panel?.querySelector('[data-review-document]');
        if (!selector) return;
        const frame = panel.querySelector('[data-review-document-frame]');
        const page = panel.querySelector('[data-review-document-page]');
        const caption = panel.querySelector('[data-review-document-caption]');
        const fallback = panel.querySelector('[data-review-document-fallback]');
        const options = [...selector.options];
        const historical = root.closest('[data-review-check-history]');
        const show = (id, number = 1) => {
            const option = options.find(item => item.value === id);
            if (!option) return false;
            let url;
            try { url = new URL(option.dataset.documentUrl, location.href); } catch { return false; }
            if (url.origin !== location.origin || !['http:', 'https:'].includes(url.protocol)) return false;
            const targetPage = /^\d+$/.test(String(number)) && Number(number) >= 1 && Number(number) <= 99999 ? Number(number) : 1;
            url.hash = `page=${targetPage}&view=FitH`;
            selector.value = id;
            page.value = String(targetPage);
            if (frame.getAttribute('src') !== url.href) frame.setAttribute('src', url.href);
            frame.title = `Pratonton ${option.textContent.trim()} - halaman ${targetPage}`;
            fallback.href = url.href;
            caption.textContent = `${option.textContent.trim()} · Halaman ${targetPage}.`;
            return true;
        };
        const showFinding = row => {
            const evidence = [...row.querySelectorAll('[data-review-evidence]')]
                .find(link => options.some(option => option.value === link.dataset.reviewEvidence));
            if (evidence) show(evidence.dataset.reviewEvidence, evidence.dataset.reviewPage);
            else caption.textContent = 'Tiada rujukan pratonton bagi dapatan ini. Pilih dokumen dalam senarai untuk semakan.';
        };
        selector.addEventListener('change', () => show(selector.value));
        panel.querySelector('[data-review-go-page]').addEventListener('click', () => {
            if (!/^\d+$/.test(page.value) || Number(page.value) < 1 || Number(page.value) > 99999) {
                caption.textContent = 'Masukkan nombor halaman antara 1 hingga 99,999.'; page.focus(); return;
            }
            show(selector.value, page.value);
        });
        page.addEventListener('keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); panel.querySelector('[data-review-go-page]').click(); }
        });
        root.querySelectorAll('[data-review-evidence]').forEach(link => link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (show(link.dataset.reviewEvidence, link.dataset.reviewPage)) event.preventDefault();
        }));
        const rows = [...root.querySelectorAll('[data-review-finding]')];
        rows.forEach(row => row.addEventListener('toggle', () => { if (row.open) showFinding(row); }));
        const initialize = () => {
            if (historical && !historical.open) return;
            const active = rows.find(row => `#${row.id}` === location.hash) || rows.find(row => row.open);
            if (active) showFinding(active); else show(selector.value);
        };
        historical?.addEventListener('toggle', () => { if (historical.open && !frame.getAttribute('src')) initialize(); });
        panel.querySelector('[data-review-page-controls]').hidden = false;
        initialize();
    });
})();
