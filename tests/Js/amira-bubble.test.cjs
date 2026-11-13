const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(resolve(__dirname, '../../public/js/amira-bubble.js'), 'utf8');

function harness({ width = 224, canvasAvailable = true } = {}) {
    const frames = new Map(), timers = new Map(), mutations = new Map(), events = {};
    let id = 0, clock = 0, intersect;
    const gradient = () => ({ addColorStop() {} });
    const context = new Proxy({
        createRadialGradient: gradient, createLinearGradient: gradient,
        getImageData: () => ({ data: [80, 180, 170, 255] }),
    }, { get: (target, key) => key in target ? target[key] : () => {} });
    const canvas = { getContext: () => canvasAvailable ? context : null };
    const root = { dataset: {} };
    const classes = new Set();
    const el = {
        dataset: { mood: 'calm', speaking: 'false' }, isConnected: true,
        querySelector: () => canvas, getBoundingClientRect: () => ({ width, height: width }),
        classList: { add: name => classes.add(name) },
    };
    const document = {
        documentElement: root, readyState: 'complete', hidden: false,
        createElement: () => canvas, querySelectorAll: () => [el],
        addEventListener: (name, callback) => { events[name] = callback; },
    };
    const media = { matches: false, addEventListener() {} };
    const window = { devicePixelRatio: 3, matchMedia: () => media };
    window.ResizeObserver = class { observe() {} };
    window.IntersectionObserver = class {
        constructor(callback) { intersect = callback; }
        observe() {}
    };
    const values = {
        '--duration-amira-release': '1100ms', '--duration-amira-satellite': '3200ms',
        '--duration-amira-response': '2200ms',
    };
    vm.runInNewContext(source, {
        window, document,
        getComputedStyle: () => ({ getPropertyValue: key => values[key] || 'teal' }),
        MutationObserver: class {
            constructor(callback) { this.callback = callback; }
            observe(target) { mutations.set(target, this.callback); }
        },
        ResizeObserver: window.ResizeObserver,
        IntersectionObserver: window.IntersectionObserver,
        requestAnimationFrame: callback => { frames.set(++id, callback); return id; },
        cancelAnimationFrame: key => frames.delete(key),
        setTimeout: (callback, delay) => { timers.set(++id, { callback, delay }); return id; },
        clearTimeout: key => timers.delete(key),
    });
    return {
        el, root, window, document, frames, timers, classes,
        step() {
            clock += 50;
            const pending = [...frames.values()];
            frames.clear();
            pending.forEach(callback => callback(clock));
        },
        change(target = root) { mutations.get(target)(); },
        visible(on) { intersect([{ isIntersecting: on }]); },
        hidden(on) { document.hidden = on; events.visibilitychange(); },
    };
}

test('satellites remain bounded, expire and keep releasing; compact avatars omit them', () => {
    const h = harness();
    const seen = new Set();
    for (let i = 0; i < 6000; i++) {
        h.step();
        assert.ok(h.el.amiraBubble.satellites.length <= 6);
        for (const bubble of h.el.amiraBubble.satellites) {
            assert.ok(bubble.age < bubble.life);
            seen.add(bubble);
        }
    }
    assert.ok(seen.size > 100, 'release continues after earlier bubbles expire');
    const compact = harness({ width: 40 });
    for (let i = 0; i < 200; i++) compact.step();
    assert.equal(compact.el.amiraBubble.satellites.length, 0);
    assert.equal(h.el.amiraBubble.canvas.width, 448, 'pixel density is capped');
});

test('reduced motion clears bubbles and freezes animation, while mood still updates', () => {
    const h = harness();
    for (let i = 0; i < 80; i++) h.step();
    assert.ok(h.el.amiraBubble.satellites.length);
    h.root.dataset.motion = 'reduced';
    h.change();
    assert.equal(h.frames.size, 0);
    assert.equal(h.el.amiraBubble.satellites.length, 0);
    assert.equal(h.el.amiraBubble.voice, 0);
    h.window.AmiraBubble.setMood(h.el, 'concern');
    h.change(h.el);
    assert.equal(h.el.amiraBubble.cur.sy, .9);
    const time = h.el.amiraBubble.time;
    h.step();
    assert.equal(h.el.amiraBubble.time, time);
    h.root.dataset.motion = 'full';
    for (let i = 0; i < 5; i++) h.change();
    assert.equal(h.frames.size, 1, 'theme/motion changes do not duplicate animation loops');
});

test('hidden and offscreen avatars pause and resume without accumulating elapsed time', () => {
    const h = harness();
    h.step();
    const time = h.el.amiraBubble.time;
    h.hidden(true);
    assert.equal(h.frames.size, 0);
    for (let i = 0; i < 200; i++) h.step();
    h.hidden(false);
    h.step();
    assert.ok(h.el.amiraBubble.time - time <= .05);
    h.visible(false);
    assert.equal(h.frames.size, 0);
    h.visible(true);
    assert.equal(h.frames.size, 1);
});

test('local response animation is finite, restartable and disabled with reduced motion', () => {
    const h = harness();
    h.window.AmiraBubble.respond(h.el);
    h.window.AmiraBubble.respond(h.el);
    assert.equal(h.timers.size, 1);
    assert.equal(h.el.dataset.speaking, 'true');
    const timer = [...h.timers.values()][0];
    assert.equal(timer.delay, 2200);
    timer.callback();
    assert.equal(h.el.dataset.speaking, 'false');
    h.root.dataset.motion = 'reduced';
    h.change();
    h.window.AmiraBubble.respond(h.el);
    assert.equal(h.timers.size, 0);
    assert.equal(h.el.dataset.speaking, 'false');
});

test('missing canvas preserves the static CSS fallback', () => {
    const h = harness({ canvasAvailable: false });
    assert.equal(h.classes.has('is-live'), false);
    assert.equal(h.frames.size, 0);
});
