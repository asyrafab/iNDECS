/* Shared OpenLayers map: base imagery, vector-tile reference layers, layer switcher, measuring, swipe.
   Colours are read from CSS custom properties at runtime (never hex), so light and dark follow the tokens. */
(function () {
    'use strict';

    const root = document.documentElement;
    const reduced = () => root.dataset.motion === 'reduced' || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* Resolve a token to an rgb() string, optionally with alpha. A probe element lets the browser resolve var() chains. */
    const probe = document.createElement('span');
    probe.style.display = 'none';
    function token(name, alpha) {
        document.body.append(probe);
        probe.style.color = 'var(' + name + ')';
        const rgb = getComputedStyle(probe).color;
        probe.remove();
        if (alpha === undefined || alpha >= 1) return rgb;
        const m = rgb.match(/[\d.]+/g);
        return m ? 'rgba(' + m[0] + ',' + m[1] + ',' + m[2] + ',' + alpha + ')' : rgb;
    }

    function formatLength(metres) {
        return metres >= 1000 ? (metres / 1000).toFixed(2) + ' km' : Math.round(metres * 10) / 10 + ' m';
    }
    function formatArea(squareMetres) {
        return squareMetres >= 10000 ? (squareMetres / 10000).toFixed(2) + ' ha' : Math.round(squareMetres) + ' m²';
    }

    function create(element, config) {
        const labels = config.labels || {};
        const bbox = config.regionBbox || [100.33, 5.10, 100.60, 5.62];
        const regionExtent = ol.proj.transformExtent(bbox, 'EPSG:4326', 'EPSG:3857');
        const palette = {};
        const registry = []; // {id, label, layer, opacity input}
        const refreshers = [];

        function refreshPalette() {
            palette.lot = token('--color-neutral-500');
            palette.lotFill = token('--color-neutral-500', 0.08);
            palette.zoning = token('--status-ai');
            palette.zoningFill = token('--status-ai', 0.12);
            palette.utility = token('--status-info');
            palette.utilityFill = token('--status-info', 0.12);
            palette.accent = token('--color-accent-700');
            palette.accentFill = token('--color-accent-700', 0.18);
            palette.muted = token('--color-neutral-500');
            palette.mutedFill = token('--color-neutral-500', 0.12);
            palette.legacy = token('--status-attention');
            palette.surface = token('--color-surface');
            palette.measure = token('--color-accent-600');
            palette.measureFill = token('--color-accent-600', 0.15);
        }
        refreshPalette();

        const map = new ol.Map({
            target: element,
            view: new ol.View({ center: ol.extent.getCenter(regionExtent), zoom: 11, extent: ol.extent.buffer(regionExtent, 20000), minZoom: 9, maxZoom: 21 }),
            controls: ol.control.defaults.defaults({ rotate: false }),
        });
        map.getView().fit(regionExtent, { size: map.getSize() || [800, 600], duration: 0 });

        /* Imagery error notice: three tile failures within 10 s show it once. */
        const noticeEl = document.getElementById('osc-map-notice');
        let noticeShown = false;
        const failures = [];
        function onTileError() {
            const now = Date.now();
            failures.push(now);
            while (failures.length && now - failures[0] > 10000) failures.shift();
            if (failures.length >= 3 && !noticeShown && noticeEl) {
                noticeShown = true;
                noticeEl.querySelector('[data-map-notice-text]').textContent = labels.imageryUnavailable || '';
                noticeEl.hidden = false;
            }
        }
        if (noticeEl) noticeEl.querySelector('[data-map-notice-close]').addEventListener('click', () => { noticeEl.hidden = true; });

        /* Layer switcher: one checkbox and one opacity slider per layer. Layers are registered with a sort position,
           top to bottom, so the list matches the stacking order. */
        const listEl = document.querySelector('[data-map-layers]');
        function addLayer(def) {
            const layer = def.layer;
            layer.setZIndex(def.zIndex);
            layer.setVisible(def.visible !== false);
            layer.setOpacity(def.opacity === undefined ? 1 : def.opacity);
            map.addLayer(layer);
            const entry = { id: def.id, label: def.label, layer: layer, zIndex: def.zIndex };
            registry.push(entry);
            registry.sort((a, b) => b.zIndex - a.zIndex);
            renderSwitcher();
            return entry;
        }
        function renderSwitcher() {
            if (!listEl) return;
            listEl.replaceChildren();
            registry.forEach(entry => {
                const row = document.createElement('li');
                row.className = 'map-layer-row';
                const label = document.createElement('label');
                const check = document.createElement('input');
                check.type = 'checkbox';
                check.checked = entry.layer.getVisible();
                check.addEventListener('change', () => entry.layer.setVisible(check.checked));
                label.append(check, document.createTextNode(entry.label));
                const range = document.createElement('input');
                range.type = 'range';
                range.min = '0'; range.max = '1'; range.step = '0.05';
                range.value = String(entry.layer.getOpacity());
                range.setAttribute('aria-label', entry.label + ' — kelegapan');
                range.addEventListener('input', () => entry.layer.setOpacity(Number(range.value)));
                row.append(label, range);
                listEl.append(row);
            });
        }

        const layers = { imagery: {}, reference: {} };

        /* Base layers (bottom of the stack). */
        (config.imagery || []).forEach((src, index) => {
            const source = new ol.source.XYZ({ url: src.url, attributions: src.attribution, maxZoom: 19, crossOrigin: 'anonymous' });
            source.on('tileloaderror', onTileError);
            const layer = new ol.layer.Tile({ source: source });
            layers.imagery[src.key] = layer;
            addLayer({ id: src.key, label: labels[src.key] || src.label, layer: layer, zIndex: 10 + index, visible: src.key === 'imagery' });
        });

        /* Reference layers: cadastral lots above other layers, which sit above imagery. */
        function styleFor(id) {
            const kind = id === 'lots' ? 'lot' : (id === 'zoning' ? 'zoning' : 'utility');
            const width = id === 'lots' ? 1 : 1.5;
            const cache = {};
            return function (feature) {
                const type = feature.getGeometry().getType();
                const key = kind + type;
                if (!cache[key]) {
                    const stroke = new ol.style.Stroke({ color: palette[kind], width: width });
                    const fill = new ol.style.Fill({ color: palette[kind + 'Fill'] || palette.lotFill });
                    cache[key] = new ol.style.Style({ stroke: stroke, fill: fill, image: new ol.style.Circle({ radius: 3, stroke: stroke, fill: fill }) });
                }
                return cache[key];
            };
        }
        (config.layers || []).forEach(def => {
            const isLots = def.id === 'lots';
            const source = new ol.source.VectorTile({ format: new ol.format.MVT(), url: def.tileUrlTemplate, maxZoom: 19, minZoom: 12 });
            const style = styleFor(def.id);
            const layer = new ol.layer.VectorTile({ source: source, style: style, declutter: false, minZoom: isLots ? 15 : 13, renderMode: 'hybrid' });
            layer.set('referenceId', def.id);
            layers.reference[def.id] = layer;
            refreshers.push(() => { layer.setStyle(styleFor(def.id)); });
            addLayer({ id: def.id, label: isLots ? (labels.cadastre || def.label) : def.label, layer: layer, zIndex: isLots ? 30 : 20, visible: isLots, opacity: isLots ? 1 : 0.8 });
        });

        /* Re-read the tokens when the theme changes. */
        new MutationObserver(() => { refreshPalette(); refreshers.forEach(fn => fn()); map.render(); })
            .observe(root, { attributes: true, attributeFilter: ['data-theme'] });
        if (window.matchMedia) window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { refreshPalette(); refreshers.forEach(fn => fn()); map.render(); });

        /* Measuring. */
        const measureOut = document.querySelector('[data-map-measure]');
        const measureSource = new ol.source.Vector();
        const measureLayer = new ol.layer.Vector({
            source: measureSource, zIndex: 100,
            style: () => new ol.style.Style({
                stroke: new ol.style.Stroke({ color: palette.measure, width: 2, lineDash: [6, 4] }),
                fill: new ol.style.Fill({ color: palette.measureFill }),
                image: new ol.style.Circle({ radius: 4, fill: new ol.style.Fill({ color: palette.measure }), stroke: new ol.style.Stroke({ color: palette.surface, width: 1 }) }),
            }),
        });
        map.addLayer(measureLayer);
        let drawing = null;
        let activeTool = null;
        const toolButtons = document.querySelectorAll('[data-map-tool]');
        function measureText(geometry) {
            if (geometry instanceof ol.geom.Polygon) return formatArea(ol.sphere.getArea(geometry, { projection: 'EPSG:3857' }));
            return formatLength(ol.sphere.getLength(geometry, { projection: 'EPSG:3857' }));
        }
        function setTool(name) {
            if (drawing) { map.removeInteraction(drawing); drawing = null; }
            measureSource.clear();
            if (measureOut) measureOut.textContent = '';
            activeTool = activeTool === name ? null : name;
            toolButtons.forEach(b => { if (b.dataset.mapTool === 'distance' || b.dataset.mapTool === 'area') b.setAttribute('aria-pressed', String(b.dataset.mapTool === activeTool)); });
            if (!activeTool) return;
            drawing = new ol.interaction.Draw({ source: measureSource, type: activeTool === 'area' ? 'Polygon' : 'LineString' });
            drawing.on('drawstart', event => {
                measureSource.clear();
                event.feature.getGeometry().on('change', e => { if (measureOut) measureOut.textContent = measureText(e.target); });
            });
            drawing.on('drawend', event => { if (measureOut) measureOut.textContent = measureText(event.feature.getGeometry()); });
            map.addInteraction(drawing);
        }
        toolButtons.forEach(b => {
            if (b.dataset.mapTool === 'distance' || b.dataset.mapTool === 'area') b.addEventListener('click', () => setTool(b.dataset.mapTool));
        });

        /* Swipe: layout-image layers are clipped to the left of the divider so they compare against imagery on the right. */
        const swipeEl = document.getElementById('osc-map-swipe');
        const swipeLayers = [];
        let swipeOn = false;
        let swipeFraction = 0.5;
        function prerender(event) {
            if (!swipeOn) return;
            const ctx = event.context;
            ctx.save();
            ctx.beginPath();
            ctx.rect(0, 0, ctx.canvas.width * swipeFraction, ctx.canvas.height);
            ctx.clip();
        }
        function postrender(event) { if (swipeOn) event.context.restore(); }
        function addSwipeLayer(layer) {
            layer.on('prerender', prerender);
            layer.on('postrender', postrender);
            swipeLayers.push(layer);
        }
        function setSwipe(fraction) {
            swipeFraction = Math.min(1, Math.max(0, fraction));
            if (swipeEl) {
                swipeEl.style.left = (swipeFraction * 100) + '%';
                swipeEl.setAttribute('aria-valuenow', String(Math.round(swipeFraction * 100)));
            }
            map.render();
        }
        function toggleSwipe() {
            swipeOn = !swipeOn;
            if (swipeEl) swipeEl.hidden = !swipeOn;
            const button = document.querySelector('[data-map-tool="compare"]');
            if (button) button.setAttribute('aria-pressed', String(swipeOn));
            setSwipe(0.5);
        }
        if (swipeEl) {
            const stage = swipeEl.parentElement;
            const move = event => { const rect = stage.getBoundingClientRect(); setSwipe((event.clientX - rect.left) / rect.width); };
            swipeEl.addEventListener('pointerdown', event => {
                swipeEl.setPointerCapture(event.pointerId);
                swipeEl.addEventListener('pointermove', move);
                const stop = () => { swipeEl.removeEventListener('pointermove', move); swipeEl.removeEventListener('pointerup', stop); swipeEl.removeEventListener('pointercancel', stop); };
                swipeEl.addEventListener('pointerup', stop);
                swipeEl.addEventListener('pointercancel', stop);
            });
            swipeEl.addEventListener('keydown', event => {
                if (event.key === 'ArrowLeft') { setSwipe(swipeFraction - 0.05); event.preventDefault(); }
                if (event.key === 'ArrowRight') { setSwipe(swipeFraction + 0.05); event.preventDefault(); }
            });
        }
        const compareButton = document.querySelector('[data-map-tool="compare"]');
        if (compareButton) compareButton.addEventListener('click', toggleSwipe);

        function fit(geometryOrExtent) {
            const view = map.getView();
            const extent = Array.isArray(geometryOrExtent) ? geometryOrExtent : geometryOrExtent.getExtent();
            view.fit(extent, { size: map.getSize(), padding: [48, 48, 48, 48], maxZoom: 19, duration: reduced() ? 0 : 400 });
        }
        /* setFocus takes an ol geometry in the view projection, or a GeoJSON geometry in EPSG:4326. */
        function setFocus(geometry) {
            if (!geometry) return;
            if (geometry.type && !geometry.getExtent) geometry = new ol.format.GeoJSON().readGeometry(geometry, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' });
            fit(geometry);
        }

        return { map: map, layers: layers, setFocus: setFocus, fit: fit, addLayer: addLayer, addSwipeLayer: addSwipeLayer, palette: palette, refreshers: refreshers, regionExtent: regionExtent, token: token };
    }

    window.GisMap = { create: create, formatLength: formatLength, formatArea: formatArea };
})();
