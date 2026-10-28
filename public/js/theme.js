/* Apply appearance before CSS loads. Storage is optional; one shared teal accent. */
(function () {
    const root = document.documentElement;
    const modes = ['light', 'dark', 'system'];
    let mode = 'system';
    let motion = 'system';
    function read(key, fallback) {
        try { return localStorage.getItem(key) || fallback; } catch { return fallback; }
    }
    function write(key, value) {
        try { localStorage.setItem(key, value); } catch { /* In-memory preference remains usable. */ }
    }
    function sync() {
        document.querySelectorAll('[data-theme-mode]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.themeMode === mode));
        });
        document.querySelectorAll('[data-motion-toggle]').forEach(button => {
            button.setAttribute('aria-pressed', String(motion === 'reduced'));
        });
    }
    function apply() {
        if (mode === 'system') root.removeAttribute('data-theme');
        else root.dataset.theme = mode;
        root.dataset.motion = motion;
        root.removeAttribute('data-accent');
        sync();
    }
    const stored = read('indecs.theme.mode', 'system');
    mode = modes.includes(stored) ? stored : 'system';
    motion = read('indecs.theme.motion', 'system') === 'reduced' ? 'reduced' : 'system';
    try { localStorage.removeItem('indecs.theme.accent'); } catch { /* Legacy accent is ignored. */ }
    window.iNDECSTheme = {
        setMode(value) {
            if (!modes.includes(value)) return;
            mode = value; write('indecs.theme.mode', value); apply();
        },
        setMotion(value) {
            motion = value === 'reduced' ? 'reduced' : 'system';
            write('indecs.theme.motion', motion); apply();
        }
    };
    apply();
    document.addEventListener('DOMContentLoaded', sync);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-theme-mode]');
        if (button) window.iNDECSTheme.setMode(button.dataset.themeMode);
        if (event.target.closest('[data-motion-toggle]')) {
            window.iNDECSTheme.setMotion(motion === 'reduced' ? 'system' : 'reduced');
        }
    });
    window.addEventListener('storage', event => {
        if (event.key === 'indecs.theme.mode') mode = modes.includes(event.newValue) ? event.newValue : 'system';
        if (event.key === 'indecs.theme.motion') motion = event.newValue === 'reduced' ? 'reduced' : 'system';
        apply();
    });
})();
