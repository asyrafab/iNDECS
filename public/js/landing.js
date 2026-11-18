/* Finite introduction and service guidance for the public landing page. */
(function () {
    const introduction = document.querySelector('.landing-introduction');
    const entrance = document.querySelector('[data-welcome-introduction]');
    const home = document.querySelector('[data-welcome-home]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let finishTimer;
    let request = 0;
    const svgNamespace = 'http://www.w3.org/2000/svg';

    function svgNode(name, attributes = {}) {
        const node = document.createElementNS(svgNamespace, name);
        Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
        return node;
    }

    function addDust(parent, x, y, seed, delay, impact = false) {
        if (!parent) return;
        const cluster = svgNode('g', { transform: `translate(${x} ${y})` });
        cluster.style.setProperty('--dust-delay', delay);
        if (impact) cluster.style.setProperty('--dust-duration', 'var(--duration-tile-dust)');
        for (let particle = 0; particle < (impact ? 3 : 5); particle++) {
            const angle = (seed * 1.7 + particle * 2.4);
            const travel = impact ? 1.3 + particle * 0.65 : 3 + particle * 1.2;
            const puff = svgNode('ellipse', {
                class: 'building-dust-particle', cx: 0, cy: 0,
                rx: impact ? 0.4 + particle * 0.12 : 0.65 + particle * 0.15,
                ry: impact ? 0.3 + particle * 0.1 : 0.45 + particle * 0.12,
            });
            const dx = impact ? Math.cos(angle) * travel : -travel + Math.cos(angle);
            const dy = impact ? Math.sin(angle) * travel : travel * 0.65 + Math.sin(angle);
            puff.style.setProperty('--dust-drift', `translate(${dx.toFixed(2)}px, ${dy.toFixed(2)}px) scale(2.4)`);
            cluster.append(puff);
        }
        parent.append(cluster);
    }

    function prepareBuildingEffects() {
        const tiles = introduction?.querySelector('[data-roof-tiles]');
        if (!tiles || tiles.childElementCount) return;
        const landscape = introduction.querySelector('[data-building-landscape]');
        // Fixed irregular clusters keep replay natural and clear of moving layers.
        const plants = [
            [-10.5, 62, 15, 12], [-12.5, 88, 17, 63],
            [-11.5, 111, 15, 147], [4, 132.5, 16, 218],
            [23, 130.5, 17, 279], [43, 134, 15, 26],
            [60, 131.8, 16, 116],
            [9, 45, 13, 39, true], [79, 109, 14, 172, true],
        ];
        plants.forEach(([x, y, size, rotation, late = false]) => {
            const position = svgNode('g', { transform: `translate(${x} ${y}) rotate(${rotation})` });
            const plant = svgNode('g', { class: late ? 'building-plant building-plant-late' : 'building-plant' });
            const crop = svgNode('svg', {
                x: -size / 2, y: -size / 2, width: size, height: size, viewBox: '0 0 20 20',
            });
            crop.append(svgNode('use', { href: '#building-plant-photo' }));
            plant.append(crop);
            position.append(plant);
            landscape?.append(position);
        });
        const tileDust = introduction.querySelector('[data-tile-dust]');
        const pieces = document.createDocumentFragment();
        // Clip the original roof, rather than approximating or recolouring its tiles.
        for (let row = 0; row < 16; row++) {
            for (let column = 0; column < 16; column++) {
                const x = column * 4;
                const y = row * 4;
                const order = row + column * 0.65 + ((row * 7 + column * 3) % 5);
                const tile = svgNode('g', { class: 'building-roof-tile' });
                tile.style.setProperty('--tile-order', order.toFixed(2));
                const dx = (column - 7.5) * 1.6;
                const dy = -12 - (15 - row) * 0.8;
                const rotation = ((row * 11 + column * 7) % 31) - 15;
                tile.style.setProperty('--tile-flight', `translate(${dx}px, ${dy}px) rotate(${rotation}deg) scale(.86)`);
                const crop = svgNode('svg', {
                    // A tiny shared edge prevents raster seams once every piece settles.
                    x, y, width: 4.05, height: 4.05, viewBox: `${x} ${y} 4.05 4.05`, overflow: 'hidden',
                });
                crop.append(svgNode('use', { href: '#building-roof-photo' }));
                tile.append(crop);
                pieces.append(tile);
                if (row % 2 === 0 && column % 2 === 0) {
                    addDust(tileDust, x + 2, y + 3.5, row * 16 + column,
                        `calc(var(--duration-roof-stagger) * ${order.toFixed(2)} + var(--duration-roof-tile) * .8)`, true);
                }
            }
        }
        tiles.append(pieces);
        const edges = {
            footprint: { x: 0, y: 56 }, walls: { x: 28, y: 28 }, roof: { x: 56, y: 0 },
        };
        Object.entries(edges).forEach(([layer, origin]) => {
            const dust = introduction.querySelector(`[data-building-dust="${layer}"]`);
            for (let i = 0; i < 12; i++) {
                const x = origin.x + (i < 6 ? 1 : (i - 5) * 9);
                const y = origin.y + (i < 6 ? 9 + i * 9 : 62);
                addDust(dust, x, y, i,
                    `calc(var(--duration-mark-story) * ${(0.26 + i * 0.012).toFixed(3)})`);
            }
        });
    }

    const textures = introduction ? [...new Set(Array.from(introduction.querySelectorAll('image'), image => image.getAttribute('href')))] : [];
    // Decode independent layers before starting so slow connections never reveal blanks.
    const assetsReady = Promise.all(textures.map(source => new Promise(resolve => {
        const image = new Image();
        image.src = source;
        image.decode().then(() => resolve(true), () => resolve(false));
    })));

    function reducedMotion() {
        return motion.matches || document.documentElement.dataset.motion === 'reduced';
    }

    function schedulePlantBloom() {
        const styles = getComputedStyle(introduction);
        const milliseconds = name => {
            const value = styles.getPropertyValue(name).trim();
            return parseFloat(value) * (value.endsWith('ms') ? 1 : 1000);
        };
        const story = milliseconds('--duration-mark-story');
        const firstStart = story * 0.25;
        const sequence = milliseconds('--duration-plant-sequence');
        const lastStart = firstStart + sequence;
        // At 40% of this eased movement the walls are clear of both marked sites.
        const sideClearance = firstStart + story * 0.25 * 0.4;
        const plants = Array.from(introduction.querySelectorAll('.building-plant'));
        for (let i = plants.length - 1; i > 0; i--) {
            const other = Math.floor(Math.random() * (i + 1));
            [plants[i], plants[other]] = [plants[other], plants[i]];
        }
        if (plants[0]?.classList.contains('building-plant-late')) {
            const eligible = plants.findIndex(plant => !plant.classList.contains('building-plant-late'));
            if (eligible >= 0) [plants[0], plants[eligible]] = [plants[eligible], plants[0]];
        }
        plants.forEach((plant, index) => {
            const final = index === plants.length - 1;
            const slot = firstStart + (lastStart - firstStart) * index / Math.max(1, plants.length - 1);
            let delay = final ? lastStart : Math.max(firstStart, Math.min(lastStart - sequence * 0.05,
                slot + (Math.random() - 0.5) * sequence * 0.08));
            if (index === 0) delay = firstStart;
            // The two marked plants must wait until the walls have moved past them.
            if (!final && plant.classList.contains('building-plant-late') && delay < sideClearance) {
                delay = sideClearance + Math.random() * (lastStart - sideClearance) * 0.5;
            }
            plant.style.setProperty('--plant-delay', `${delay.toFixed(1)}ms`);
        });
    }

    function stopIntroduction(immediate = false) {
        if (!entrance || entrance.hidden) return;
        if (entrance.classList.contains('is-leaving') && !immediate) return;
        request++;
        clearTimeout(finishTimer);
        const hide = () => {
            entrance.hidden = true;
            introduction.classList.remove('is-playing');
            home.inert = false;
            document.body.classList.remove('welcome-is-introducing');
            delete document.documentElement.dataset.welcome;
            scheduleCarousel();
            ensureGardenLoop();
        };
        if (immediate || reducedMotion()) hide();
        else {
            document.documentElement.dataset.welcome = 'revealing';
            entrance.classList.add('is-leaving');
            const duration = parseFloat(getComputedStyle(entrance).transitionDuration) * 1000;
            finishTimer = setTimeout(hide, duration + 50);
        }
    }

    async function playIntroduction() {
        clearTimeout(window.indecsWelcomeRecovery);
        if (!introduction || !entrance || !home || document.documentElement.hasAttribute('data-skip-welcome-introduction') || reducedMotion() || loginDialog?.open || document.querySelector('[data-application-launcher]')) {
            delete document.documentElement.dataset.welcome;
            scheduleCarousel();
            return;
        }
        document.documentElement.dataset.welcome = 'pending';
        entrance.hidden = false;
        home.inert = true;
        document.body.classList.add('welcome-is-introducing');
        const current = ++request;
        // A failed or stalled texture must never trap users behind the entrance.
        let loadingTimer;
        const loaded = await Promise.race([
            assetsReady,
            new Promise(resolve => { loadingTimer = setTimeout(() => resolve([false]), 3000); }),
        ]);
        clearTimeout(loadingTimer);
        if (current !== request) return;
        if (reducedMotion() || loaded.some(ready => !ready)) {
            stopIntroduction(true);
            return;
        }
        prepareBuildingEffects();
        schedulePlantBloom();
        introduction.classList.add('is-playing');
        // The artwork finishes at the wordmark reveal; omit the final static hold.
        const duration = parseFloat(getComputedStyle(introduction).getPropertyValue('--duration-mark-complete'));
        finishTimer = setTimeout(() => stopIntroduction(), duration);
    }

    function syncMotion() {
        if (reducedMotion()) stopIntroduction(true);
        scheduleCarousel();
    }
    introduction?.addEventListener('animationend', event => {
        if (event.target.matches('.building-roof') && event.animationName === 'building-separate') stopIntroduction();
    });
    motion.addEventListener('change', syncMotion);
    new MutationObserver(syncMotion).observe(document.documentElement, { attributes: true, attributeFilter: ['data-motion'] });

    const choices = [...document.querySelectorAll('.welcome-choice')];
    choices.forEach(choice => choice.addEventListener('toggle', () => {
        if (choice.open) choices.forEach(other => { if (other !== choice) other.open = false; });
    }));
    document.addEventListener('click', event => {
        if (!event.target.closest('.welcome-choice')) choices.forEach(choice => { choice.open = false; });
    });
    const loginDialog = document.querySelector('[data-login-dialog]');
    const loginTrigger = document.querySelector('[data-welcome-login]');
    const gallery = document.querySelector('[data-welcome-carousel]');
    const photos = [...document.querySelectorAll('[data-welcome-photo]')];
    const gardenCircle = document.querySelector('[data-welcome-garden]');
    const slides = JSON.parse(document.getElementById('welcome-slides')?.textContent || '[]');
    const squareIndices = gardenCircle && !gardenCircle.hidden
        ? slides.map((slide, index) => (!slide.image?.startsWith('garden-') ? index : -1)).filter(index => index >= 0)
        : [];
    let nextImage = photos.length;
    let phase = 0;
    let frameRequest = 0;
    let previousTime;
    let pointerVelocity = 0;
    let inertiaVelocity = 0;
    let currentVelocity = 0;
    let releaseSpeed = 0;
    let hoverPending = false;
    let lastDirection = 1;
    let settling = null;
    let drag = null;
    let lastRelease = null;
    const previousPositions = photos.map((_, slot) => slot * .5);
    const cachedImages = slides.map(slide => {
        const image = new Image();
        image.src = slide.src;
        return { image, ready: false };
    });
    const imagesReady = Promise.all(cachedImages.map(entry => entry.image.decode().then(() => { entry.ready = true; }, () => {})));
    photos.forEach((photo, slot) => { photo.dataset.photoIndex = String(slot); });
    const setting = name => parseFloat(getComputedStyle(gallery).getPropertyValue(name));

    function replacePhoto(photo, desired) {
        const pool = squareIndices.length ? squareIndices : slides.map((_, index) => index);
        for (let attempt = 0; attempt < pool.length; attempt++) {
            const candidate = attempt === 0 && desired !== undefined ? desired : pool[nextImage++ % pool.length];
            if (!cachedImages[candidate]?.ready) continue;
            const slide = slides[candidate];
            const image = photo.querySelector('img');
            image.src = slide.src;
            image.alt = slide.alt;
            image.className = `photo-${slide.crop}`;
            photo.dataset.photoIndex = String(candidate);
            photo.setAttribute('aria-label', `Pilih imej: ${slide.label}`);
            return;
        }
    }
    function renderBoxes() {
        phase = (phase % 1.5 + 1.5) % 1.5;
        photos.forEach((photo, slot) => {
            const position = ((slot * .5 + phase + .25) % 1.5 + 1.5) % 1.5 - .25;
            if (Math.abs(position - previousPositions[slot]) > .75) replacePhoto(photo);
            previousPositions[slot] = position;
            const opacity = gallery.dataset.motionState === 'resting' ? 1 : Math.min(1, Math.max(0, (position + .25) / .25), Math.max(0, (1.25 - position) / .25));
            photo.style.setProperty('--photo-position', position.toFixed(5));
            photo.style.setProperty('--photo-opacity', opacity.toFixed(4));
            photo.style.setProperty('--photo-layer', String(Math.max(1, Math.round(position * 2) + 1)));
        });
        gallery.dataset.travelPosition = phase.toFixed(5);
        gallery.dataset.travelVelocity = currentVelocity.toFixed(4);
    }
    function finishRest() {
        settling = null;
        currentVelocity = 0;
        phase = Math.round(phase / .5) * .5;
        gallery.dataset.motionState = 'resting';
        gallery.dataset.travelVelocity = '0';
        renderBoxes();
    }
    function parkImmediately() {
        cancelAnimationFrame(frameRequest);
        frameRequest = 0;
        previousTime = undefined;
        pointerVelocity = 0;
        inertiaVelocity = 0;
        releaseSpeed = 0;
        hoverPending = false;
        settling = null;
        const captured = drag?.id;
        drag = null;
        if (captured !== undefined && gallery.hasPointerCapture(captured)) gallery.releasePointerCapture(captured);
        finishRest();
    }
    function motionAllowed() {
        return gallery && !reducedMotion() && !document.hidden && entrance.hidden &&
            !loginDialog?.open && !document.querySelector('[data-application-launcher][open]') && !choices.some(choice => choice.open) && !gallery.querySelector(':focus-visible');
    }
    function beginSettle(velocity = currentVelocity) {
        if (settling) return;
        const aligned = Math.round(phase / .5) * .5;
        if (Math.abs(phase - aligned) < .00001 && Math.abs(velocity) < .04) { finishRest(); return; }
        const baseDuration = setting('--duration-carousel-settle');
        const speed = Math.abs(velocity);
        const direction = speed > .04 ? Math.sign(velocity) : lastDirection;
        const runway = Math.max(.00001, speed * baseDuration / 2000);
        const target = direction > 0 ? Math.ceil((phase + runway) / .5) * .5 : Math.floor((phase - runway) / .5) * .5;
        const distance = Math.abs(target - phase);
        const duration = speed > .04 ? Math.min(setting('--duration-carousel-settle-max'), 2000 * distance / speed) : baseDuration * (.4 + .6 * distance / .5);
        settling = {from: phase, target, started: null, duration, tangent: velocity * duration / 1000};
        inertiaVelocity = 0;
        gallery.dataset.motionState = 'settling';
    }
    function tick(timestamp) {
        frameRequest = 0;
        if (!motionAllowed()) { parkImmediately(); return; }
        const elapsed = previousTime === undefined ? 0 : Math.min((timestamp - previousTime) / 1000, .05);
        previousTime = timestamp;
        if (drag) return;
        if (hoverPending && Math.abs(inertiaVelocity) <= releaseSpeed * setting('--carousel-hover-takeover-ratio')) {
            inertiaVelocity = 0;
            hoverPending = false;
        }
        if (Math.abs(inertiaVelocity) > .04 && (hoverPending || pointerVelocity !== 0 || Math.abs(inertiaVelocity) > setting('--carousel-settle-speed'))) {
            settling = null;
            gallery.dataset.motionState = 'spinning';
            lastDirection = Math.sign(inertiaVelocity);
            const friction = setting('--carousel-spin-friction');
            const decay = Math.exp(-friction * elapsed);
            phase += inertiaVelocity * (1 - decay) / friction;
            inertiaVelocity *= decay;
            currentVelocity = inertiaVelocity;
        } else if (pointerVelocity !== 0) {
            inertiaVelocity = 0;
            settling = null;
            gallery.dataset.motionState = 'moving';
            lastDirection = Math.sign(pointerVelocity);
            currentVelocity = pointerVelocity;
            phase += pointerVelocity * elapsed;
        } else {
            beginSettle();
            if (!settling) return;
            settling.started ??= timestamp - elapsed * 1000;
            const progress = Math.min(1, (timestamp - settling.started) / settling.duration);
            const distance = settling.target - settling.from;
            phase = settling.from + distance * (3 * progress ** 2 - 2 * progress ** 3) + settling.tangent * (progress ** 3 - 2 * progress ** 2 + progress);
            currentVelocity = (distance * (6 * progress - 6 * progress ** 2) + settling.tangent * (3 * progress ** 2 - 4 * progress + 1)) * 1000 / settling.duration;
            if (progress === 1) { finishRest(); return; }
        }
        renderBoxes();
        frameRequest = requestAnimationFrame(tick);
    }
    function scheduleCarousel() {
        if (!motionAllowed()) { parkImmediately(); return; }
        if (drag) return;
        if (pointerVelocity === 0 && Math.abs(inertiaVelocity) <= .04) {
            inertiaVelocity = 0;
            beginSettle();
            if (!settling) return;
        }
        if (!frameRequest) {
            previousTime = undefined;
            frameRequest = requestAnimationFrame(tick);
        }
    }
    async function changeSlides(direction) {
        await imagesReady;
        parkImmediately();
        const pool = squareIndices.length ? squareIndices : slides.map((_, index) => index);
        photos.forEach(photo => {
            const currentPos = Math.max(0, pool.indexOf(Number(photo.dataset.photoIndex)));
            replacePhoto(photo, pool[(currentPos + direction + pool.length) % pool.length]);
        });
        gallery.querySelector('[data-carousel-status]').textContent = 'Imej baharu dipaparkan';
    }
    gallery?.addEventListener('pointermove', event => {
        if (drag && event.pointerId === drag.id) {
            const now = performance.now();
            const rect = gallery.getBoundingClientRect();
            const delta = ((event.clientX - drag.x) / rect.width - (event.clientY - drag.y) / rect.height) * setting('--carousel-drag-gain');
            const seconds = Math.max(.008, (now - drag.time) / 1000);
            const maximum = setting('--carousel-spin-max');
            const velocity = Math.max(-maximum, Math.min(maximum, delta / seconds));
            drag.velocity = drag.velocity * .45 + velocity * .55;
            currentVelocity = drag.velocity;
            drag.travel += delta;
            drag.x = event.clientX;
            drag.y = event.clientY;
            drag.time = now;
            if (delta !== 0) lastDirection = Math.sign(delta);
            if (!reducedMotion()) { phase += delta; renderBoxes(); }
            event.preventDefault();
            return;
        }
        if (event.pointerType !== 'mouse' || event.buttons !== 0) return;
        if (event.target.closest('[data-welcome-garden]')) {
            if (pointerVelocity !== 0) {
                pointerVelocity = 0;
                hoverPending = false;
                gallery.dataset.pointerSpeed = '0';
                gallery.dataset.speedTier = '0';
                scheduleCarousel();
            }
            return;
        }
        // Pointer position steers the whole region, including gaps between boxes.
        if (lastRelease && Math.hypot(event.clientX - lastRelease.x, event.clientY - lastRelease.y) < 3) return;
        lastRelease = null;
        const rect = gallery.getBoundingClientRect();
        const diagonal = (event.clientX - rect.left) / rect.width - (event.clientY - rect.top) / rect.height;
        const distance = Math.min(1, Math.max(0, (Math.abs(diagonal) - .08) / .92));
        const tier = distance === 0 ? 0 : Math.min(4, Math.ceil(distance * 4));
        pointerVelocity = tier === 0 ? 0 : -Math.sign(diagonal) * setting(`--carousel-speed-${tier}`);
        // Preserve release momentum until it falls to the configured speed ratio.
        hoverPending = true;
        if (Math.abs(inertiaVelocity) <= releaseSpeed * setting('--carousel-hover-takeover-ratio')) {
            inertiaVelocity = 0;
            hoverPending = false;
            settling = null;
        }
        gallery.dataset.speedTier = String(tier);
        gallery.dataset.pointerSpeed = pointerVelocity.toFixed(4);
        scheduleCarousel();
    });
    gallery?.addEventListener('pointerleave', event => {
        if (drag || event.pointerType !== 'mouse') return;
        pointerVelocity = 0;
        hoverPending = false;
        gallery.dataset.pointerSpeed = '0';
        gallery.dataset.speedTier = '0';
        scheduleCarousel();
    });
    gallery?.addEventListener('pointerdown', event => {
        if (!event.isPrimary || event.button !== 0 || loginDialog?.open || !entrance.hidden) return;
        if (event.target.closest('[data-welcome-garden]')) return;
        event.preventDefault();
        if (gallery.contains(document.activeElement)) document.activeElement.blur();
        cancelAnimationFrame(frameRequest);
        frameRequest = 0;
        previousTime = undefined;
        pointerVelocity = 0;
        const carryVelocity = inertiaVelocity || (settling ? currentVelocity : 0);
        inertiaVelocity = 0;
        releaseSpeed = 0;
        hoverPending = false;
        settling = null;
        lastRelease = null;
        const started = performance.now();
        drag = {id: event.pointerId, x: event.clientX, y: event.clientY, time: started, started, carryVelocity, velocity: 0, travel: 0};
        gallery.setPointerCapture(event.pointerId);
        gallery.dataset.motionState = 'dragging';
        gallery.dataset.speedTier = '0';
    });
    function endDrag(event, cancelled = false) {
        if (!drag || event.pointerId !== drag.id) return;
        const released = drag;
        drag = null;
        lastRelease = {x: event.clientX, y: event.clientY};
        if (gallery.hasPointerCapture(event.pointerId)) gallery.releasePointerCapture(event.pointerId);
        if (reducedMotion()) {
            if (!cancelled && Math.abs(released.travel) > .04) changeSlides(Math.sign(released.travel));
            else parkImmediately();
            return;
        }
        const now = performance.now();
        const carry = released.carryVelocity * Math.exp(-setting('--carousel-spin-friction') * (now - released.started) / 1000);
        const impulse = now - released.time < 120 ? released.velocity : 0;
        const maximum = setting('--carousel-spin-max');
        inertiaVelocity = cancelled ? 0 : Math.max(-maximum, Math.min(maximum, carry + impulse));
        currentVelocity = inertiaVelocity;
        releaseSpeed = Math.abs(inertiaVelocity);
        gallery.dataset.releaseVelocity = inertiaVelocity.toFixed(4);
        scheduleCarousel();
    }
    gallery?.addEventListener('pointerup', event => endDrag(event));
    gallery?.addEventListener('pointercancel', event => endDrag(event, true));
    gallery?.addEventListener('lostpointercapture', event => endDrag(event, true));
    gallery?.addEventListener('dragstart', event => event.preventDefault());
    gallery?.addEventListener('focusin', scheduleCarousel);
    gallery?.addEventListener('focusout', () => setTimeout(scheduleCarousel, 0));
    document.addEventListener('visibilitychange', scheduleCarousel);
    choices.forEach(choice => choice.addEventListener('toggle', scheduleCarousel));
    document.querySelectorAll('[data-welcome-close]').forEach(close => close.addEventListener('click', () => {
        const choice = close.closest('details');
        choice.open = false;
        choice.querySelector('summary').focus();
    }));
    gallery?.addEventListener('keydown', event => {
        if (!['ArrowRight', 'ArrowUp', 'ArrowLeft', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        changeSlides(['ArrowRight', 'ArrowUp'].includes(event.key) ? 1 : -1);
    });
    parkImmediately();

    const gardenGrass = gardenCircle?.querySelector('[data-garden-grass]');
    const gardenPlants = gardenCircle?.querySelector('[data-garden-plants]');
    const gardenBlades = [];
    const parkPlants = [];
    const windGusts = [];
    let gardenFrame = 0;
    let gardenPrevTime;
    let gardenPointer = null;

    function initInteractiveGarden() {
        if (!gardenCircle || gardenCircle.hidden || !gardenGrass || !gardenPlants || gardenBlades.length) return;
        const grassFragment = document.createDocumentFragment();
        const classes = ['welcome-garden-blade-deep', 'welcome-garden-blade-mid', 'welcome-garden-blade-light'];
        let seed = 17;
        const rand = () => {
            seed = (seed * 16807) % 2147483647;
            return (seed - 1) / 2147483646;
        };
        for (let ring = 10; ring <= 47; ring += 5.2) {
            const count = Math.max(8, Math.round(ring * 0.62));
            for (let i = 0; i < count; i++) {
                const angle = (i / count) * Math.PI * 2 + rand() * 0.35;
                const radius = ring + (rand() - 0.5) * 3.4;
                const x = 60 + Math.cos(angle) * radius;
                const y = 60 + Math.sin(angle) * radius;
                if (x < 34 && y < 34) continue;
                const length = 3.6 + rand() * 2.6;
                const baseLean = (rand() - 0.35) * 1.4;
                const node = svgNode('path', {
                    class: classes[i % classes.length],
                    d: `M${x.toFixed(2)} ${y.toFixed(2)}Q${(x + baseLean * 0.5).toFixed(2)} ${(y - length * 0.55).toFixed(2)} ${(x + baseLean).toFixed(2)} ${(y - length).toFixed(2)}`,
                });
                grassFragment.append(node);
                gardenBlades.push({ node, x, y, length, baseLean, dx: 0, dy: 0, vx: 0, vy: 0, phase: rand() * Math.PI * 2 });
            }
        }
        gardenGrass.append(grassFragment);

        const plantSpecs = [
            { x: 92, y: 46, size: 17, rot: 18, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
            { x: 99, y: 66, size: 19, rot: 74, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
            { x: 91, y: 86, size: 18, rot: 142, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
            { x: 74, y: 97, size: 19, rot: 215, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
            { x: 52, y: 98, size: 16, rot: 288, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
            { x: 77, y: 73, size: 22, rot: 35, href: '#welcome-palm-photo', viewBox: '0 0 26 26' },
            { x: 63, y: 84, size: 16, rot: 112, href: '#welcome-plant-photo', viewBox: '0 0 20 20' },
        ];
        const plantFragment = document.createDocumentFragment();
        plantSpecs.forEach((spec, idx) => {
            const group = svgNode('g', {
                class: 'welcome-garden-plant',
                transform: `translate(${spec.x} ${spec.y}) rotate(${spec.rot}) scale(1)`,
                'data-garden-plant-index': String(idx),
            });
            const crop = svgNode('svg', {
                x: -spec.size / 2, y: -spec.size / 2,
                width: spec.size, height: spec.size,
                viewBox: spec.viewBox,
            });
            crop.append(svgNode('use', { href: spec.href }));
            group.append(crop);
            plantFragment.append(group);
            parkPlants.push({ ...spec, node: group, tx: 0, ty: 0, r: 0, s: 0, vx: 0, vy: 0, vr: 0, vs: 0 });
        });
        gardenPlants.append(plantFragment);
    }

    function renderGardenStatic() {
        gardenBlades.forEach(b => {
            b.dx = 0; b.dy = 0; b.vx = 0; b.vy = 0;
            b.node.setAttribute('d', `M${b.x.toFixed(2)} ${b.y.toFixed(2)}Q${(b.x + b.baseLean * 0.5).toFixed(2)} ${(b.y - b.length * 0.55).toFixed(2)} ${(b.x + b.baseLean).toFixed(2)} ${(b.y - b.length).toFixed(2)}`);
        });
        parkPlants.forEach(p => {
            p.tx = 0; p.ty = 0; p.r = 0; p.s = 0; p.vx = 0; p.vy = 0; p.vr = 0; p.vs = 0;
            p.node.setAttribute('transform', `translate(${p.x} ${p.y}) rotate(${p.rot}) scale(1)`);
        });
    }

    function tickGarden(timestamp) {
        gardenFrame = 0;
        if (!gardenCircle || reducedMotion() || document.hidden || !entrance.hidden) {
            gardenPrevTime = undefined;
            if (reducedMotion()) renderGardenStatic();
            return;
        }
        const dt = gardenPrevTime === undefined ? 0.016 : Math.min((timestamp - gardenPrevTime) / 1000, 0.05);
        gardenPrevTime = timestamp;
        const t = timestamp / 1000;

        for (let i = windGusts.length - 1; i >= 0; i--) {
            windGusts[i].radius += dt * 68;
            windGusts[i].strength *= Math.exp(-dt * 3.2);
            if (windGusts[i].strength < 0.08) windGusts.splice(i, 1);
        }

        gardenBlades.forEach(b => {
            const wave = Math.sin(t * 2.3 - (b.x * 0.085 + b.y * 0.06) + b.phase * 0.35)
                + 0.55 * Math.sin(t * 4.1 - b.x * 0.14 + b.y * 0.05);
            let targetX = b.baseLean + wave * 2.35;
            let targetY = Math.abs(wave) * 0.65;

            windGusts.forEach(g => {
                const dist = Math.hypot(b.x - g.x, b.y - g.y);
                const band = Math.abs(dist - g.radius);
                if (band < 14 && dist > 0.1) {
                    const push = (1 - band / 14) * g.strength * 4.5;
                    targetX += ((b.x - g.x) / dist) * push;
                    targetY += ((b.y - g.y) / dist) * push * 0.7;
                }
            });

            b.vx += (targetX - b.dx) * 26 * dt;
            b.vy += (targetY - b.dy) * 26 * dt;
            const damping = Math.exp(-7.5 * dt);
            b.vx *= damping;
            b.vy *= damping;
            b.dx += b.vx * dt * 12;
            b.dy += b.vy * dt * 12;

            const tipX = b.x + Math.max(-6.5, Math.min(6.5, b.dx));
            const tipY = b.y - b.length + Math.max(-2.5, Math.min(3.5, b.dy));
            const ctrlX = b.x + (tipX - b.x) * 0.42;
            const ctrlY = b.y - b.length * 0.58 + (b.dy * 0.25);
            b.node.setAttribute('d', `M${b.x.toFixed(2)} ${b.y.toFixed(2)}Q${ctrlX.toFixed(2)} ${ctrlY.toFixed(2)} ${tipX.toFixed(2)} ${tipY.toFixed(2)}`);
        });

        parkPlants.forEach((p, idx) => {
            const breeze = Math.sin(t * 2.1 - (p.x * 0.07 + p.y * 0.05) + idx);
            const targetTx = breeze * 0.85;
            const targetTy = Math.cos(t * 1.8 + idx) * 0.45;
            const targetR = breeze * 4.2;

            p.vx += (targetTx - p.tx) * 28 * dt;
            p.vy += (targetTy - p.ty) * 28 * dt;
            p.vr += (targetR - p.r) * 24 * dt;
            p.vs += (0 - p.s) * 34 * dt;
            const damp = Math.exp(-6.2 * dt);
            p.vx *= damp; p.vy *= damp; p.vr *= damp; p.vs *= damp;
            p.tx += p.vx * dt * 10;
            p.ty += p.vy * dt * 10;
            p.r += p.vr * dt * 10;
            p.s += p.vs * dt * 10;

            const scale = 1 + Math.max(-0.12, Math.min(0.22, p.s));
            p.node.setAttribute(
                'transform',
                `translate(${(p.x + p.tx).toFixed(2)} ${(p.y + p.ty).toFixed(2)}) rotate(${(p.rot + p.r).toFixed(2)}) scale(${scale.toFixed(3)})`
            );
        });

        gardenFrame = requestAnimationFrame(tickGarden);
    }

    function ensureGardenLoop() {
        if (!gardenCircle || gardenCircle.hidden) return;
        initInteractiveGarden();
        if (reducedMotion()) {
            if (gardenFrame) { cancelAnimationFrame(gardenFrame); gardenFrame = 0; }
            renderGardenStatic();
            return;
        }
        if (!gardenFrame && !document.hidden && entrance.hidden) {
            gardenPrevTime = undefined;
            gardenFrame = requestAnimationFrame(tickGarden);
        }
    }

    function toGardenCoords(clientX, clientY) {
        const rect = gardenCircle.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        return {
            x: ((clientX - rect.left) / rect.width) * 120,
            y: ((clientY - rect.top) / rect.height) * 120,
        };
    }

    gallery?.addEventListener('pointermove', event => {
        if (!gardenCircle || gardenCircle.hidden || reducedMotion() || !entrance.hidden) return;
        const pt = toGardenCoords(event.clientX, event.clientY);
        if (!pt) return;
        const distFromCenter = Math.hypot(pt.x - 60, pt.y - 60);
        if (distFromCenter > 68) {
            gardenPointer = null;
            return;
        }
        const vx = gardenPointer ? Math.max(-18, Math.min(18, pt.x - gardenPointer.x)) : 1.8;
        const vy = gardenPointer ? Math.max(-18, Math.min(18, pt.y - gardenPointer.y)) : 0.8;
        gardenPointer = pt;

        gardenBlades.forEach(b => {
            const d = Math.hypot(b.x - pt.x, b.y - pt.y);
            if (d < 22) {
                const influence = (1 - d / 22);
                b.vx += (vx * 1.35 + (b.x - pt.x) * 0.28) * influence;
                b.vy += (vy * 0.9 + (b.y - pt.y) * 0.22) * influence;
            }
        });
        parkPlants.forEach(p => {
            const d = Math.hypot(p.x - pt.x, p.y - pt.y);
            if (d < 24) {
                const influence = (1 - d / 24);
                p.vx += vx * 0.55 * influence;
                p.vy += vy * 0.55 * influence;
                p.vr += (vx - vy) * 2.4 * influence;
                p.vs += 0.045 * influence;
            }
        });
        ensureGardenLoop();
    });

    gardenCircle?.addEventListener('pointerdown', event => {
        if (reducedMotion() || !entrance.hidden) return;
        const pt = toGardenCoords(event.clientX, event.clientY);
        if (!pt) return;
        windGusts.push({ x: pt.x, y: pt.y, radius: 2, strength: 1.5 });
        parkPlants.forEach(p => {
            const d = Math.hypot(p.x - pt.x, p.y - pt.y);
            if (d < 34) {
                const boost = 1 - d / 34;
                const nx = d > 0.1 ? (p.x - pt.x) / d : 0.7;
                const ny = d > 0.1 ? (p.y - pt.y) / d : 0.7;
                p.vx += nx * 5.5 * boost;
                p.vy += ny * 5.5 * boost;
                p.vr += (d < 14 ? 42 : 24) * boost;
                p.vs += 0.22 * boost;
            }
        });
        ensureGardenLoop();
    });

    document.addEventListener('visibilitychange', ensureGardenLoop);
    motion.addEventListener('change', ensureGardenLoop);
    initInteractiveGarden();
    ensureGardenLoop();
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        choices.forEach(choice => { if (choice.open) { choice.open = false; choice.querySelector('summary').focus(); } });
        // The finite opening sequence intentionally has no skip or Escape action.
    });
    const loginTabs = [...document.querySelectorAll('[data-login-tab-button]')];
    function selectLoginTab(name) {
        loginDialog.dataset.loginTab = name;
        loginTabs.forEach(tab => {
            const selected = tab.dataset.loginTabButton === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        loginDialog.querySelectorAll('[data-login-panel]').forEach(panel => { panel.hidden = panel.dataset.loginPanel !== name; });
        loginDialog.querySelectorAll('[data-login-portrait]').forEach(portrait => { portrait.hidden = portrait.dataset.loginPortrait !== name; });
    }
    function showLogin() {
        if (!loginDialog || loginDialog.open || typeof loginDialog.showModal !== 'function') return;
        choices.forEach(choice => { choice.open = false; });
        pointerVelocity = 0;
        inertiaVelocity = 0;
        parkImmediately();
        loginDialog.classList.add('is-measuring');
        loginDialog.showModal();
        const trigger = loginTrigger.getBoundingClientRect();
        const panel = loginDialog.getBoundingClientRect();
        loginDialog.style.setProperty('--login-origin-x', `${trigger.x + trigger.width / 2 - panel.x - panel.width / 2}px`);
        loginDialog.style.setProperty('--login-origin-y', `${trigger.y + trigger.height / 2 - panel.y - panel.height / 2}px`);
        loginDialog.style.setProperty('--login-origin-scale', String(Math.min(.65, Math.max(.2, trigger.width / panel.width))));
        void loginDialog.offsetWidth;
        loginDialog.classList.remove('is-measuring');
        loginDialog.querySelector('[data-login-panel]:not([hidden]) input:not([type="hidden"])')?.focus({preventScroll:true});
        scheduleCarousel();
    }
    loginTrigger?.addEventListener('click', event => {
        if (!loginDialog || typeof loginDialog.showModal !== 'function') return;
        event.preventDefault();
        showLogin();
    });
    loginDialog?.querySelector('[data-login-close]')?.addEventListener('click', () => loginDialog.close());
    loginDialog?.addEventListener('close', () => {
        loginDialog.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        loginTrigger?.focus({preventScroll:true});
        scheduleCarousel();
    });
    loginDialog?.addEventListener('click', event => {
        const rect = loginDialog.getBoundingClientRect();
        if (event.target === loginDialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) loginDialog.close();
    });
    loginDialog?.addEventListener('keydown', event => {
        if (event.key !== 'Tab') return;
        const focusable = [...loginDialog.querySelectorAll('a[href], button, input, [tabindex]')].filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length > 0);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    loginTabs.forEach(tab => {
        tab.addEventListener('click', () => selectLoginTab(tab.dataset.loginTabButton));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const index = loginTabs.indexOf(tab);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? loginTabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + loginTabs.length) % loginTabs.length;
            selectLoginTab(loginTabs[next].dataset.loginTabButton);
            loginTabs[next].focus();
        });
    });
    loginDialog?.querySelector('[data-staff-login-help]')?.addEventListener('click', event => {
        const help = loginDialog.querySelector('#welcome-staff-help');
        help.hidden = !help.hidden;
        event.currentTarget.setAttribute('aria-expanded', String(!help.hidden));
    });
    if (loginDialog?.hasAttribute('data-reopen-login')) showLogin();
    playIntroduction();
})();
