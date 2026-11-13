const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const vm = require('node:vm');

const source = readFileSync(resolve(__dirname, '../../public/js/indecs.js'), 'utf8');

function harness() {
    let clock = 0, id = 0, motionChanged;
    const frames = new Map(), events = {}, observers = [];
    const html = { dataset: {} };
    const document = {
        documentElement: html, hidden: false,
        addEventListener(name, callback) { events[name] = callback; },
    };
    const media = { matches: false, addEventListener(name, callback) { motionChanged = callback; } };
    const values = {
        '--duration-amira-character': '8ms', '--duration-amira-reply-max': '2200ms',
        '--duration-amira-message-enter': '280ms', '--scale-amira-message-enter': '.94',
        '--ease-standard': 'cubic-bezier(.2, .7, .2, 1)',
    };
    const window = { matchMedia: () => media, AmiraBubble: { setSpeaking(el, on) { el.speaking = on; } } };
    const context = vm.createContext({
        window, document, Intl, performance: { now: () => clock },
        getComputedStyle: () => ({ getPropertyValue: key => values[key] }),
        requestAnimationFrame(callback) { frames.set(++id, callback); return id; },
        cancelAnimationFrame(key) { frames.delete(key); },
        MutationObserver: class {
            constructor(callback) { observers.push(callback); }
            observe() {}
        },
    });
    vm.runInContext(source, context);
    const reply = vm.runInContext('animateAmiraReply', context);
    function panel() {
        const animations = [];
        const node = () => ({
            style: {}, textContent: '', hidden: true, attributes: {},
            getBoundingClientRect: () => ({ height: 80 }),
            setAttribute(name, value) { this.attributes[name] = value; },
            animate(keyframes, options) {
                const animation = { keyframes, options, cancelled: false, cancel() { this.cancelled = true; } };
                animations.push(animation);
                return animation;
            },
        });
        const answer = node(), copy = node(), user = node(), title = node(), avatar = {};
        const nodes = { 'answer': answer, 'answer-copy': copy, 'user': user, 'answer-title': title, 'bubble': avatar };
        const root = { isConnected: true, querySelector(selector) { return nodes[selector.slice(12, -1)]; } };
        return { root, answer, copy, user, title, avatar, animations };
    }
    return {
        panel, document, html, frames, media,
        reply(panel, text, question = 'Bantu saya') { reply(panel.root, 'Panduan', text, question); },
        step(ms) {
            clock += ms;
            const pending = [...frames.values()];
            frames.clear();
            pending.forEach(callback => callback(clock));
        },
        reduce() { html.dataset.motion = 'reduced'; observers.forEach(callback => callback()); },
        reduceOS() { media.matches = true; motionChanged(); },
        hide() { document.hidden = true; events.visibilitychange(); },
    };
}

test('reply types complete graphemes, reserves height and completes its live announcement', () => {
    const h = harness(), p = h.panel();
    const text = 'A👩🏽‍💻e\u0301Z';
    h.reply(p, text);
    assert.equal(p.copy.textContent, '');
    assert.equal(p.copy.style.minHeight, '80px');
    assert.equal(p.answer.attributes['aria-busy'], 'true');
    assert.equal(p.avatar.speaking, true);
    h.step(8);
    assert.equal(p.copy.textContent, 'A');
    h.step(8);
    assert.equal(p.copy.textContent, 'A👩🏽‍💻');
    h.step(16);
    assert.equal(p.copy.textContent, text);
    assert.equal(p.copy.style.minHeight, '');
    assert.equal(p.answer.attributes['aria-busy'], 'false');
    assert.equal(p.avatar.speaking, false);
    assert.equal(h.frames.size, 0);
    assert.equal(p.animations.length, 2);
    assert.equal(p.animations[1].keyframes[0].transform, 'scale(.94)');
});

test('a new exchange cancels the previous reveal and replays message entrance', () => {
    const h = harness(), p = h.panel();
    h.reply(p, 'Old reply still typing');
    h.step(16);
    h.reply(p, 'New reply', 'New question');
    assert.equal(h.frames.size, 1);
    assert.equal(p.user.textContent, 'New question');
    assert.equal(p.copy.textContent, '');
    assert.equal(p.animations.length, 4);
    assert.ok(p.animations[0].cancelled && p.animations[1].cancelled);
    h.step(80);
    assert.equal(p.copy.textContent, 'New reply');
    assert.equal(h.frames.size, 0);
});

test('reduced motion shows immediate text and finishes an active reply on live preference changes', () => {
    const h = harness(), p = h.panel();
    h.reduce();
    h.reply(p, 'Immediate reply');
    assert.equal(p.copy.textContent, 'Immediate reply');
    assert.equal(p.animations.length, 0);
    assert.equal(h.frames.size, 0);

    const active = harness(), q = active.panel();
    active.reply(q, 'Finish this complete reply');
    active.step(8);
    active.reduceOS();
    assert.equal(q.copy.textContent, 'Finish this complete reply');
    assert.equal(q.answer.attributes['aria-busy'], 'false');
    assert.equal(active.frames.size, 0);
    assert.ok(q.animations.every(animation => animation.cancelled));
});

test('hidden pages finish replies, and long replies have a bounded duration', () => {
    const h = harness(), p = h.panel();
    const text = 'Long reply '.repeat(1000);
    h.reply(p, text);
    h.step(2200);
    assert.equal(p.copy.textContent, text);
    assert.equal(h.frames.size, 0);
    h.reply(p, 'Complete on hide');
    h.hide();
    assert.equal(p.copy.textContent, 'Complete on hide');
    assert.equal(h.frames.size, 0);
});

test('separate panels reveal independently and content remains plain text', () => {
    const h = harness(), p = h.panel(), q = h.panel();
    h.reply(p, '<img src=x onerror=alert(1)>');
    h.reply(q, 'Separate reply');
    h.step(40);
    assert.equal(p.copy.textContent, '<img ');
    assert.equal(q.copy.textContent, 'Separ');
    h.reply(p, 'Replacement');
    h.step(2200);
    assert.equal(p.copy.textContent, 'Replacement');
    assert.equal(q.copy.textContent, 'Separate reply');
});
