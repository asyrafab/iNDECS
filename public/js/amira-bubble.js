/*
 * AMIRA avatar (ADR-361): an uneven bubble that moves with her mood, and three
 * horizontal rings that are her voice. Animates every [data-amira-bubble] from its
 * data-mood and data-speaking attributes. Colours come from tokens.css only.
 * The gradient and glow are an owner-approved exception limited to this canvas.
 */
(function () {
    'use strict';

    const root = document.documentElement;
    const motionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const reducedMotion = () => (motionQuery && motionQuery.matches) || root.dataset.motion === 'reduced';

    // Motion per mood (spec §5). Colours: [light, mid, deep, ring] token names.
    const MOODS = {
        calm:    { lump: .07, spd: .6,  sx: 1,    sy: 1,    dy: 0,  bounce: 0,  lean: 0,   nudge: 0, tokens: ['--color-accent-200', '--color-accent-400', '--color-accent-600', '--color-accent-400'] },
        happy:   { lump: .09, spd: 1.4, sx: .95,  sy: 1.08, dy: -8, bounce: 10, lean: 0,   nudge: 0, tokens: ['--color-accent-100', '--color-accent-300', '--color-accent-500', '--color-accent-300'] },
        think:   { lump: .12, spd: 1.8, sx: 1.02, sy: .98,  dy: 0,  bounce: 0,  lean: .12, nudge: 0, tokens: ['--status-ai-bg', '--status-ai', '--status-ai-text', '--status-ai'] },
        concern: { lump: .05, spd: .35, sx: 1.08, sy: .9,   dy: 12, bounce: 0,  lean: 0,   nudge: 0, tokens: ['--color-neutral-200', '--color-neutral-500', '--color-neutral-700', '--color-neutral-500'] },
        alert:   { lump: .08, spd: .9,  sx: 1,    sy: 1,    dy: 0,  bounce: 0,  lean: 0,   nudge: 1, tokens: ['--status-attention-bg', '--status-attention', '--status-attention-text', '--status-attention'] },
    };
    const NUMERIC = ['lump', 'spd', 'sx', 'sy', 'dy', 'bounce', 'lean', 'nudge'];
    // Rings: [radius × body, flatness, waves, opacity, phase]
    const RINGS = [[1.55, .24, 7, .9, 0], [1.85, .24, 9, .55, 1.7], [2.15, .24, 5, .3, 3.1]];

    // Resolve any CSS colour (token value) to [r, g, b] through a 1×1 canvas.
    const probe = document.createElement('canvas').getContext('2d');
    const toRgb = (value) => {
        probe.fillStyle = 'black';
        probe.fillStyle = value.trim() || 'black';
        probe.clearRect(0, 0, 1, 1);
        probe.fillRect(0, 0, 1, 1);
        const d = probe.getImageData(0, 0, 1, 1).data;
        return [d[0], d[1], d[2]];
    };
    const palette = {};
    const readPalette = () => {
        const style = getComputedStyle(root);
        Object.entries(MOODS).forEach(([mood, def]) => {
            palette[mood] = def.tokens.map(token => toRgb(style.getPropertyValue(token)));
        });
        palette.highlight = toRgb(style.getPropertyValue('--color-amira-highlight'));
    };
    const rgba = (c, a) => `rgba(${c[0] | 0}, ${c[1] | 0}, ${c[2] | 0}, ${a})`;
    const lerp = (a, b, k) => a + (b - a) * k;
    const lumpAt = (a, t, seed) => Math.sin(a * 2 + t * 1.1 + seed) * .5 + Math.sin(a * 3 - t * .7 + seed * 2) * .35 + Math.sin(a * 5 + t * 1.9) * .15;

    const bubbles = [];

    function setup(el) {
        if (el.amiraBubble) return;
        const canvas = el.querySelector('canvas') || el.appendChild(document.createElement('canvas'));
        const mood = MOODS[el.dataset.mood] ? el.dataset.mood : 'calm';
        const state = { el, canvas, g: canvas.getContext('2d'), w: 0, h: 0, voice: 0, last: 0, cur: {}, col: palette[mood].map(c => c.slice()) };
        NUMERIC.forEach(key => { state.cur[key] = MOODS[mood][key]; });
        el.amiraBubble = state;
        el.classList.add('is-live');
        bubbles.push(state);
        resize(state);
        if (window.ResizeObserver) new ResizeObserver(() => { resize(state); if (reducedMotion()) draw(state, 0, 1); }).observe(el);
        new MutationObserver(() => { if (reducedMotion()) draw(state, 0, 1); }).observe(el, { attributes: true, attributeFilter: ['data-mood', 'data-speaking'] });
    }

    function resize(s) {
        const box = s.el.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        s.w = Math.max(1, box.width);
        s.h = Math.max(1, box.height);
        s.canvas.width = Math.round(s.w * ratio);
        s.canvas.height = Math.round(s.h * ratio);
        s.g.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function rings(s, front, t, R, cx, cy) {
        const g = s.g;
        g.lineCap = 'round';
        RINGS.forEach((r, i) => {
            const rx = R * r[0];
            let prev = null;
            g.lineWidth = Math.max(.75, (1.7 - i * .3) * R / 40);
            for (let k = 0; k <= 200; k++) {
                const a = k / 200 * Math.PI * 2;
                const near = Math.sin(a) > 0;
                const env = Math.pow(Math.abs(Math.cos(a)), .6);
                const quiet = (1.5 + i * .3) * (R / 40) * Math.sin(a * r[2] - t * .8 + r[4]);
                const loud = (2 + 18 * (1 - i * .2)) * (R / 40) * env * Math.sin(a * r[2] * 2 - t * (6 + i * 2) + r[4]) * (.6 + .4 * Math.sin(t * 13 + i));
                const wave = lerp(quiet, loud, s.voice);
                const x = cx + rx * Math.cos(a);
                const y = cy + rx * r[1] * Math.sin(a) + wave;
                if (prev && near === front) {
                    g.beginPath();
                    g.moveTo(prev[0], prev[1]);
                    g.lineTo(x, y);
                    g.strokeStyle = rgba(s.col[3], front ? r[3] : r[3] * .4);
                    g.stroke();
                }
                prev = [x, y];
            }
        });
    }

    function draw(s, t, k) {
        const mood = MOODS[s.el.dataset.mood] ? s.el.dataset.mood : 'calm';
        const target = MOODS[mood];
        NUMERIC.forEach(key => { s.cur[key] = lerp(s.cur[key], target[key], k); });
        s.col.forEach((c, i) => { for (let j = 0; j < 3; j++) c[j] = lerp(c[j], palette[mood][i][j], k); });
        const speaking = s.el.dataset.speaking === 'true';
        s.voice = lerp(s.voice, speaking ? 1 : 0, Math.min(1, k * 1.7));

        const g = s.g, c = s.cur;
        const R = Math.min(s.w, s.h) * .2;
        const scale = R / 82; // reference motion was tuned for an 82px body
        const cx = s.w / 2, cy = s.h / 2 + c.dy * scale;
        g.clearRect(0, 0, s.w, s.h);

        rings(s, false, t, R, cx, cy);

        const bounce = c.bounce * scale * Math.abs(Math.sin(t * 3.2));
        const phase = t % 3;
        const nudge = c.nudge * (phase < .35 ? Math.sin(phase / .35 * Math.PI * 3) * 8 * scale : 0);
        const talk = 1 + .05 * Math.sin(t * 18) * s.voice;
        const breathe = 1 + .03 * Math.sin(t * 1.6 * Math.max(.5, c.spd));

        g.save();
        g.translate(cx + nudge, cy - bounce);
        g.rotate(c.lean * Math.sin(t * .9));
        g.beginPath();
        for (let i = 0; i <= 120; i++) {
            const a = i / 120 * Math.PI * 2;
            const rr = R * (1 + c.lump * lumpAt(a, t * c.spd * 2, .7)) * breathe * talk;
            let y = rr * Math.sin(a) * c.sy;
            if (y > 0 && c.sy < .95) y *= .9; // concerned: settles onto a flatter base
            const x = rr * Math.cos(a) * c.sx;
            if (i === 0) g.moveTo(x, y); else g.lineTo(x, y);
        }
        g.closePath();
        const fill = g.createRadialGradient(-R * .35, -R * .4, R * .05, 0, 0, R * 1.15);
        fill.addColorStop(0, rgba(palette.highlight, .9));
        fill.addColorStop(.18, rgba(s.col[0], .75));
        fill.addColorStop(.6, rgba(s.col[1], .6));
        fill.addColorStop(1, rgba(s.col[2], .85));
        g.shadowColor = rgba(s.col[1], .55);
        g.shadowBlur = R * .5;
        g.fillStyle = fill;
        g.fill();
        g.shadowBlur = 0;
        g.lineWidth = Math.max(.75, R / 55);
        g.strokeStyle = rgba(palette.highlight, .25);
        g.stroke();
        g.beginPath();
        g.ellipse(R * .3, R * .38, R * .22, R * .1, -.5, 0, Math.PI * 2);
        g.fillStyle = rgba(palette.highlight, .12);
        g.fill();
        g.restore();

        rings(s, true, t, R, cx, cy);
    }

    let running = false;
    function frame(ms) {
        if (!running) return;
        const t = ms / 1000;
        bubbles.forEach(s => {
            const dt = Math.min(.05, (ms - s.last) / 1000 || 0);
            s.last = ms;
            draw(s, t, Math.min(1, dt * 3));
        });
        requestAnimationFrame(frame);
    }
    function start() {
        if (reducedMotion()) {
            running = false;
            bubbles.forEach(s => draw(s, 0, 1)); // one still, settled frame
            return;
        }
        if (running || document.hidden) return;
        running = true;
        requestAnimationFrame(frame);
    }
    function stop() { running = false; }

    function init() {
        readPalette();
        document.querySelectorAll('[data-amira-bubble]').forEach(setup);
        start();
    }

    window.AmiraBubble = {
        setMood(el, mood) { if (MOODS[mood]) el.dataset.mood = mood; },
        setSpeaking(el, on) { el.dataset.speaking = on ? 'true' : 'false'; },
        refresh() { document.querySelectorAll('[data-amira-bubble]').forEach(setup); start(); },
    };

    // Re-read token colours when the theme changes; respect motion changes live.
    new MutationObserver(() => { readPalette(); stop(); start(); }).observe(root, { attributes: true, attributeFilter: ['data-theme', 'data-motion'] });
    if (motionQuery && motionQuery.addEventListener) motionQuery.addEventListener('change', () => { stop(); start(); });
    if (window.matchMedia) {
        const scheme = window.matchMedia('(prefers-color-scheme: dark)');
        if (scheme.addEventListener) scheme.addEventListener('change', () => { readPalette(); if (reducedMotion()) start(); });
    }
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); else start(); });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
