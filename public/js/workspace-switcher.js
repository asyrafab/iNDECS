// Keep the native disclosure as the no-JavaScript and keyboard fallback.
document.querySelectorAll('.workspace-switcher').forEach(disclosure => {
    const summary = disclosure.querySelector('summary');
    const navigation = disclosure.querySelector('nav');
    if (!summary || !navigation || typeof disclosure.animate !== 'function') return;
    const preference = matchMedia('(prefers-reduced-motion: reduce)');
    const reduced = () => preference.matches || document.documentElement.dataset.motion === 'reduced';
    let desired = disclosure.open;
    let animation = null;
    const overflow = disclosure.style.overflow;
    const finish = () => {
        animation?.cancel();
        animation = null;
        disclosure.open = desired;
        disclosure.dataset.expanded = String(desired);
        disclosure.style.overflow = overflow;
        navigation.inert = false;
    };
    disclosure.dataset.expanded = String(desired);
    summary.addEventListener('click', event => {
        event.preventDefault();
        desired = !desired;
        const start = disclosure.getBoundingClientRect().height;
        animation?.cancel();
        animation = null;
        const tokens = getComputedStyle(document.documentElement);
        const timing = tokens.getPropertyValue('--duration-normal').trim();
        const duration = parseFloat(timing) * (timing.endsWith('ms') ? 1 : 1000);
        if (reduced() || document.hidden || !duration) { finish(); return; }
        disclosure.open = desired;
        const end = disclosure.getBoundingClientRect().height;
        disclosure.open = true;
        disclosure.dataset.expanded = String(desired);
        disclosure.style.overflow = 'hidden';
        navigation.inert = !desired;
        animation = disclosure.animate([{ height: `${start}px` }, { height: `${end}px` }], {
            duration, easing: tokens.getPropertyValue('--ease-standard').trim(), fill: 'both',
        });
        animation.onfinish = finish;
    });
    disclosure.addEventListener('toggle', () => {
        if (!animation) {
            desired = disclosure.open;
            disclosure.dataset.expanded = String(desired);
        }
    });
    preference.addEventListener('change', () => { if (reduced()) finish(); });
    new MutationObserver(() => { if (reduced()) finish(); })
        .observe(document.documentElement, { attributes: true, attributeFilter: ['data-motion'] });
    document.addEventListener('visibilitychange', () => { if (document.hidden) finish(); });
});
