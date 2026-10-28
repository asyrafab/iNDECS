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
    const assistantIsModal = () => mobile.matches || document.body.matches('.public-page, .auth-page');
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
    menuToggle?.addEventListener('click', () => {
        setAssistant(false, false);
        setSidebar(!sidebar.classList.contains('open'));
    });
    sidebarBackdrop?.addEventListener('click', () => setSidebar(false));
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
    const showGuidance = (kind, question) => {
        if (!assistant) return;
        const main = document.querySelector('#main-content');
        const title = assistant.querySelector('[data-amira-answer-title]');
        const copy = assistant.querySelector('[data-amira-answer-copy]');
        const user = assistant.querySelector('[data-amira-user]');
        user.hidden = !question;
        user.textContent = question || '';
        let heading = 'Panduan halaman ini';
        let content = assistant.dataset.guide;
        if (kind === 'fields') {
            const labels = [...new Set([...main.querySelectorAll('form .field > span')].map(label => label.textContent.trim()))].slice(0, 12);
            heading = 'Medan yang tersedia';
            content = labels.length ? `Semak medan berikut pada borang: ${labels.join('; ')}. Medan bertanda * perlu dilengkapkan. Saya tidak mengisi atau menghantar borang bagi pihak anda.` : 'Tiada borang maklumat pada halaman ini. Buka halaman pendaftaran atau tindakan permohonan yang tersedia untuk peranan anda.';
        } else if (kind === 'next') {
            heading = 'Langkah seterusnya';
            const actions = [...new Set([...main.querySelectorAll('form button[type="submit"], form button:not([type])')].map(button => button.textContent.trim()))].slice(0, 4);
            content = assistant.dataset.reference ? `Rekod ${assistant.dataset.reference}${assistant.dataset.stage ? ` berada pada peringkat ${assistant.dataset.stage}` : ''}. ${content}` : content;
            if (actions.length) content += ` Tindakan pada halaman: ${actions.join('; ')}. Semak maklumat sebelum membuat keputusan.`;
        } else if (kind === 'unavailable') {
            heading = 'Panduan sistem setempat';
            content = 'Saya belum disambungkan kepada perbualan AI AIRS. Pilih panduan halaman, bantuan borang atau langkah seterusnya untuk panduan berdasarkan halaman ini. Semakan dokumen dan jawapan AI langsung belum tersedia.';
        }
        title.textContent = heading;
        copy.textContent = content;
        assistant.querySelector('[data-amira-answer]').hidden = false;
    };
    assistant?.querySelectorAll('[data-amira-prompt]').forEach(button => button.addEventListener('click', () => showGuidance(button.dataset.amiraPrompt, button.textContent.trim())));
    assistant?.querySelector('[data-amira-form]')?.addEventListener('submit', event => {
        event.preventDefault();
        const input = assistant.querySelector('#amira-question');
        const question = input.value.trim();
        if (!question) return;
        const kind = /borang|medan|isi/i.test(question) ? 'fields' : /seterusnya|teruskan|tindakan|peringkat/i.test(question) ? 'next' : /halaman|dokumen|panduan/i.test(question) ? 'page' : 'unavailable';
        showGuidance(kind, question);
        input.value = '';
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
