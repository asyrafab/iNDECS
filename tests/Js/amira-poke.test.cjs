const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

// Poking AMIRA (owner, 2026-11-06): a poke wobbles her and disperses bubbles; too many pokes make her glow,
// startle, shrink and pop, then she reappears.
const source = readFileSync(resolve(__dirname, '../../public/js/amira-bubble.js'), 'utf8');

function harness({ reduced = false, bleed = 1 } = {}) {
    const frames = new Map(), listeners = {};
    let id = 0, clock = 0;
    const gradient = () => ({ addColorStop() {} });
    const context = new Proxy({ createRadialGradient: gradient, getImageData: () => ({ data: [80, 180, 170, 255] }) },
        { get: (target, key) => key in target ? target[key] : () => {} });
    // The canvas may be larger than the avatar (CSS bleed) so effects are not clipped to the avatar's box.
    const canvas = { getContext: () => context, getBoundingClientRect: () => ({ left: -112 * (bleed - 1), top: -112 * (bleed - 1), width: 224 * bleed, height: 224 * bleed }) };
    const root = { dataset: reduced ? { motion: 'reduced' } : {} };
    const el = {
        dataset: { mood: 'calm', speaking: 'false', emergence: 'ready' }, isConnected: true,
        querySelector: () => canvas, getBoundingClientRect: () => ({ left: 0, top: 0, width: 224, height: 224 }),
        classList: { add() {} },
        addEventListener: (name, callback) => { listeners[name] = callback; },
    };
    const values = {
        '--duration-amira-release': '1100ms', '--duration-amira-satellite': '3200ms', '--duration-amira-response': '2200ms',
        '--duration-amira-poke-overload': '1400ms', '--duration-amira-poke-pop': '450ms',
        '--duration-amira-poke-gone': '1200ms', '--duration-amira-poke-return': '700ms', '--duration-amira-poke-settle': '600ms',
        '--color-amira-poke-glow': '#f5c542',
    };
    const window = { devicePixelRatio: 1, matchMedia: () => ({ matches: false, addEventListener() {} }) };
    window.ResizeObserver = class { observe() {} };
    window.IntersectionObserver = class { observe() {} };
    vm.runInNewContext(source, {
        window, document: { documentElement: root, readyState: 'complete', hidden: false, createElement: () => canvas,
            querySelectorAll: () => [el], addEventListener() {} },
        getComputedStyle: () => ({ getPropertyValue: key => values[key] || 'teal' }),
        MutationObserver: class { observe() {} },
        ResizeObserver: window.ResizeObserver, IntersectionObserver: window.IntersectionObserver,
        requestAnimationFrame: callback => { frames.set(++id, callback); return id; },
        cancelAnimationFrame: key => frames.delete(key),
        setTimeout: () => 0, clearTimeout() {},
    });
    const h = {
        el, listeners, window,
        state: () => el.amiraBubble,
        step(ms = 50) {
            clock += ms;
            const pending = [...frames.values()];
            frames.clear();
            pending.forEach(callback => callback(clock));
        },
        steps(n, ms = 50) { for (let i = 0; i < n; i++) h.step(ms); },
        poke(x = 150, y = 112) { listeners.pointerdown({ clientX: x, clientY: y, button: 0 }); },
    };
    h.step();
    return h;
}

test('a poke wobbles AMIRA away from the finger and disperses bubbles', () => {
    const h = harness();
    const before = h.state().satellites.length;
    h.poke(200, 112);
    const poke = h.state().poke;
    assert.equal(poke.phase, 'normal');
    assert.ok(poke.wobble > .9, 'wobble starts strong');
    assert.ok(Math.abs(poke.angle) < .2, 'pushed from the right side');
    assert.ok(h.state().satellites.length >= before + 3, 'bubbles disperse');
    h.steps(60);
    assert.ok(h.state().poke.wobble < .05, 'wobble settles');
});

test('slow pokes never overload', () => {
    const h = harness();
    for (let i = 0; i < 10; i++) { h.poke(); h.steps(40); }
    assert.equal(h.state().poke.phase, 'normal');
});

