/* Upload-first part 2: when the background check fills draft details while the form is open, put them into
   boxes that are still empty and take the new draft version, so saving merges instead of conflicting.
   Anything the applicant typed is kept. */
(function () {
    const form = document.querySelector('form.application-form');
    if (!form || !form.elements.version) return;
    const note = (field, anchor, name) => {
        if (!anchor || form.querySelector('[data-prefill-note="' + field + '"]')) return;
        const small = document.createElement('small');
        small.className = 'prefill-note';
        small.dataset.prefillNote = field;
        small.textContent = 'Diisi daripada ' + name + ' — sila semak.';
        anchor.insertAdjacentElement('afterend', small);
    };
    const merge = () => {
        const holder = document.querySelector('[data-prefill]');
        if (!holder) return;
        let payload;
        try { payload = JSON.parse(holder.dataset.prefill); } catch (e) { return; }
        const current = parseInt(form.elements.version.value, 10);
        if (!payload || !(payload.version > current)) return;
        const fields = payload.fields || {}, sources = payload.sources || {};
        ['title', 'site_address'].forEach(field => {
            const box = form.elements[field];
            if (typeof fields[field] !== 'string' || !box) return;
            if (box.value.trim() === '') { box.value = fields[field]; note(field, box, sources[field]); }
        });
        if (Array.isArray(fields.lighting_types)) {
            const boxes = [...form.querySelectorAll('input[name="lighting_types[]"]')];
            if (boxes.length && !boxes.some(box => box.checked)) {
                boxes.forEach(box => { box.checked = fields.lighting_types.includes(box.value); });
                note('lighting_types', boxes[boxes.length - 1].closest('fieldset'), sources.lighting_types);
            }
        }
        form.elements.version.value = String(payload.version);
    };
    // The checklist section (which carries the data) is replaced by the pre-check poll in portal.js.
    new MutationObserver(merge).observe(document.body, { childList: true, subtree: true });
    merge();
})();
