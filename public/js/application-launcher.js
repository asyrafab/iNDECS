/* The homepage chooser uses native modal isolation; links stay server-authorised. */
(function () {
    const dialog = document.querySelector('[data-application-launcher]');
    const trigger = document.querySelector('[data-application-launcher-open]');
    if (!dialog || !trigger || typeof dialog.showModal !== 'function') return;
    function open() {
        if (dialog.open) return;
        dialog.classList.add('is-measuring');
        dialog.showModal();
        const origin = trigger.getBoundingClientRect();
        const panel = dialog.getBoundingClientRect();
        dialog.style.setProperty('--login-origin-x', `${origin.x + origin.width / 2 - panel.x - panel.width / 2}px`);
        dialog.style.setProperty('--login-origin-y', `${origin.y + origin.height / 2 - panel.y - panel.height / 2}px`);
        dialog.style.setProperty('--login-origin-scale', String(Math.min(.65, Math.max(.2, origin.width / panel.width))));
        void dialog.offsetWidth;
        dialog.classList.remove('is-measuring');
        (dialog.querySelector('.application-tile[href]') || dialog.querySelector('button'))?.focus();
    }
    trigger.addEventListener('click', open);
    dialog.querySelector('[data-application-launcher-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger.focus({ preventScroll: true }));
    dialog.addEventListener('click', event => {
        const rect = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
    });
    dialog.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const controls = [...dialog.querySelectorAll('button, a[href]')].filter(node => !node.disabled && node.getClientRects().length);
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    open();
})();
