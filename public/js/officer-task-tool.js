/* ADR-400: page-local layout only; navigation never saves or assesses findings. */
(() => {
    const tool = document.querySelector('[data-task-tool]');
    const header = document.querySelector('.topbar');
    const workspace = document.querySelector('.page-content');
    if (!tool || !header || !workspace) return;
    const handle = tool.querySelector('[data-task-drag]');
    const reset = tool.querySelector('[data-task-reset]');
    const gap = parseFloat(getComputedStyle(tool).getPropertyValue('--space-2'));
    if (!Number.isFinite(gap)) return;
    let offset = {x: 0, y: 0}, desired = null, drag = null;
    const paint = () => { tool.style.transform = `translate(${offset.x}px, ${offset.y}px)`; };
    const place = (x, y) => {
        const rect = tool.getBoundingClientRect();
        const left = Math.max(gap, workspace.getBoundingClientRect().left + gap);
        const top = Math.max(gap, header.getBoundingClientRect().bottom + gap);
        tool.style.maxHeight = `${Math.max(0, innerHeight - top - gap)}px`;
        const height = tool.getBoundingClientRect().height;
        const next = {x: Math.max(left, Math.min(x, innerWidth - rect.width - gap)),
            y: Math.max(top, Math.min(y, innerHeight - height - gap))};
        offset.x += next.x - rect.left; offset.y += next.y - rect.top; paint();
        return next;
    };
    const reflow = () => {
        const rect = tool.getBoundingClientRect();
        const position = place(desired?.x ?? rect.left, desired?.y ?? rect.top);
        if (desired) desired = position;
    };
    const finish = commit => {
        if (!drag) return;
        const previous = drag; drag = null;
        document.documentElement.classList.remove('review-task-dragging');
        if (!commit) { offset = previous.offset; desired = previous.desired; paint(); reflow(); }
        if (handle.hasPointerCapture(previous.id)) handle.releasePointerCapture(previous.id);
    };
    const restore = () => { finish(false); offset = {x: 0, y: 0}; desired = null; paint(); reflow(); };
    handle.hidden = false; reset.hidden = false;
    handle.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); });
    handle.addEventListener('pointerdown', event => {
        if (event.button !== 0 || event.isPrimary === false || drag) return;
        event.preventDefault(); event.stopPropagation(); handle.focus({preventScroll: true});
        const rect = tool.getBoundingClientRect();
        drag = {id: event.pointerId, dx: event.clientX - rect.left, dy: event.clientY - rect.top,
            offset: {...offset}, desired: desired ? {...desired} : null};
        handle.setPointerCapture(event.pointerId);
        document.documentElement.classList.add('review-task-dragging');
    });
    handle.addEventListener('pointermove', event => {
        if (drag?.id === event.pointerId) desired = place(event.clientX - drag.dx, event.clientY - drag.dy);
    });
    handle.addEventListener('pointerup', event => { if (drag?.id === event.pointerId) finish(true); });
    handle.addEventListener('pointercancel', () => finish(false));
    handle.addEventListener('lostpointercapture', () => finish(false));
    handle.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); finish(false); return; }
        if (event.key === 'Home') { event.preventDefault(); restore(); return; }
        const step = event.shiftKey ? 40 : 10;
        const delta = {ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step]}[event.key];
        if (!delta || drag) return;
        event.preventDefault(); event.stopPropagation();
        const rect = tool.getBoundingClientRect(); desired = place(rect.left + delta[0], rect.top + delta[1]);
    });
    reset.addEventListener('click', restore);
    tool.querySelector('[data-task-jump]')?.addEventListener('click', event => {
        const root = document.getElementById(`review-list-${tool.dataset.taskCheck}`);
        const rows = [...root?.querySelectorAll('[data-review-finding]') || []];
        const focused = root?.closest('[data-technical-review]')?.dataset.reviewFocus === 'true';
        let current = rows.findIndex(row => focused ? row.open && !row.hidden : `#${row.id}` === location.hash);
        if (current < 0) current = rows.findIndex(row => row.open && !row.hidden);
        const next = [...rows.slice(current + 1), ...rows.slice(0, current + 1)]
            .find(row => row.dataset.reviewUnsettled === 'true');
        if (!next) return;
        event.preventDefault(); event.stopPropagation();
        if (location.hash === `#${next.id}`) window.dispatchEvent(new Event('hashchange'));
        else location.hash = next.id;
    });
    tool.addEventListener('toggle', reflow);
    window.addEventListener('resize', reflow);
    window.addEventListener('scroll', () => { if (desired) reflow(); }, {passive: true});
    window.addEventListener('blur', () => finish(false));
    document.addEventListener('visibilitychange', () => { if (document.hidden) finish(false); });
    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(reflow); observer.observe(tool); observer.observe(header);
    }
    reflow();
})();
