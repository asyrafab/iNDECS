/* Finite introduction and service guidance for the public landing page. */
(function () {
    const introduction = document.querySelector('.landing-introduction');
    const replay = document.querySelector('[data-introduction-replay]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let finishTimer;

    function reducedMotion() {
        return motion.matches || document.documentElement.dataset.motion === 'reduced';
    }

    function stopIntroduction() {
        clearTimeout(finishTimer);
        introduction?.classList.remove('is-playing');
    }

    function playIntroduction() {
        stopIntroduction();
        if (!introduction || reducedMotion()) return;
        // Restart the finite CSS animation when the owner chooses to replay it.
        void introduction.offsetWidth;
        introduction.classList.add('is-playing');
        const duration = parseFloat(getComputedStyle(introduction).animationDuration) * 1000;
        finishTimer = setTimeout(stopIntroduction, duration);
    }

    function syncMotion() {
        if (reducedMotion()) stopIntroduction();
        if (replay) {
            replay.disabled = reducedMotion();
            replay.title = reducedMotion() ? 'Pengenalan dimatikan kerana gerakan dikurangkan' : 'Main semula pengenalan iNDECS';
        }
    }

    function openGuidance(hash = location.hash) {
        const target = document.getElementById(hash.slice(1));
        if (target?.matches('.landing-questions details')) {
            target.open = true;
            target.querySelector('summary')?.focus({ preventScroll: true });
        }
    }

    replay?.addEventListener('click', playIntroduction);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') stopIntroduction();
    });
    motion.addEventListener('change', syncMotion);
    new MutationObserver(syncMotion).observe(document.documentElement, { attributes: true, attributeFilter: ['data-motion'] });
    window.addEventListener('hashchange', () => openGuidance());
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href^="#panduan-"]');
        if (link) openGuidance(link.hash);
    });
    syncMotion();
    openGuidance();
    playIntroduction();
})();
