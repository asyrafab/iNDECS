// Shared presentation for local guidance in the drawer and Kaunter Maya.
const amiraReplyStates = new WeakMap();
const amiraActiveReplies = new Set();
const amiraMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const amiraReducedMotion = () => amiraMotion.matches || document.documentElement.dataset.motion === 'reduced';

function animateAmiraReply(root, heading, content, question, options = {}) {
    amiraReplyStates.get(root)?.finish();
    const answer = root.querySelector('[data-amira-answer]');
    const copy = root.querySelector('[data-amira-answer-copy]');
    const user = root.querySelector('[data-amira-user]');
    const avatar = options.avatar || root.querySelector('[data-amira-bubble]');
    const text = content || '';
    let frame = 0;
    const animations = [];
    let finished = false;
    let resolveDone;
    const done = new Promise(resolve => { resolveDone = resolve; });
    const state = {
        finish() {
            if (finished) return;
            finished = true;
            cancelAnimationFrame(frame);
            animations.forEach(animation => animation.cancel());
            copy.textContent = text;
            copy.style.minHeight = '';
            answer.setAttribute('aria-busy', 'false');
            if (avatar) window.AmiraBubble?.setSpeaking(avatar, false);
            amiraActiveReplies.delete(state);
            options.onUpdate?.();
            resolveDone();
        },
    };
    amiraReplyStates.set(root, state);
    answer.setAttribute('aria-busy', 'true');
    const title = root.querySelector('[data-amira-answer-title]');
    title.textContent = heading;
    title.hidden = !heading;
    user.textContent = question || '';
    user.hidden = !question;
    answer.hidden = false;
    copy.textContent = text;
    if (options.immediate || amiraReducedMotion() || document.hidden || !text) { state.finish(); return done; }

    // Measure complete text once; reveal into its reserved space without reflowing every letter.
    copy.style.minHeight = `${copy.getBoundingClientRect().height}px`;
    copy.textContent = '';
    const styles = getComputedStyle(document.documentElement);
    const milliseconds = name => {
        const value = styles.getPropertyValue(name).trim();
        return parseFloat(value) * (value.endsWith('ms') ? 1 : 1000);
    };
    const letters = typeof Intl.Segmenter === 'function'
        ? Array.from(new Intl.Segmenter('ms', { granularity: 'grapheme' }).segment(text), part => part.segment)
        : Array.from(text);
    const duration = Math.min(letters.length * milliseconds('--duration-amira-character'), milliseconds('--duration-amira-reply-max'));
    const scale = styles.getPropertyValue('--scale-amira-message-enter').trim();
    [user, answer].filter(box => !box.hidden && typeof box.animate === 'function').forEach(box => {
        animations.push(box.animate([
            { opacity: 0, transform: `scale(${scale})`, transformOrigin: box === user ? 'top right' : 'top left' },
            { opacity: 1, transform: 'scale(1)', transformOrigin: box === user ? 'top right' : 'top left' },
        ], { duration: milliseconds('--duration-amira-message-enter'), easing: styles.getPropertyValue('--ease-standard').trim() }));
    });
    amiraActiveReplies.add(state);
    if (avatar) window.AmiraBubble?.setSpeaking(avatar, true);
    const started = performance.now();
    let shown = 0;
    const type = now => {
        if (amiraReducedMotion() || document.hidden || !root.isConnected) { state.finish(); return; }
        const count = Math.min(letters.length, Math.floor((now - started) / duration * letters.length));
        if (count !== shown) { copy.textContent = letters.slice(0, count).join(''); shown = count; options.onUpdate?.(); }
        if (count === letters.length) state.finish(); else frame = requestAnimationFrame(type);
    };
    frame = requestAnimationFrame(type);
    return done;
}

