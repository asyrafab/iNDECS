/* eOSC+ meeting map: items, earlier tablings, past decisions and positioned layout plans on top of GisMap.
   All item data comes from the map-data endpoint; the page config carries none. */
(function () {
    'use strict';

    const configEl = document.getElementById('osc-map-config');
    const element = document.getElementById('osc-map');
    if (!configEl || !element || !window.GisMap || !window.ol) return;

    const config = JSON.parse(configEl.textContent);
    const labels = config.labels;
    const gis = window.GisMap.create(element, config);
    const map = gis.map;
    const palette = gis.palette;
    const geojson = new ol.format.GeoJSON();

    /* Bottom sheet (below 768px): a non-modal dialog. Focus moves to its heading on open and back to the toggle on close. */
    const panel = document.getElementById('osc-map-panel');
    const toggle = document.querySelector('[data-map-sheet-toggle]');
    const panelTitle = document.getElementById('osc-map-panel-title');
    const wide = window.matchMedia('(min-width: 768px)');
    function syncRole() {
        if (!panel) return;
        if (wide.matches) { panel.removeAttribute('role'); panel.removeAttribute('aria-labelledby'); }
        else { panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-labelledby', 'osc-map-panel-title'); }
    }
    syncRole();
    wide.addEventListener('change', () => { syncRole(); setSheet(false, false); });
    function setSheet(open, restoreFocus) {
        if (!panel || !toggle) return;
        const wasOpen = panel.dataset.open === 'true';
        panel.dataset.open = String(open);
        toggle.setAttribute('aria-expanded', String(open));
        if (wide.matches) return;
        if (open && panelTitle) { void panel.offsetHeight; panelTitle.focus({ preventScroll: true }); }
        else if (!open && wasOpen && restoreFocus !== false) toggle.focus();
    }
    if (toggle && panel) {
        toggle.addEventListener('click', () => setSheet(panel.dataset.open !== 'true'));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && panel.dataset.open === 'true' && !wide.matches) setSheet(false);
        });
    }

    /* Styles, cached per palette refresh. */
    let styleCache = {};
    gis.refreshers.push(() => { styleCache = {}; });
    function style(feature) {
        const kind = feature.get('kind');
        const selected = feature.get('selected') === true;
        const muted = kind === 'item' && Boolean(feature.get('status_label')); // not yet accepted into the agenda
        const type = feature.getGeometry().getType();
        const key = kind + type + selected + muted;
        if (styleCache[key]) return styleCache[key];
        let s;
        if (kind === 'legacy') {
            s = new ol.style.Style({ image: new ol.style.Circle({ radius: 4, fill: new ol.style.Fill({ color: palette.legacy }), stroke: new ol.style.Stroke({ color: palette.surface, width: 1 }) }) });
        } else if (kind === 'earlier') {
            s = new ol.style.Style({ stroke: new ol.style.Stroke({ color: palette.muted, width: 2, lineDash: [6, 4] }), fill: new ol.style.Fill({ color: palette.mutedFill }), image: new ol.style.Circle({ radius: 5, stroke: new ol.style.Stroke({ color: palette.muted, width: 2 }), fill: new ol.style.Fill({ color: palette.mutedFill }) }) });
        } else {
            const width = selected ? 4 : 2.5;
            const color = muted ? palette.muted : palette.accent;
            const fill = muted ? palette.mutedFill : palette.accentFill;
            s = new ol.style.Style({ stroke: new ol.style.Stroke({ color: color, width: width }), fill: new ol.style.Fill({ color: fill }), image: new ol.style.Circle({ radius: 6, stroke: new ol.style.Stroke({ color: color, width: width }), fill: new ol.style.Fill({ color: fill }) }) });
        }
        styleCache[key] = s;
        return s;
    }

    const itemSource = new ol.source.Vector();
    const pastSource = new ol.source.Vector();
    const itemLayer = new ol.layer.Vector({ source: itemSource, style: style });
    const pastLayer = new ol.layer.Vector({ source: pastSource, style: style });
    gis.addLayer({ id: 'items', label: labels.application, layer: itemLayer, zIndex: 60 });
    gis.addLayer({ id: 'past', label: labels.earlier, layer: pastLayer, zIndex: 50 });

    /* Layout plans: one "Pelan susun atur" layer that shows at most one plan, the one switched on with the
       "Lihat pelan susun atur" button of an application (agenda item or past decision). No plan shows by default,
       and an image is only fetched when its plan is switched on. */
    const layoutLayer = new ol.layer.Image();
    gis.addLayer({ id: 'layout', label: labels.layout, layer: layoutLayer, zIndex: 40 });
    gis.addSwipeLayer(layoutLayer);
    let activeLayoutKey = null;

    function layoutKey(feature) { return feature.get('kind') + ':' + feature.get('id'); }
    function hasLayout(feature) {
        const corners = feature && feature.get('layout_corners');
        return Boolean(feature && feature.get('layout_url') && Array.isArray(corners) && corners.length === 4);
    }

    function layoutSource(url, corners, onError) {
        const image = new Image();
        const points = corners.map(c => ol.proj.fromLonLat(c)); // top-left, top-right, bottom-right, bottom-left
        const source = new ol.source.ImageCanvas({
            canvasFunction: function (canvasExtent, resolution, pixelRatio, size) {
                const canvas = document.createElement('canvas');
                canvas.width = size[0];
                canvas.height = size[1];
                if (!image.getAttribute('src')) { image.src = url; return canvas; } // first render: start loading
                if (!image.complete || !image.naturalWidth) return canvas;
                const ctx = canvas.getContext('2d');
                const scale = resolution / pixelRatio;
                const px = p => [(p[0] - canvasExtent[0]) / scale, (canvasExtent[3] - p[1]) / scale];
                const tl = px(points[0]);
                const tr = px(points[1]);
                const bl = px(points[3]);
                const w = image.naturalWidth;
                const h = image.naturalHeight;
                ctx.setTransform((tr[0] - tl[0]) / w, (tr[1] - tl[1]) / w, (bl[0] - tl[0]) / h, (bl[1] - tl[1]) / h, tl[0], tl[1]);
                ctx.drawImage(image, 0, 0);
                return canvas;
            },
            ratio: 1,
        });
        image.addEventListener('load', () => source.changed());
        image.addEventListener('error', onError);
        return { source: source, extent: ol.extent.boundingExtent(points) };
    }

    /* Show the plan of `feature`, or none when feature is null. Switching on a plan replaces the previous one. */
    function showLayout(feature) {
        if (!feature || !hasLayout(feature)) {
            activeLayoutKey = null;
            layoutLayer.setSource(null);
            updateLayoutButtons();
            return;
        }
        const key = layoutKey(feature);
        const plan = layoutSource(feature.get('layout_url'), feature.get('layout_corners'), () => {
            if (activeLayoutKey !== key) return;
            console.warn('Pelan susun atur tidak dapat dimuatkan.');
            showLayout(null);
        });
        activeLayoutKey = key;
        layoutLayer.setExtent(plan.extent);
        layoutLayer.setSource(plan.source);
        layoutLayer.setVisible(true);
        // Bring the plan into view: zoom to it unless it is already fully visible at a useful size.
        const viewExtent = map.getView().calculateExtent(map.getSize());
        const tooSmall = ol.extent.getWidth(plan.extent) < ol.extent.getWidth(viewExtent) * 0.2;
        // Instant fit: an animated fit is cancelled by the popup's auto-pan.
        if (tooSmall || !ol.extent.containsExtent(viewExtent, plan.extent)) {
            map.getView().cancelAnimations();
            map.getView().fit(plan.extent, { size: map.getSize(), padding: [48, 48, 48, 48], maxZoom: 19 });
        }
        updateLayoutButtons();
    }
    function toggleLayout(feature) {
        showLayout(feature && activeLayoutKey === layoutKey(feature) ? null : feature);
    }
    function layoutButton(feature) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-secondary button-small map-layout-toggle';
        button.dataset.layoutKey = layoutKey(feature);
        button.addEventListener('click', event => { event.stopPropagation(); toggleLayout(feature); });
        setLayoutButton(button);
        return button;
    }
    function setLayoutButton(button) {
        const on = button.dataset.layoutKey === activeLayoutKey;
        button.setAttribute('aria-pressed', String(on));
        button.textContent = on ? labels.hideLayout : labels.showLayout;
    }
    function updateLayoutButtons() {
        document.querySelectorAll('.map-layout-toggle').forEach(setLayoutButton);
    }

    /* Popup, built with textContent only: titles are user-entered. */
    const popupEl = document.getElementById('osc-map-popup');
    popupEl.hidden = false;
    const popupBody = popupEl.querySelector('[data-map-popup-body]');
    const overlay = new ol.Overlay({ element: popupEl, positioning: 'bottom-left', offset: [0, -12], autoPan: { animation: { duration: 0 } } });
    map.addOverlay(overlay);
    popupEl.style.display = 'none';
    function closePopup() { overlay.setPosition(undefined); popupEl.style.display = 'none'; }
    popupEl.querySelector('[data-map-popup-close]').addEventListener('click', closePopup);

    function line(text, tag) {
        const el = document.createElement(tag || 'p');
        el.textContent = text;
        return el;
    }
    function link(href, text, newTab) {
        const a = document.createElement('a');
        a.className = 'text-link';
        a.href = href;
        a.textContent = text;
        if (newTab) { a.target = '_blank'; a.rel = 'noopener'; }
        return a;
    }
    function statusBadge(text) {
        const badge = line(text, 'span');
        badge.className = 'status-badge status-pending';
        return badge;
    }
    /* Zone class from the whitelisted zoning attributes (config gis.layers.zoning.properties): main class, then the
       detailed class with its code. */
    function zoneLines(props) {
        const lines = [];
        if (props.gunatanah1) lines.push('Zon guna tanah: ' + props.gunatanah1);
        const detail = [props.gunatanah3, props.kod_gtn ? '(' + props.kod_gtn + ')' : ''].filter(Boolean).join(' ');
        if (detail) lines.push(detail);
        return lines;
    }
    function showPopup(coordinate, feature, lotProps, zoneProps) {
        popupBody.replaceChildren();
        if (feature) {
            const p = feature.getProperties();
            if (p.kind === 'item') {
                popupBody.append(...itemLines(feature).map(el => el.tagName === 'SPAN' ? line(el.textContent) : el));
                if (p.status_label) popupBody.append(statusBadge(p.status_label));
            } else {
                popupBody.append(line(p.title || '', 'strong'));
                if (p.reference || p.rujukan) popupBody.append(line(p.reference || p.rujukan));
            }
            if (p.purpose) popupBody.append(line(p.purpose));
            if (p.kind === 'legacy') {
                if (p.decision) popupBody.append(line(p.decision));
                if (p.year) popupBody.append(line(String(p.year)));
            }
        } else {
            if (lotProps) {
                popupBody.append(line('Lot ' + (lotProps.lot || ''), 'strong'));
                if (lotProps.mukim) popupBody.append(line(lotProps.mukim));
            }
            if (zoneProps) zoneLines(zoneProps).forEach(text => popupBody.append(line(text)));
        }
        if (hasLayout(feature)) popupBody.append(layoutButton(feature));
        const links = document.createElement('div');
        links.className = 'map-popup-links';
        if (feature && feature.get('paper_url')) links.append(link(feature.get('paper_url'), 'Kertas kerja', true));
        const [lon, lat] = ol.proj.toLonLat(coordinate);
        links.append(link('https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=' + lat.toFixed(6) + ',' + lon.toFixed(6), labels.streetView, true));
        popupBody.append(links);
        popupEl.style.display = '';
        overlay.setPosition(coordinate);
    }

    /* Item list in the panel. */
    const listEl = document.querySelector('[data-map-items]');
    const unmappedEl = document.querySelector('[data-map-unmapped]');
    const byId = {};
    let selectedId = null;
    function select(id, zoom) {
        selectedId = id;
        itemSource.getFeatures().forEach(f => { f.set('selected', f.get('id') === id); });
        listEl.querySelectorAll('.map-item-button').forEach(b => {
            if (b.dataset.id === id) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
        });
        const feature = byId[id];
        if (feature && zoom) {
            gis.fit(feature.getGeometry());
            const extent = feature.getGeometry().getExtent();
            showPopup(ol.extent.getCenter(extent), feature);
            setSheet(false);
        }
    }
    /* Item heading lines: "[type] | [paper no.]", the title, then the developer. */
    function itemLines(f) {
        return [
            line([f.get('type_label'), f.get('reference')].filter(Boolean).join(' | '), 'span'),
            line(f.get('title') || '', 'strong'),
            f.get('developer') ? line(f.get('developer'), 'span') : null,
        ].filter(Boolean);
    }
    function renderList(features, unmapped) {
        listEl.replaceChildren();
        const ordered = features.slice().sort((a, b) => (a.get('position') || Infinity) - (b.get('position') || Infinity));
        ordered.forEach(f => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'map-item-button';
            button.dataset.id = f.get('id');
            const bil = line(f.get('position') ? String(f.get('position')) : '–', 'span');
            bil.className = 'map-item-bil';
            bil.setAttribute('aria-label', 'Bil. ' + (f.get('position') || '–'));
            const body = document.createElement('span');
            body.className = 'map-item-body';
            body.append(...itemLines(f));
            if (f.get('status_label')) { button.classList.add('map-item-muted'); body.append(statusBadge(f.get('status_label'))); }
            button.append(bil, body);
            button.addEventListener('click', () => select(f.get('id'), true));
            li.append(button);
            listEl.append(li);
        });
        if (!features.length) listEl.append(Object.assign(document.createElement('li'), { className: 'muted', textContent: '—' }));
        if (unmapped.length) {
            unmappedEl.hidden = false;
            unmappedEl.replaceChildren(line('Belum ada lokasi pada peta', 'strong'));
            const ul = document.createElement('ul');
            ul.className = 'map-item-list';
            unmapped.forEach(u => ul.append(Object.assign(document.createElement('li'), { className: 'muted', textContent: [u.title, u.status_label].filter(Boolean).join(' · ') })));
            unmappedEl.append(ul);
        }
    }

    function zoomToSite() {
        const feature = selectedId ? byId[selectedId] : null;
        if (feature) { gis.fit(feature.getGeometry()); return; }
        const extent = ol.extent.createEmpty();
        itemSource.getFeatures().forEach(f => ol.extent.extend(extent, f.getGeometry().getExtent()));
        gis.fit(ol.extent.isEmpty(extent) ? gis.regionExtent : extent);
    }
    const zoomButton = document.querySelector('[data-map-tool="zoom"]');
    if (zoomButton) zoomButton.addEventListener('click', zoomToSite);

    /* Click: an item, an earlier or legacy feature, or a cadastral lot; always offers Street View for the point. */
    map.on('singleclick', event => {
        if (map.getInteractions().getArray().some(i => i instanceof ol.interaction.Draw)) return;
        let feature = null;
        let lotProps = null;
        let zoneProps = null;
        map.forEachFeatureAtPixel(event.pixel, (f, layer) => {
            if (layer === itemLayer || layer === pastLayer) { if (!feature) feature = f; return false; }
            if (!lotProps && layer && layer.get('referenceId') === 'lots') lotProps = f.getProperties();
            if (!zoneProps && layer && layer.get('referenceId') === 'zoning') zoneProps = f.getProperties();
            return undefined;
        }, { hitTolerance: 4 });
        if (feature && feature.get('kind') === 'item') select(feature.get('id'), false);
        showPopup(event.coordinate, feature, lotProps, zoneProps);
    });
    map.on('pointermove', event => {
        if (event.dragging) return;
        const hit = map.hasFeatureAtPixel(event.pixel, { layerFilter: l => l === itemLayer || l === pastLayer, hitTolerance: 4 });
        element.style.cursor = hit ? 'pointer' : '';
    });

    /* Load map-data. */
    fetch(config.mapDataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(response => { if (!response.ok) throw new Error(String(response.status)); return response.json(); })
        .then(collection => {
            const read = geojson.readFeatures(collection, { dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' });
            const items = [];
            read.forEach(f => {
                const kind = f.get('kind');
                if (kind === 'item') {
                    itemSource.addFeature(f);
                    items.push(f);
                    byId[f.get('id')] = f;
                } else {
                    pastSource.addFeature(f);
                }
            });
            renderList(items, (collection.properties && collection.properties.unmapped) || []);
            if (config.focusItemId && byId[config.focusItemId]) select(config.focusItemId, true);
            else zoomToSite();
        })
        .catch(() => {
            listEl.replaceChildren(Object.assign(document.createElement('li'), { className: 'muted', textContent: 'Data peta tidak dapat dimuatkan.' }));
        });
})();