test('too many pokes: shrink a little, inflate too big, burst into many bubbles, then return body first', () => {
    const h = harness();
    for (let i = 0; i < 5; i++) { h.poke(); h.step(); }
    h.poke();
    assert.equal(h.state().poke.phase, 'overload');
    h.steps(6);
    const p = () => h.state().poke;
    assert.ok(p().scale < .95 && p().scale > .8, 'shrinks just a little first');
    assert.ok(p().glow > .5 && p().startle > .5, 'glows yellow and the rings startle');
    assert.ok(p().shake > .5, 'the blob wobbles uncontrollably');
    h.steps(20);
    assert.ok(p().scale > 1.3, 'then inflates too big');
    h.steps(4);
    assert.equal(p().phase, 'popping');
    assert.equal(p().scale, 0, 'the body bursts');
    assert.ok(h.state().satellites.length >= 20, 'into a lot of bubbles');
    h.steps(10);
    assert.equal(p().phase, 'gone');
    h.poke();
    assert.equal(p().phase, 'gone', 'cannot be poked while gone');
    h.steps(25);
    assert.equal(p().phase, 'returning');
    h.steps(5);
    assert.ok(p().scale > .3, 'the blob comes back first');
    assert.equal(p().effects, 0, 'without the wave or mist yet');
    h.steps(10);
    assert.equal(p().phase, 'settling');
    assert.ok(p().effects > 0 && p().effects < 1, 'then the wave and glowing mist fade in');
    h.steps(10);
    assert.equal(p().phase, 'normal');
    assert.equal(p().scale, 1);
    assert.equal(p().effects, 1);
    assert.equal(p().shake, 0);
    assert.equal(p().glow, 0);
    assert.equal(p().strikes.length, 0);
});

test('reduced motion: pokes do nothing', () => {
    const h = harness({ reduced: true });
    for (let i = 0; i < 8; i++) h.poke();
    assert.equal(h.state().poke.phase, 'normal');
    assert.equal(h.state().poke.wobble, 0);
    assert.equal(h.state().satellites.length, 0);
});

// Hover trail (owner, 2026-11-06): moving the mouse over AMIRA leaves foam inside her, like a surfer's wake.
function hover(h, points) {
    points.forEach(([x, y]) => { h.listeners.pointermove({ clientX: x, clientY: y, pointerType: 'mouse' }); h.step(16); });
}

test('hovering across her body leaves a foam trail inside it that drifts and fades', () => {
    const h = harness();
    hover(h, Array.from({ length: 12 }, (_, i) => [70 + i * 7, 112]));
    const trail = h.state().trail;
    assert.ok(trail.length >= 6, 'foam is left along the path');
    const R0 = 224 * .285;
    assert.ok(trail.every(foam => Math.hypot(foam.x, foam.y) <= 1.05), 'foam stays inside her body');
    assert.ok(trail.some(foam => foam.vx < 0), 'foam drifts back behind the movement');
    assert.ok(R0 > 0);
    h.steps(40);
    assert.equal(h.state().trail.length, 0, 'the wake fades away');
});

test('hovering outside her body, by touch, or with reduced motion leaves nothing', () => {
    const h = harness();
    hover(h, Array.from({ length: 8 }, (_, i) => [2 + i, 2]));
    assert.equal(h.state().trail.length, 0, 'outside the body');
    Array.from({ length: 8 }, (_, i) => h.listeners.pointermove({ clientX: 80 + i * 8, clientY: 112, pointerType: 'touch' }));
    assert.equal(h.state().trail.length, 0, 'touch has no hover');
    const r = harness({ reduced: true });
    hover(r, Array.from({ length: 8 }, (_, i) => [80 + i * 8, 112]));
    assert.equal(r.state().trail.length, 0, 'reduced motion');
});

test('the trail is bounded however long the mouse moves', () => {
    const h = harness();
    hover(h, Array.from({ length: 400 }, (_, i) => [112 + Math.cos(i / 6) * 40, 112 + Math.sin(i / 6) * 40]));
    assert.ok(h.state().trail.length <= 40);
});

test('the canvas can be larger than the avatar: her body keeps the avatar size, effects get room around it', () => {
    const h = harness({ bleed: 2 });
    assert.equal(h.state().size, 224, 'body is sized from the avatar');
    assert.equal(h.state().w, 448, 'canvas spans twice the avatar');
    h.poke(200, 112);
    assert.ok(Math.abs(h.state().poke.angle) < .2, 'pokes still aim from the avatar centre');
});

// Hover drag (owner, 2026-11-06): her outline bulges a little towards the cursor, then relaxes.
test('hovering pulls her shape towards the cursor and it relaxes after leaving', () => {
    const h = harness();
    hover(h, Array.from({ length: 10 }, (_, i) => [112 + i * 6, 112]));
    const pull = h.state().pull;
    assert.ok(pull.amount > .3 && pull.amount <= 1, 'visibly pulled, not too much');
    assert.ok(Math.abs(pull.angle) < .3, 'towards the cursor on the right');
    h.listeners.pointerleave();
    h.steps(40);
    assert.ok(h.state().pull.amount < .02, 'relaxes back');
});