const finishAmiraReplies = () => [...amiraActiveReplies].forEach(state => state.finish());
amiraMotion.addEventListener('change', () => { if (amiraReducedMotion()) finishAmiraReplies(); });
new MutationObserver(() => { if (amiraReducedMotion()) finishAmiraReplies(); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['data-motion'] });
document.addEventListener('visibilitychange', () => { if (document.hidden) finishAmiraReplies(); });

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('#sidebar');
    const menuToggle = document.querySelector('[data-menu-toggle]');

    const mobile = window.matchMedia('(max-width: 900px)');
    const sidebarBackdrop = document.querySelector('[data-menu-close]');
    const pageShell = document.querySelector('.page-shell');
    const assistant = document.querySelector('#amira-panel');
    const assistantBackdrop = document.createElement('button');
    assistantBackdrop.className = 'assistant-backdrop';
    assistantBackdrop.type = 'button';
    assistantBackdrop.hidden = true;
    assistantBackdrop.setAttribute('aria-label', 'Tutup panduan AMIRA');
    if (assistant) document.body.append(assistantBackdrop);
    let assistantOpener = null;

    const focusable = (element) => [...element.querySelectorAll('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), summary')]
        .filter(node => node.getClientRects().length && !node.closest('[hidden], [inert]'));
    const assistantIsModal = () => mobile.matches || document.body.matches('.public-page, .auth-page, .portal-page');
    const setSidebar = (open, returnFocus = true) => {
        if (!sidebar) return;
        const showing = mobile.matches && open;
        sidebar.classList.toggle('open', showing);
        sidebar.inert = mobile.matches && !showing;
        if (sidebarBackdrop) sidebarBackdrop.hidden = !showing;
        if (pageShell) pageShell.inert = showing;
        menuToggle?.setAttribute('aria-expanded', String(showing));
        if (showing) focusable(sidebar)[0]?.focus();
        else if (returnFocus && mobile.matches) menuToggle?.focus();
    };
    /* Desktop: the arrow on the sidebar's right edge collapses it so the page (e.g. the meeting map) gets the full width. */
    const root = document.documentElement;
    const edgeToggle = document.querySelector('[data-sidebar-collapse]');
    const syncCollapsed = () => {
        const collapsed = root.dataset.sidebar === 'collapsed';
        const text = collapsed ? 'Tunjuk menu' : 'Sembunyi menu';
        edgeToggle?.setAttribute('aria-expanded', String(!collapsed));
        edgeToggle?.setAttribute('aria-label', text);
        edgeToggle?.setAttribute('title', text);
    };
    const setCollapsed = (collapsed) => {
        if (collapsed) root.dataset.sidebar = 'collapsed';
        else delete root.dataset.sidebar;
        try { localStorage.setItem('indecs.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch { /* The choice lasts for this page only. */ }
        syncCollapsed();
    };
    const setAssistant = (open, moveFocus = true) => {
        if (!assistant) return;
        if (open) setSidebar(false, false);
        assistant.hidden = !open;
        document.body.classList.toggle('assistant-open', open);
        const modal = open && assistantIsModal();
        assistantBackdrop.hidden = !modal;
        if (modal) { assistant.setAttribute('role', 'dialog'); assistant.setAttribute('aria-modal', 'true'); }
        else { assistant.removeAttribute('role'); assistant.removeAttribute('aria-modal'); }
        document.querySelectorAll('[data-amira-open]').forEach(button => button.setAttribute('aria-expanded', String(open)));
        try { if (pageShell) sessionStorage.setItem('indecs.amira.open', String(open)); } catch { /* Session persistence is optional. */ }
        if (moveFocus) {
            if (open) assistant.querySelector('[data-amira-close]')?.focus();
            else assistantOpener?.focus();
        }
    };
    setSidebar(false, false);
    syncCollapsed();
    edgeToggle?.addEventListener('click', () => setCollapsed(root.dataset.sidebar !== 'collapsed'));
    menuToggle?.addEventListener('click', () => {
        setAssistant(false, false);
        setSidebar(!sidebar.classList.contains('open'));
    });
    sidebarBackdrop?.addEventListener('click', () => setSidebar(false));
    /* Pages in map mode can collapse the topbar; the choice is remembered like the sidebar's. */
    const headerToggles = document.querySelectorAll('[data-header-toggle]');
    const syncHeader = () => {
        const collapsed = root.dataset.header === 'collapsed';
        const text = collapsed ? 'Tunjuk pengepala' : 'Sembunyi pengepala';
        headerToggles.forEach(button => {
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', text);
            button.setAttribute('title', text);
        });
    };
    headerToggles.forEach(button => button.addEventListener('click', () => {
        const collapsed = root.dataset.header !== 'collapsed';
        if (collapsed) root.dataset.header = 'collapsed';
        else delete root.dataset.header;
        try { localStorage.setItem('indecs.header', collapsed ? 'collapsed' : 'expanded'); } catch { /* The choice lasts for this page only. */ }
        syncHeader();
    }));
    syncHeader();
    document.querySelector('[data-menu-close-inline]')?.addEventListener('click', () => setSidebar(false));
    document.querySelectorAll('[data-amira-open]').forEach(button => button.addEventListener('click', () => {
        assistantOpener = button;
        setAssistant(assistant?.hidden ?? false);
    }));
    assistant?.querySelector('[data-amira-close]')?.addEventListener('click', () => setAssistant(false));
    assistantBackdrop.addEventListener('click', () => setAssistant(false));
    let restoreAssistant = false;
    try { restoreAssistant = sessionStorage.getItem('indecs.amira.open') === 'true'; } catch { /* Default closed. */ }
    if (pageShell && !mobile.matches && restoreAssistant) setAssistant(true, false);
    mobile.addEventListener('change', () => {
        setSidebar(false, false);
        if (assistant && !assistant.hidden) setAssistant(true, false);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (sidebar?.classList.contains('open')) setSidebar(false);
            else if (assistant && !assistant.hidden) setAssistant(false);
            document.querySelectorAll('.workspace-switcher[open]').forEach(details => { details.open = false; });
        }
        const activePanel = sidebar?.classList.contains('open') ? sidebar : assistant && !assistant.hidden && assistantIsModal() ? assistant : null;
        if (event.key !== 'Tab' || !activePanel) return;
        const nodes = focusable(activePanel);
        if (!nodes.length) return;
        if (event.shiftKey && document.activeElement === nodes[0]) { event.preventDefault(); nodes.at(-1).focus(); }
        else if (!event.shiftKey && document.activeElement === nodes.at(-1)) { event.preventDefault(); nodes[0].focus(); }
    });
    document.addEventListener('click', event => {
        document.querySelectorAll('.workspace-switcher[open]').forEach(details => {
            if (!details.contains(event.target)) details.open = false;
        });
    });

    // Local, evidence-grounded page help. No model call, external request or workflow mutation.
    const showGuidance = (root, kind, question) => {
        if (!root) return;
        const main = document.querySelector('#main-content');
        let heading = 'Panduan halaman ini';
        let content = root.dataset.guide;
        if (kind === 'fields') {
            const labels = [...new Set([...main.querySelectorAll('form .field > span')].map(label => label.textContent.trim()))].slice(0, 12);
            heading = 'Medan yang tersedia';
            content = labels.length ? `Semak medan berikut pada borang: ${labels.join('; ')}. Medan bertanda * perlu dilengkapkan. Saya tidak mengisi atau menghantar borang bagi pihak anda.` : 'Tiada borang maklumat pada halaman ini. Buka halaman pendaftaran atau tindakan permohonan yang tersedia untuk peranan anda.';
        } else if (kind === 'next') {
            heading = 'Langkah seterusnya';
            // AMIRA's own composer sits inside #main-content on Kaunter Maya pages; never list it as a page action.
            const actions = [...new Set([...main.querySelectorAll('form button[type="submit"], form button:not([type])')].filter(button => !button.closest('[data-amira-form]')).map(button => button.textContent.trim()).filter(Boolean))].slice(0, 4);
            content = root.dataset.reference ? `Rekod ${root.dataset.reference}${root.dataset.stage ? ` berada pada peringkat ${root.dataset.stage}` : ''}. ${content}` : content;
            if (actions.length) content += ` Tindakan pada halaman: ${actions.join('; ')}. Semak maklumat sebelum membuat keputusan.`;
        } else if (kind === 'unavailable') {
            heading = 'Panduan sistem setempat';
            content = 'Perbualan AI AMIRA belum tersedia. Pilih panduan halaman, bantuan borang atau langkah seterusnya untuk panduan berdasarkan halaman ini. Semakan dokumen melalui AIRS dan jawapan AI langsung belum disambungkan.';
        }
        animateAmiraReply(root, heading, content, question);
    };
    // The drawer (#amira-panel) and the inline Kaunter Maya panel ([data-amira-guidance]) share one guidance engine.
    [assistant, ...document.querySelectorAll('[data-amira-guidance]')].filter(Boolean).forEach(root => {
    if (root.hasAttribute('data-amira-session')) return;
    // Inline AMIRA controls ship hidden so they stay inert without JavaScript.
    root.querySelectorAll('[data-amira-interactive][hidden]').forEach(node => { node.hidden = false; });
    root.querySelectorAll('[data-amira-prompt]').forEach(button => button.addEventListener('click', () => showGuidance(root, button.dataset.amiraPrompt, button.textContent.trim())));
    root.querySelector('[data-amira-form]:not([data-amira-chat])')?.addEventListener('submit', event => {
        event.preventDefault();
        const input = root.querySelector('[data-amira-input], #amira-question');
        const question = input.value.trim();
        if (!question) return;
        const kind = /borang|medan|isi/i.test(question) ? 'fields' : /seterusnya|teruskan|tindakan|peringkat/i.test(question) ? 'next' : /halaman|dokumen|panduan/i.test(question) ? 'page' : 'unavailable';
        showGuidance(root, kind, question);
        input.value = '';
    });
    });
    assistant?.querySelector('[data-amira-source]')?.addEventListener('click', () => {
        if (assistantIsModal()) setAssistant(false, false);
        document.querySelector('#main-content')?.focus();
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const name = repeater.dataset.repeater;
        const list = repeater.querySelector('[data-repeater-items]');
        const template = repeater.querySelector('[data-repeater-template]');
        const addButton = document.querySelector(`[data-repeater-add="${name}"]`);

        if (!list || !template || !addButton) return;

        const renumber = () => {
            list.querySelectorAll('[data-repeater-item]').forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    const prefix = `${name}[`;
                    if (!field.name.startsWith(prefix)) return;
                    const end = field.name.indexOf(']', prefix.length);
                    if (end !== -1) field.name = `${prefix}${index}${field.name.slice(end)}`;
                });
                const number = row.querySelector('.row-number');
                if (number) number.textContent = String(index + 1);
            });
        };

        addButton.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-repeater-item]').length;
            const fragment = template.content.cloneNode(true);

            fragment.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replaceAll('__INDEX__', String(index));
            });

            list.appendChild(fragment);
            renumber();
            list.querySelector('[data-repeater-item]:last-child input')?.focus();
        });

        list.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-repeater-remove]');
            if (!removeButton) return;

            const rows = list.querySelectorAll('[data-repeater-item]');
            if (name === 'lots' && rows.length === 1) return;

            removeButton.closest('[data-repeater-item]')?.remove();
            renumber();
        });

        renumber();
    });
});
