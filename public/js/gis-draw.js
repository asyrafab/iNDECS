/* Boundary drawing field: draw one polygon, modify it, snap to cadastral lots, write GeoJSON (EPSG:4326) into a hidden input.
   Builds on GisMap.create; without JavaScript the form submits without a boundary. Colours come from tokens at runtime. */
(function () {
    'use strict';
    if (!window.GisMap || !window.ol) return;

    const SNAP_LIMIT = 3000;

    function init(root) {
        const mapEl = root.querySelector('[data-boundary-map]');
        const input = root.querySelector('[data-boundary-input]');
        const configEl = root.querySelector('[data-boundary-config]');
        const toolbar = root.querySelector('.boundary-field-toolbar');
        const stage = root.querySelector('[data-boundary-stage]');
        const areaOut = root.querySelector('[data-boundary-area]');
        if (!mapEl || !input || !configEl) return;

        toolbar.hidden = false;
        stage.hidden = false;
        const gis = window.GisMap.create(mapEl, JSON.parse(configEl.textContent));
        const map = gis.map;
        const palette = gis.palette;
        const format = new ol.format.GeoJSON();
        const projection = { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' };

        let styleCache = null;
        gis.refreshers.push(() => { styleCache = null; });
        function style() {
            if (!styleCache) {
                styleCache = new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: palette.measure, width: 2.5 }),
                    fill: new ol.style.Fill({ color: palette.measureFill }),
                    image: new ol.style.Circle({ radius: 5, fill: new ol.style.Fill({ color: palette.measure }), stroke: new ol.style.Stroke({ color: palette.surface, width: 1.5 }) }),
                });
            }
            return styleCache;
        }

        const source = new ol.source.Vector();
        const layer = new ol.layer.Vector({ source: source, zIndex: 100, style: style });
        map.addLayer(layer);

        /* Existing boundary (edit on the application page, or old input after a failed submit). */
        if (input.value) {
            try {
                const geometry = format.readGeometry(JSON.parse(input.value), projection);
                source.addFeature(new ol.Feature(geometry));
                gis.fit(geometry);
            } catch (e) { input.value = ''; }
        }

        function areaOf(geometry) {
            const type = geometry.getType();
            return type === 'Polygon' || type === 'MultiPolygon' ? ol.sphere.getArea(geometry, { projection: 'EPSG:3857' }) : 0;
        }
        function showArea(geometry) {
            areaOut.textContent = geometry && areaOf(geometry) > 0 ? 'Keluasan: ' + window.GisMap.formatArea(areaOf(geometry)) : '';
        }
        function sync() {
            const feature = source.getFeatures()[0];
            if (!feature) { input.value = ''; showArea(null); draw.setActive(true); return; }
            const geometry = feature.getGeometry();
            input.value = format.writeGeometry(geometry, Object.assign({ decimals: 7 }, projection));
            showArea(geometry);
            draw.setActive(false);
        }

        const modify = new ol.interaction.Modify({ source: source });
        const draw = new ol.interaction.Draw({ source: source, type: 'Polygon' });
        draw.on('drawstart', event => {
            event.feature.getGeometry().on('change', e => showArea(e.target));
        });
        map.addInteraction(modify);
        map.addInteraction(draw);

        /* Snap targets: the lots tile layer's rendered features (via its renderer, which holds them in the view
           projection), re-collected when the map settles and turned into plain geometries so Snap can read them. */
        const snapSource = new ol.source.Vector();
        const snap = new ol.interaction.Snap({ source: snapSource, pixelTolerance: 12 });
        map.addInteraction(snap);
        const lots = gis.layers.reference.lots;
        let snapTimer = null;
        function toGeometry(rendered) {
            const flat = rendered.getFlatCoordinates();
            switch (rendered.getType()) {
                case 'Polygon': return new ol.geom.Polygon(flat, 'XY', rendered.getEnds());
                case 'MultiPolygon': return new ol.geom.MultiPolygon(flat, 'XY', rendered.getEndss());
                case 'LineString': return new ol.geom.LineString(flat, 'XY');
                case 'MultiLineString': return new ol.geom.MultiLineString(flat, 'XY', rendered.getEnds());
                default: return null;
            }
        }
        function collectLots() {
            if (!lots || !lots.getVisible() || map.getView().getZoom() < 15) { snapSource.clear(true); return; }
            const extent = map.getView().calculateExtent(map.getSize());
            const features = lots.getRenderer().getFeaturesInExtent(extent);
            const collected = [];
            const seen = new Set();
            for (const rendered of features) {
                if (collected.length >= SNAP_LIMIT) break;
                const geometry = toGeometry(rendered);
                if (!geometry) continue;
                const key = String(rendered.getId ? rendered.getId() : '') + geometry.getExtent().join(',');
                if (seen.has(key)) continue;
                seen.add(key);
                collected.push(new ol.Feature(geometry));
            }
            snapSource.clear(true);
            snapSource.addFeatures(collected);
        }
        function scheduleCollect() { clearTimeout(snapTimer); snapTimer = setTimeout(collectLots, 150); }
        map.on('moveend', scheduleCollect);
        map.on('rendercomplete', scheduleCollect);

        source.on(['addfeature', 'changefeature', 'removefeature'], sync);
        sync();

        root.querySelector('[data-boundary-clear]').addEventListener('click', () => { source.clear(); sync(); });
        root.querySelector('[data-boundary-done]').addEventListener('click', () => {
            draw.finishDrawing();
            sync();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        root.boundaryField = { map: map, source: source, snapSource: snapSource, draw: draw, modify: modify, snap: snap, collectLots: collectLots };
    }

    /* Control-points mode: place a scanned plan from three matching points. The user clicks a point on the image, then the
       same point on the map, three times. A live preview drapes the image over the map using the affine fit through the
       three pairs (in EPSG:3857, pixel-centre convention like a world file); the server recomputes the corners from the
       submitted points and is the authority. Hidden inputs control_points[i][pixel|map][0|1] carry the pairs. */
    function initControlPoints(root) {
        const mapEl = root.querySelector('[data-layout-map]');
        const configEl = root.querySelector('[data-layout-config]');
        const fileInput = root.querySelector('[data-layout-file]');
        const imageStage = root.querySelector('[data-layout-stage]');
        const img = root.querySelector('[data-layout-image]');
        const pointsEl = root.querySelector('[data-layout-points]');
        const statusEl = root.querySelector('[data-layout-status]');
        const saveButton = root.querySelector('[data-layout-save]');
        const resetButton = root.querySelector('[data-layout-reset]');
        if (!mapEl || !configEl || !fileInput || !img || !pointsEl) return;

        root.querySelectorAll('[data-layout-needs-js]').forEach(el => { el.hidden = false; });
        const gis = window.GisMap.create(mapEl, JSON.parse(configEl.textContent));
        const map = gis.map;
        const palette = gis.palette;
        const messages = JSON.parse(configEl.textContent).messages || {};

        let pairs = [];
        let objectUrl = null;
        let loaded = false;
        let styleCache = null;
        gis.refreshers.push(() => { styleCache = null; });

        const markerSource = new ol.source.Vector();
        map.addLayer(new ol.layer.Vector({
            source: markerSource, zIndex: 120,
            style: feature => {
                if (!styleCache) styleCache = {};
                const n = String(feature.get('n'));
                if (!styleCache[n]) {
                    styleCache[n] = new ol.style.Style({
                        image: new ol.style.Circle({ radius: 11, fill: new ol.style.Fill({ color: palette.measure }), stroke: new ol.style.Stroke({ color: palette.surface, width: 2 }) }),
                        text: new ol.style.Text({ text: n, fill: new ol.style.Fill({ color: palette.surface }), font: '600 12px sans-serif' }),
                    });
                }
                return styleCache[n];
            },
        }));

        /* The draped preview: the same canvas transform osc-map.js uses for positioned plans. */
        const previewImage = new Image();
        let previewPoints = null;
        const previewSource = new ol.source.ImageCanvas({
            canvasFunction: function (extent, resolution, pixelRatio, size) {
                const canvas = document.createElement('canvas');
                canvas.width = size[0];
                canvas.height = size[1];
                if (!previewPoints || !previewImage.complete || !previewImage.naturalWidth) return canvas;
                const ctx = canvas.getContext('2d');
                const scale = resolution / pixelRatio;
                const px = p => [(p[0] - extent[0]) / scale, (extent[3] - p[1]) / scale];
                const tl = px(previewPoints[0]);
                const tr = px(previewPoints[1]);
                const bl = px(previewPoints[3]);
                const w = previewImage.naturalWidth;
                const h = previewImage.naturalHeight;
                ctx.setTransform((tr[0] - tl[0]) / w, (tr[1] - tl[1]) / w, (bl[0] - tl[0]) / h, (bl[1] - tl[1]) / h, tl[0], tl[1]);
                ctx.drawImage(previewImage, 0, 0);
                return canvas;
            },
            ratio: 1,
        });
        previewImage.addEventListener('load', () => previewSource.changed());
        map.addLayer(new ol.layer.Image({ source: previewSource, zIndex: 110, opacity: 0.65 }));

        function say(text) { if (statusEl) statusEl.textContent = text; }

        /* Affine fit through three pairs by Cramer's rule; null when the pixel triangle is under 0.5 % of the image area
           (nearly collinear), matching AffinePlacement on the server. */
        function corners() {
            const p = pairs.map(pair => pair.pixel);
            const m = pairs.map(pair => ol.proj.fromLonLat(pair.map));
            const det = p[0][0] * (p[1][1] - p[2][1]) - p[0][1] * (p[1][0] - p[2][0]) + (p[1][0] * p[2][1] - p[2][0] * p[1][1]);
            if (Math.abs(det) / 2 < 0.005 * Math.max(1, img.naturalWidth) * Math.max(1, img.naturalHeight)) return null;
            const solve = (v0, v1, v2) => [
                (v0 * (p[1][1] - p[2][1]) - p[0][1] * (v1 - v2) + (v1 * p[2][1] - v2 * p[1][1])) / det,
                (p[0][0] * (v1 - v2) - v0 * (p[1][0] - p[2][0]) + (p[1][0] * v2 - p[2][0] * v1)) / det,
                (p[0][0] * (p[1][1] * v2 - p[2][1] * v1) - p[0][1] * (p[1][0] * v2 - p[2][0] * v1) + v0 * (p[1][0] * p[2][1] - p[2][0] * p[1][1])) / det,
            ];
            const fx = solve(m[0][0], m[1][0], m[2][0]);
            const fy = solve(m[0][1], m[1][1], m[2][1]);
            const w = img.naturalWidth;
            const h = img.naturalHeight;
            return [[-0.5, -0.5], [w - 0.5, -0.5], [w - 0.5, h - 0.5], [-0.5, h - 0.5]]
                .map(c => [fx[0] * c[0] + fx[1] * c[1] + fx[2], fy[0] * c[0] + fy[1] * c[1] + fy[2]]);
        }

        function marker(n, pixel) {
            const el = document.createElement('span');
            el.className = 'layout-marker';
            el.textContent = String(n);
            el.style.left = ((pixel[0] + 0.5) / img.naturalWidth * 100) + '%';
            el.style.top = ((pixel[1] + 0.5) / img.naturalHeight * 100) + '%';
            return el;
        }

        function render() {
            imageStage.querySelectorAll('.layout-marker').forEach(el => el.remove());
            markerSource.clear();
            pointsEl.replaceChildren();
            pairs.forEach((pair, i) => {
                if (pair.pixel) imageStage.querySelector('[data-layout-wrap]').append(marker(i + 1, pair.pixel));
                if (pair.map) {
                    const feature = new ol.Feature(new ol.geom.Point(ol.proj.fromLonLat(pair.map)));
                    feature.set('n', i + 1);
                    markerSource.addFeature(feature);
                }
                if (pair.pixel && pair.map) {
                    [['pixel', 0, pair.pixel[0]], ['pixel', 1, pair.pixel[1]], ['map', 0, pair.map[0]], ['map', 1, pair.map[1]]].forEach(([kind, axis, value]) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'control_points[' + i + '][' + kind + '][' + axis + ']';
                        input.value = String(Math.round(value * 1e7) / 1e7);
                        pointsEl.append(input);
                    });
                }
            });
            const done = pairs.length === 3 && pairs[2].map !== null;
            previewPoints = null;
            if (done) {
                const result = corners();
                if (result) previewPoints = result;
                else { say(messages.invalid || ''); pairs = []; render(); say(messages.invalid || ''); return; }
                say(messages.ready || '');
            } else if (!loaded) {
                say(messages.choose || '');
            } else {
                const current = pairs[pairs.length - 1];
                const n = current && !current.map ? pairs.length : pairs.length + 1;
                say(current && !current.map ? (messages.pickMap || '').replace(':n', String(n)) : (messages.pickImage || '').replace(':n', String(n)));
            }
            saveButton.disabled = !(done && previewPoints);
            previewSource.changed();
        }

        function reset() { pairs = []; render(); }

        fileInput.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            loaded = false;
            const file = fileInput.files[0];
            if (!file) { img.removeAttribute('src'); img.hidden = true; previewImage.removeAttribute('src'); reset(); return; }
            objectUrl = URL.createObjectURL(file);
            img.hidden = false;
            img.src = objectUrl;
            previewImage.src = objectUrl;
        });
        img.addEventListener('load', () => { loaded = true; reset(); });
        img.addEventListener('error', () => { loaded = false; say(messages.unreadable || ''); });

        img.addEventListener('click', event => {
            if (!loaded || pairs.length > 3) return;
            const last = pairs[pairs.length - 1];
            if (pairs.length === 3 && last.map) return;
            const rect = img.getBoundingClientRect();
            const clamp = (v, max) => Math.min(max - 0.5, Math.max(-0.5, v));
            const pixel = [
                clamp((event.clientX - rect.left) / rect.width * img.naturalWidth - 0.5, img.naturalWidth),
                clamp((event.clientY - rect.top) / rect.height * img.naturalHeight - 0.5, img.naturalHeight),
            ];
            if (last && !last.map) last.pixel = pixel; // re-pick the pending image point
            else pairs.push({ pixel: pixel, map: null });
            render();
        });
        map.on('singleclick', event => {
            const last = pairs[pairs.length - 1];
            if (!loaded || !last || last.map) { if (loaded) say(messages.pickImageFirst || ''); return; }
            const lonLat = ol.proj.toLonLat(event.coordinate);
            last.map = lonLat;
            render();
        });
        resetButton.addEventListener('click', reset);
        render();

        root.layoutEditor = { map: map, pairs: () => pairs, reset: reset };
    }

    function start() {
        document.querySelectorAll('[data-boundary-field]').forEach(init);
        document.querySelectorAll('[data-layout-editor]').forEach(initControlPoints);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
