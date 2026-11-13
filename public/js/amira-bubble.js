/*
 * AMIRA avatar (ADR-365): a fluid body, soft mist, an orbiting voice wave and
 * small bubbles that bud, drift and pop. Animates every [data-amira-bubble] from its
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
        calm:    { lump: .07, spd: .6,  sx: 1,    sy: 1,    dy: 0,  bounce: 0,  lean: 0,   nudge: 0, tokens: ['--color-amira-body-light', '--color-amira-body-mid', '--color-accent-600', '--color-accent-400'] },
        happy:   { lump: .09, spd: 1.4, sx: .95,  sy: 1.08, dy: -8, bounce: 10, lean: 0,   nudge: 0, tokens: ['--color-amira-body-light', '--color-amira-body-mid', '--color-accent-500', '--color-accent-300'] },
        think:   { lump: .12, spd: 1.8, sx: 1.02, sy: .98,  dy: 0,  bounce: 0,  lean: .12, nudge: 0, tokens: ['--color-amira-ai-light', '--status-ai', '--color-amira-ai-deep', '--status-ai'] },
        concern: { lump: .05, spd: .35, sx: 1.08, sy: .9,   dy: 12, bounce: 0,  lean: 0,   nudge: 0, tokens: ['--color-amira-concern-light', '--color-neutral-500', '--color-amira-concern-deep', '--color-neutral-500'] },
        alert:   { lump: .08, spd: .9,  sx: 1,    sy: 1,    dy: 0,  bounce: 0,  lean: 0,   nudge: 1, tokens: ['--color-amira-alert-light', '--status-attention', '--color-amira-alert-deep', '--status-attention'] },
    };
    const NUMERIC = ['lump', 'spd', 'sx', 'sy', 'dy', 'bounce', 'lean', 'nudge'];

    // Resolve any CSS colour (token value) to [r, g, b] through a 1×1 canvas.
    const probe = document.createElement('canvas').getContext('2d');
    if (!probe) return; // Leave the CSS fallback visible if canvas is unavailable.
    const toRgb = (value) => {
        probe.fillStyle = 'black';
        probe.fillStyle = value.trim() || 'black';
        probe.clearRect(0, 0, 1, 1);
        probe.fillRect(0, 0, 1, 1);
        const d = probe.getImageData(0, 0, 1, 1).data;
        return [d[0], d[1], d[2]];
    };
    const palette = {};
    const timing = {};
    const readPalette = () => {
        const style = getComputedStyle(root);
        Object.entries(MOODS).forEach(([mood, def]) => {
            palette[mood] = def.tokens.map(token => toRgb(style.getPropertyValue(token)));
        });
        palette.highlight = toRgb(style.getPropertyValue('--color-amira-highlight'));
        palette.glow = toRgb(style.getPropertyValue('--color-amira-poke-glow'));
        const defaults = { 'poke-overload': .75, 'poke-pop': .3, 'poke-gone': .9, 'poke-return': .45, 'poke-settle': .45 };
        ['release', 'satellite', 'response', 'poke-overload', 'poke-pop', 'poke-gone', 'poke-return', 'poke-settle'].forEach(name => {
            const value = style.getPropertyValue(`--duration-amira-${name}`).trim();
            const seconds = parseFloat(value) / (value.endsWith('ms') ? 1000 : 1);
            timing[name] = Number.isFinite(seconds) ? seconds : (defaults[name] || 1);
        });
    };
    const rgba = (c, a) => `rgba(${c[0] | 0}, ${c[1] | 0}, ${c[2] | 0}, ${a})`;
    const lerp = (a, b, k) => a + (b - a) * k;
    const lumpAt = (a, t, seed) => Math.sin(a * 2 + t * 1.1 + seed) * .5 + Math.sin(a * 3 - t * .7 + seed * 2) * .35 + Math.sin(a * 5 + t * 1.9) * .15;

    const bubbles = [];

    // Poking (owner, 2026-11-06): each poke wobbles AMIRA away from the finger and disperses bubbles.
    // POKE_LIMIT pokes within POKE_WINDOW seconds overload her: she glows, her rings startle and she shrinks,
    // then she pops, vanishes and comes back. Reduced motion ignores pokes.
    const POKE_LIMIT = 6;
    const POKE_WINDOW = 3;
    const freshPoke = () => ({ phase: 'normal', t: 0, wobble: 0, angle: 0, glow: 0, startle: 0, scale: 1, effects: 1, burst: 0, shake: 0, strikes: [] });
    const easeOutBack = x => 1 + 2.2 * Math.pow(x - 1, 3) + 1.2 * Math.pow(x - 1, 2);

    function poke(s, x, y) {
        const p = s.poke;
        if (reducedMotion() || p.phase !== 'normal') return;
        const box = s.el.getBoundingClientRect();
        p.angle = Math.atan2(y - (box.top + box.height / 2), x - (box.left + box.width / 2));
        p.wobble = 1;
        for (let i = 0; i < 4; i++) {
            const life = timing.satellite * (.45 + Math.random() * .25);
            s.satellites.push({ angle: p.angle + Math.PI + (Math.random() - .5) * 2.2, age: life * .22, life,
                size: .035 + Math.random() * .04, drift: .7 + Math.random() * .35, sway: Math.random() * Math.PI * 2 });
        }
        s.satellites = s.satellites.slice(-14);
        p.strikes = p.strikes.filter(at => s.time - at < POKE_WINDOW).concat(s.time);
        if (p.strikes.length >= POKE_LIMIT) { p.phase = 'overload'; p.t = 0; }
        start();
    }

    // Hover trail (owner, 2026-11-06): a mouse moving over her body leaves foam inside it, drifting back and
    // fading like the white water behind a surfer. Positions are in body radii from the avatar centre.
    const TRAIL_MAX = 40;
    function hover(s, x, y) {
        if (reducedMotion() || s.poke.scale < .5) return;
        const box = s.el.getBoundingClientRect();
        const R0 = Math.min(box.width, box.height) * .285;
        const nx = (x - (box.left + box.width / 2)) / R0;
        const ny = (y - (box.top + box.height / 2)) / R0;
        if (Math.hypot(nx, ny) > .95 * Math.min(s.poke.scale, 1.2)) { s.trailLast = null; s.pull.target = 0; return; }
        // Dragging: her outline leans towards the cursor, more the further it is from her centre.
        s.pull.angle = Math.atan2(ny, nx);
        s.pull.target = Math.min(1, Math.hypot(nx, ny) / .6);
        s.pull.seen = s.time;
        start();
        const last = s.trailLast;
        if (last && Math.hypot(nx - last.x, ny - last.y) < .12) return;
        const length = last ? Math.hypot(nx - last.x, ny - last.y) : 0;
        const dx = length ? (nx - last.x) / length : 0;
        const dy = length ? (ny - last.y) / length : 0;
        for (let i = 0; i < 2; i++) {
            s.trail.push({ x: nx + (Math.random() - .5) * .06, y: ny + (Math.random() - .5) * .06, age: 0,
                life: .7 + Math.random() * .4, size: .035 + Math.random() * .04,
                vx: -dx * .35 + (Math.random() - .5) * .08, vy: -dy * .35 + (Math.random() - .5) * .08 });
        }
        s.trail = s.trail.slice(-TRAIL_MAX);
        s.trailLast = { x: nx, y: ny };
        start();
    }

    function updatePull(s, dt) {
        const pull = s.pull;
        if (s.time - pull.seen > .25) pull.target = 0; // the mouse stopped: relax
        pull.amount += (pull.target - pull.amount) * Math.min(1, dt * 8);
        if (pull.amount < .001) pull.amount = 0;
    }

    function updateTrail(s, dt) {
        s.trail.forEach(foam => {
            foam.age += dt;
            foam.x += foam.vx * dt;
            foam.y += foam.vy * dt;
            foam.vx *= Math.exp(-dt * 2);
            foam.vy *= Math.exp(-dt * 2);
        });
        s.trail = s.trail.filter(foam => foam.age < foam.life);
    }

    // Drawn inside the body's clip: a faint wake line through the foam, then the foam bubbles themselves.
    function foam(s, R, ox, oy) {
        if (!s.trail.length) return;
        const g = s.g;
        g.beginPath();
        s.trail.forEach((bubble, i) => {
            const x = bubble.x * R + ox, y = bubble.y * R + oy;
            if (i === 0) g.moveTo(x, y); else g.lineTo(x, y);
        });
        g.strokeStyle = rgba(palette.highlight, .18);
        g.lineWidth = Math.max(.6, R * .03);
        g.lineCap = 'round';
        g.lineJoin = 'round';
        g.stroke();
        s.trail.forEach(bubble => {
            const progress = bubble.age / bubble.life;
            const fade = 1 - progress;
            const radius = R * bubble.size * (progress < .15 ? progress / .15 : 1 - progress * .4);
            if (radius < .1) return;
            const x = bubble.x * R + ox, y = bubble.y * R + oy;
            g.beginPath();
            g.arc(x, y, radius, 0, Math.PI * 2);
            g.fillStyle = rgba(palette.highlight, .28 * fade);
            g.fill();
            g.strokeStyle = rgba(palette.highlight, .6 * fade);
            g.lineWidth = Math.max(.5, R * .012);
            g.stroke();
        });
    }

    function updatePoke(s, dt) {
        const p = s.poke;
        p.wobble *= Math.exp(-dt * 3);
        if (p.wobble < .001) p.wobble = 0;
        if (p.phase === 'normal') return;
        p.t += dt;
        if (p.phase === 'overload') {
            // Shrinks a little, then inflates past her size until she bursts.
            const k = Math.min(1, p.t / timing['poke-overload']);
            p.glow = Math.min(1, p.t / .25);
            p.startle = p.glow;
            // Out of control: a fast spinning dent plus jelly-like lumps that grow until she bursts.
            p.shake = Math.min(1, .6 + .4 * k);
            p.wobble = Math.max(p.wobble, .35);
            p.angle += dt * 5;
            p.scale = k < .3 ? 1 - .15 * (k / .3) : .85 + .7 * Math.pow((k - .3) / .7, 2);
            if (k >= 1) {
                p.phase = 'popping'; p.t = 0; p.scale = 0; p.effects = 0; p.burst = 1; p.shake = 0;
                // The burst: a cloud of bubbles flies out from where her surface was, then pops one by one.
                s.satellites = [];
                for (let i = 0; i < 28; i++) {
                    const life = .9 + Math.random() * .7;
                    s.satellites.push({ angle: i / 28 * Math.PI * 2 + Math.random() * .25, age: life * .2, life, from: 1.55,
                        size: .035 + Math.random() * .06, drift: .5 + Math.random() * 1.1, sway: Math.random() * Math.PI * 2 });
                }
            }
        } else if (p.phase === 'popping') {
            const k = Math.min(1, p.t / timing['poke-pop']);
            p.burst = 1 - k;
            if (k >= 1) { p.phase = 'gone'; p.t = 0; p.glow = 0; p.startle = 0; p.burst = 0; }
        } else if (p.phase === 'gone') {
            if (p.t >= timing['poke-gone']) { p.phase = 'returning'; p.t = 0; }
        } else if (p.phase === 'returning') {
            // The blob comes back first, without her voice wave or mist.
            const k = Math.min(1, p.t / timing['poke-return']);
            p.scale = easeOutBack(k);
            p.effects = 0;
            if (k >= 1) { p.phase = 'settling'; p.t = 0; p.scale = 1; }
        } else if (p.phase === 'settling') {
            // Then the wave and the glowing mist fade back in.
            const k = Math.min(1, p.t / timing['poke-settle']);
            p.effects = k;
            if (k >= 1) Object.assign(p, freshPoke());
        }
    }

    function setup(el) {
        if (el.amiraBubble) return;
        const canvas = el.querySelector('canvas') || el.appendChild(document.createElement('canvas'));
        const mood = MOODS[el.dataset.mood] ? el.dataset.mood : 'calm';
        const g = canvas.getContext('2d');
        if (!g) return;
        const state = { el, canvas, g, w: 0, h: 0, voice: 0, last: 0, time: 0, visible: true, satellites: [], release: timing.release, cur: {}, col: palette[mood].map(c => c.slice()), poke: freshPoke(), trail: [], trailLast: null, pull: { angle: 0, amount: 0, target: 0, seen: 0 } };
        NUMERIC.forEach(key => { state.cur[key] = MOODS[mood][key]; });
        el.amiraBubble = state;
        el.classList.add('is-live');
        // The phone bar's small avatar sits inside a button that opens AMIRA, so only the panel avatar is pokeable.
        if (!el.closest?.('button') && el.addEventListener) {
            el.addEventListener('pointerdown', event => { if (event.button === 0) poke(state, event.clientX, event.clientY); });
            el.addEventListener('pointermove', event => { if (event.pointerType === 'mouse') hover(state, event.clientX, event.clientY); });
            el.addEventListener('pointerleave', () => { state.trailLast = null; state.pull.target = 0; });
        }
        bubbles.push(state);
        resize(state);
        if (window.ResizeObserver) new ResizeObserver(() => { resize(state); if (reducedMotion()) draw(state, 0, 1); }).observe(el);
        new MutationObserver(() => { if (reducedMotion()) draw(state, 0, 1); }).observe(el, { attributes: true, attributeFilter: ['data-mood', 'data-speaking'] });
        if (window.IntersectionObserver) {
            new IntersectionObserver(entries => {
                state.visible = entries[0].isIntersecting;
                state.last = 0;
                if (bubbles.some(bubble => bubble.visible)) start(); else stop();
            }).observe(el);
        }
    }

    function resize(s) {
        // The canvas may bleed past the avatar (CSS) so the burst, glow and voice ring are not clipped to a box.
        // Her body is sized from the avatar (s.size); drawing is centred on the canvas, which shares that centre.
        const box = s.el.getBoundingClientRect();
        const area = s.canvas.getBoundingClientRect ? s.canvas.getBoundingClientRect() : box;
        const ratio = Math.min(2, window.devicePixelRatio || 1);
        s.size = Math.max(1, Math.min(box.width, box.height));
        s.w = Math.max(1, area.width || box.width);
        s.h = Math.max(1, area.height || box.height);
        s.canvas.width = Math.round(s.w * ratio);
        s.canvas.height = Math.round(s.h * ratio);
        s.g.setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function orbit(s, front, t, R, cx, cy) {
        const g = s.g;
        g.save();
        g.translate(cx, cy);
        g.rotate(-.22);
        g.lineCap = 'round';
        g.lineWidth = Math.max(.7, R * .022);
        g.strokeStyle = rgba(front ? palette.highlight : s.col[3], front ? .72 : .3);
        g.beginPath();
        let drawing = false;
        for (let i = 0; i <= 160; i++) {
            const a = i / 160 * Math.PI * 2;
            const near = Math.sin(a) >= 0;
            const jitter = s.poke.startle * Math.sin(a * 9 + t * 34) * R * .07;
            const wave = Math.sin(a * 7 - t * (1.2 + s.voice * 4)) * R * (.025 + s.voice * .1) + jitter;
            const x = R * 1.48 * Math.cos(a);
            const y = R * .38 * Math.sin(a) + wave;
            if (near !== front) { drawing = false; continue; }
            if (drawing) g.lineTo(x, y); else g.moveTo(x, y);
            drawing = true;
        }
        g.stroke();
        g.restore();

    }

    function mist(s, t, R, cx, cy) {
        const g = s.g;
        for (let i = 0; i < 3; i++) {
            const x = cx + Math.cos(t * .24 + i * 2.1) * R * .42;
            const y = cy + Math.sin(t * .3 + i * 1.8) * R * .48;
            const radius = R * (1.3 + i * .12);
            const haze = g.createRadialGradient(x, y, R * .15, x, y, radius);
            haze.addColorStop(0, rgba(s.col[1], .13));
            haze.addColorStop(.45, rgba(s.col[1], .065));
            haze.addColorStop(1, rgba(s.col[1], 0));
            g.fillStyle = haze;
            g.fillRect(0, 0, s.w, s.h);
        }
    }

    function bodyPoint(s, a, t, R) {
        const c = s.cur;
        const breathe = 1 + .025 * Math.sin(t * 1.3 * Math.max(.5, c.spd));
        const dent = s.poke.wobble * .22 * Math.max(0, Math.cos(a - s.poke.angle)) * (.6 + .4 * Math.sin(t * 22));
        // A bubble wobbles in its lowest modes: a fast squash-and-stretch oval plus a slight three-lobe sway.
        const jelly = 1 + s.poke.shake * (.11 * Math.cos(2 * a + t * 2.5) * Math.sin(t * 22) + .035 * Math.sin(3 * a - t * 7));
        const toward = Math.max(0, Math.cos(a - s.pull.angle));
        const drag = 1 + s.pull.amount * .1 * toward * toward;
        const radius = R * (1 + (c.lump + .09) * lumpAt(a, t * c.spd, .7)) * breathe * (1 - dent) * jelly * drag;
        return [radius * Math.cos(a) * c.sx, radius * Math.sin(a) * c.sy];
    }

    function releaseBubbles(s, dt) {
        if (reducedMotion() || s.size < 80 || (s.el.dataset.emergence && s.el.dataset.emergence !== 'ready')) { s.satellites = []; return; }
        s.satellites.forEach(bubble => { bubble.age += dt; });
        s.satellites = s.satellites.filter(bubble => bubble.age < bubble.life);
        s.release -= dt;
        if (s.release > 0 || s.satellites.length >= 6) return;
        s.release = timing.release * (.65 + Math.random() * .9);
        s.satellites.push({
            angle: Math.random() * Math.PI * 2, age: 0,
            life: timing.satellite * (.8 + Math.random() * .5),
            size: .045 + Math.random() * .045, drift: .38 + Math.random() * .22,
            sway: Math.random() * Math.PI * 2,
        });
    }

    // While overloaded or popped, existing bubbles keep drifting and popping but no new ones bud.
    function ageBubbles(s, dt) {
        s.satellites.forEach(bubble => { bubble.age += dt; });
        s.satellites = s.satellites.filter(bubble => bubble.age < bubble.life);
    }

    function satellites(s, t, R) {
        const g = s.g;
        s.satellites.forEach(bubble => {
            const progress = bubble.age / bubble.life;
            const bud = Math.min(1, progress / .2);
            const travel = Math.max(0, (progress - .2) / .8);
            const point = bubble.from ? [Math.cos(bubble.angle) * R * bubble.from, Math.sin(bubble.angle) * R * bubble.from] : bodyPoint(s, bubble.angle, t, R);
            const dx = Math.cos(bubble.angle), dy = Math.sin(bubble.angle);
            const distance = R * bubble.drift * travel;
            const sway = Math.sin(travel * 3 + bubble.sway) * R * .035 * travel;
            const x = point[0] + dx * distance - dy * sway;
            const y = point[1] + dy * distance + dx * sway;
            const radius = R * bubble.size * Math.sin(bud * Math.PI / 2);
            if (radius < .1) return;
            const pop = Math.max(0, (progress - .84) / .16);
            g.lineWidth = Math.max(.5, R * .014);
            if (pop > 0) {
                g.strokeStyle = rgba(s.col[0], (1 - pop) * .65);
                g.beginPath();
                g.arc(x, y, radius * (1 + pop * 1.5), bubble.sway + pop, bubble.sway + pop + Math.PI * 1.45);
                g.stroke();
                for (let i = 0; i < 3; i++) {
                    const a = bubble.sway + i * Math.PI * 2 / 3;
                    g.beginPath();
                    g.arc(x + Math.cos(a) * radius * (1 + pop * 3), y + Math.sin(a) * radius * (1 + pop * 3), Math.max(.15, radius * .18 * (1 - pop)), 0, Math.PI * 2);
                    g.fillStyle = rgba(s.col[0], (1 - pop) * .5);
                    g.fill();
                }
                return;
            }
            // The small neck grows out of the current surface before detaching.
            if (progress < .2) {
                g.beginPath();
                g.moveTo(point[0] - dx * radius, point[1] - dy * radius);
                g.lineTo(x, y);
                g.lineWidth = radius * (1 - bud) * 1.4;
                g.strokeStyle = rgba(s.col[1], .65);
                g.stroke();
            }
            const fill = g.createRadialGradient(x - radius * .3, y - radius * .4, 0, x, y, radius);
            fill.addColorStop(0, rgba(palette.highlight, .65));
            fill.addColorStop(.4, rgba(s.col[1], .25));
            fill.addColorStop(1, rgba(s.col[0], .55));
            g.beginPath();
            g.arc(x, y, radius, 0, Math.PI * 2);
            g.fillStyle = fill;
            g.fill();
            g.strokeStyle = rgba(s.col[0], .65);
            g.lineWidth = Math.max(.5, R * .012);
            g.stroke();
        });
    }

    function draw(s, t, k) {
        const mood = MOODS[s.el.dataset.mood] ? s.el.dataset.mood : 'calm';
        const target = MOODS[mood];
        NUMERIC.forEach(key => { s.cur[key] = lerp(s.cur[key], target[key], k); });
        s.col.forEach((c, i) => { for (let j = 0; j < 3; j++) c[j] = lerp(c[j], palette[mood][i][j], k); });
        const speaking = !reducedMotion() && s.el.dataset.speaking === 'true';
        s.voice = lerp(s.voice, speaking ? 1 : 0, Math.min(1, k * 1.7));

        const g = s.g, c = s.cur, p = s.poke;
        const R0 = s.size * .285;
        const R = R0 * Math.max(p.scale, .001);
        // Overload glow: blend toward the poke-glow token for this frame only.
        const base = s.col;
        if (p.glow > 0) s.col = base.map(colour => colour.map((v, j) => lerp(v, palette.glow[j], p.glow * .85)));
        const scale = R0 / 82; // reference motion was tuned for an 82px body
        const cx = s.w / 2, cy = s.h / 2 + c.dy * scale;
        g.clearRect(0, 0, s.w, s.h);

        const effectsTarget = s.el.dataset.emergence === 'body' ? 0 : 1;
        s.effects = s.effects === undefined || reducedMotion() ? effectsTarget : lerp(s.effects, effectsTarget, k);
        g.save();
        const ringR = R0 * Math.min(Math.max(p.scale, .001), 1.1);
        g.globalAlpha = s.effects * p.effects;
        mist(s, t, ringR, cx, cy);
        orbit(s, false, t, ringR, cx, cy);
        g.restore();

        const bounce = c.bounce * scale * Math.abs(Math.sin(t * 3.2));
        const phase = t % 3;
        const nudge = c.nudge * (phase < .35 ? Math.sin(phase / .35 * Math.PI * 3) * 8 * scale : 0);

        const push = p.wobble * R0 * .12;
        const quake = p.shake * R0 * .025;
        g.save();
        const tx = cx + nudge - Math.cos(p.angle) * push + Math.sin(t * 61) * quake;
        const ty = cy - bounce - Math.sin(p.angle) * push + Math.cos(t * 53) * quake;
        g.translate(tx, ty);
        g.rotate(c.lean * Math.sin(t * .9));
        if (p.scale <= .001) {
            if (p.burst > 0) {
                g.beginPath();
                g.arc(0, 0, R0 * 1.55 * (1 + (1 - p.burst) * .6), 0, Math.PI * 2);
                g.strokeStyle = rgba(palette.glow, p.burst * .8);
                g.lineWidth = Math.max(1, R0 * .05 * p.burst);
                g.stroke();
            }
            satellites(s, t, R0);
            g.restore();
            s.col = base;
            return;
        }
        g.beginPath();
        for (let i = 0; i <= 120; i++) {
            const a = i / 120 * Math.PI * 2;
            const [x, y] = bodyPoint(s, a, t, R);
            if (i === 0) g.moveTo(x, y); else g.lineTo(x, y);
        }
        g.closePath();
        const fill = g.createRadialGradient(-R * .35, -R * .4, R * .05, 0, 0, R * 1.15);
        fill.addColorStop(0, rgba(s.col[0], .95));
        fill.addColorStop(.28, rgba(s.col[1], .88));
        fill.addColorStop(.72, rgba(s.col[2], .78));
        fill.addColorStop(1, rgba(s.col[0], .8));
        g.shadowColor = p.glow > 0 ? rgba(palette.glow, .55 + .35 * p.glow) : rgba(s.col[1], .3);
        g.shadowBlur = R * (.22 + p.glow * .6);
        g.fillStyle = fill;
        g.fill();
        g.shadowBlur = 0;
        g.lineWidth = Math.max(.75, R / 55);
        g.strokeStyle = rgba(s.col[0], .7);
        g.stroke();
        g.save();
        g.clip();
        // Broad internal light and a fine curved reflection keep the body fluid.
        const light = g.createRadialGradient(R * .35, R * .55, 0, R * .2, R * .4, R * .95);
        light.addColorStop(0, rgba(s.col[0], .6));
        light.addColorStop(.5, rgba(s.col[1], .12));
        light.addColorStop(1, rgba(s.col[1], 0));
        g.fillStyle = light;
        g.fillRect(-R * 1.4, -R * 1.4, R * 2.8, R * 2.8);
        g.beginPath();
        g.moveTo(-R * .7, -R * .3);
        g.bezierCurveTo(-R * .6, -R * .9, -R * .15, -R * .95, R * .2, -R * .7);
        g.strokeStyle = rgba(palette.highlight, .32);
        g.lineWidth = Math.max(.65, R * .025);
        g.stroke();
        foam(s, R0, s.w / 2 - tx, s.h / 2 - ty);
        g.restore();
        satellites(s, t, R);
        g.restore();

        g.save();
        g.globalAlpha = s.effects * p.effects;
        orbit(s, true, t, ringR, cx, cy);
        g.restore();
        s.col = base;
    }

    let running = false;
    let frameId = 0;
    function frame(ms) {
        if (!running) return;
        bubbles.forEach(s => {
            if (!s.visible || !s.el.isConnected) return;
            const dt = Math.min(.05, (ms - s.last) / 1000 || 0);
            s.last = ms;
            s.time += dt;
            updatePoke(s, dt);
            updateTrail(s, dt);
            updatePull(s, dt);
            if (s.poke.phase === 'normal') releaseBubbles(s, dt); else ageBubbles(s, dt);
            draw(s, s.time, Math.min(1, dt * 3));
        });
        frameId = requestAnimationFrame(frame);
    }
    function start() {
        if (reducedMotion()) {
            stop();
            bubbles.forEach(s => { s.satellites = []; s.voice = 0; draw(s, 0, 1); }); // one still, settled frame
            return;
        }
        if (running || document.hidden || !bubbles.some(s => s.visible)) return;
        running = true;
        bubbles.forEach(s => { s.last = 0; });
        frameId = requestAnimationFrame(frame);
    }
    function stop() { running = false; cancelAnimationFrame(frameId); }

    function init() {
        readPalette();
        document.querySelectorAll('[data-amira-bubble]').forEach(setup);
        start();
    }

    window.AmiraBubble = {
        setMood(el, mood) { if (MOODS[mood]) el.dataset.mood = mood; },
        setSpeaking(el, on) { el.dataset.speaking = on ? 'true' : 'false'; },
        respond(el) {
            clearTimeout(el.amiraResponseTimer);
            if (reducedMotion()) return;
            el.dataset.speaking = 'true';
            el.amiraResponseTimer = setTimeout(() => { el.dataset.speaking = 'false'; }, timing.response * 1000);
        },
        refresh() { document.querySelectorAll('[data-amira-bubble]').forEach(setup); start(); },
        poke(el, x, y) { if (el.amiraBubble) poke(el.amiraBubble, x, y); },
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
