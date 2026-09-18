document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('#sidebar');
    const menuToggle = document.querySelector('[data-menu-toggle]');

    menuToggle?.addEventListener('click', () => sidebar?.classList.toggle('open'));

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
