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

    function start() { document.querySelectorAll('[data-boundary-field]').forEach(init); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
