import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

class Node {
    constructor() {
        this.listeners = {}; this.children = {}; this.dataset = {}; this.open = false;
        this.classes = new Set(); this.values = {};
        this.classList = { add: (...names) => names.forEach(n => this.classes.add(n)), remove: (...names) => names.forEach(n => this.classes.delete(n)) };
        this.style = { setProperty: (key, value) => { this.values[key] = value; } };
    }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter(item => item !== fn); }
    emit(type, props = {}) {
        const event = { target: this, preventDefault() { this.defaultPrevented = true; }, ...props };
        for (const fn of [...(this.listeners[type] || [])]) fn(event);
        return event;
    }
    dispatchEvent(event) { this.emit(event.type); }
    querySelector(selector) { return this.children[selector] || null; }
    querySelectorAll(selector) { const value = this.children[selector]; return value ? (Array.isArray(value) ? value : [value]) : []; }
    matches(selector) { return selector.includes('.draft-remove-dialog') || (selector === 'form[data-draft-remove]' && this.isForm); }
    focus() { this.focused = true; }
    setPointerCapture() {}
    getBoundingClientRect() { return { left: 0, top: 0, right: 100, bottom: 40 }; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
}

function boot() {
    const document = new Node(), window = new Node(), dialog = new Node(), hold = new Node(), cancel = new Node(), trigger = new Node(), form = new Node();
    const name = new Node(), status = new Node();
    dialog.children = { '[data-draft-remove-hold]': hold, '[data-draft-remove-cancel]': cancel, '[data-portal-close]': [cancel], '[data-draft-remove-name]': name, '[data-draft-remove-status]': status };
    document.children = { '.portal-dialog': [dialog], '[data-draft-remove-trigger]': [trigger] };
    document.getElementById = id => id === 'draft-remove-confirm' ? dialog : null;
    document.documentElement = { dataset: {} };
    window.matchMedia = () => ({ matches: false });
    let now = 0, next = 0, submissions = 0;
    const frames = new Map();
    form.isForm = true; form.dataset.draftTitle = 'Draf ujian';
    form.requestSubmit = () => { if (!document.emit('submit', { target: form, submitter: trigger }).defaultPrevented) submissions++; };
    const context = { document, window, Event: class { constructor(type) { this.type = type; } }, performance: { now: () => now },
        getComputedStyle: () => ({ animationDuration: '.28s', getPropertyValue: () => '1200ms' }),
        setTimeout: () => ++next, clearTimeout() {},
        requestAnimationFrame: fn => { frames.set(++next, fn); return next; }, cancelAnimationFrame: id => frames.delete(id) };
    vm.runInNewContext(readFileSync(new URL('../../public/js/portal.js', import.meta.url), 'utf8'), context);
    const open = () => document.emit('submit', { target: form, submitter: trigger });
    const advance = ms => { now += ms; const pending = [...frames.values()]; frames.clear(); pending.forEach(fn => fn(now)); };
    const down = () => hold.emit('pointerdown', { button: 0, isPrimary: true, pointerId: 1 });
    return { document, window, dialog, hold, cancel, trigger, name, status, open, advance, down, submissions: () => submissions };
}

test('short presses and pointer cancellation cannot remove a draft', () => {
    const ui = boot();
    assert.equal(ui.trigger.disabled, false);
    assert.equal(ui.open().defaultPrevented, true);
    assert.equal(ui.name.textContent, 'Draf ujian');
    ui.down(); ui.advance(600); ui.hold.emit('pointerup'); ui.advance(1200);
    assert.equal(ui.submissions(), 0);
    ui.down(); ui.hold.emit('pointermove', { clientX: 120, clientY: 20 }); ui.advance(1200);
    assert.equal(ui.submissions(), 0);
});

test('a completed keyboard hold submits once and repeat events cannot resubmit', () => {
    const ui = boot(); ui.open();
    ui.hold.emit('keydown', { key: ' ', repeat: false }); ui.advance(1200);
    assert.equal(ui.submissions(), 1);
    ui.hold.emit('keydown', { key: ' ', repeat: true }); ui.advance(2400);
    assert.equal(ui.submissions(), 1);
});

test('cancel resets a hold and waits for exit animation before closing', () => {
    const ui = boot(); ui.open(); ui.down(); ui.advance(600);
    ui.cancel.emit('click'); ui.advance(1200);
    assert.equal(ui.submissions(), 0);
    assert.equal(ui.dialog.open, true);
    assert.ok(ui.dialog.classes.has('is-closing'));
    ui.dialog.emit('animationend', { animationName: 'document-dialog-exit' });
    assert.equal(ui.dialog.open, false);
    assert.equal(ui.trigger.focused, true);
});

test('hidden pages cancel a hold', () => {
    const ui = boot(); ui.open(); ui.down(); ui.advance(600);
    ui.document.hidden = true; ui.document.emit('visibilitychange'); ui.advance(1200);
    assert.equal(ui.submissions(), 0);
});
