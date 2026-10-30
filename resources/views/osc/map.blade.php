@extends('layouts.app')
@section('title', 'Peta · Mesyuarat '.$meeting->number)
@section('content')
<link rel="stylesheet" href="{{ asset('vendor/openlayers/ol.css') }}">
<div class="map-page">
    <div class="map-heading">
        <a href="{{ route('osc.meetings.show', $meeting) }}" class="back-link"><x-icon name="arrow-left" /> Mesyuarat OSC {{ $meeting->number }}</a>
        <div class="map-heading-row"><h1>Peta</h1>
    <button type="button" class="button button-primary map-sheet-toggle" data-map-sheet-toggle aria-expanded="false" aria-controls="osc-map-panel"><x-icon name="panel-right" />Lapisan &amp; senarai</button></div>
    </div>
    <div class="map-layout" id="osc-map-root">
        <div class="map-stage">
            <div class="map-canvas" id="osc-map" role="application" aria-label="Peta"></div>
            <div class="map-notice" id="osc-map-notice" role="status" hidden>
                <span data-map-notice-text></span>
                <button type="button" class="icon-button" data-map-notice-close aria-label="Tutup"><x-icon name="x" /></button>
            </div>
            <div class="map-tools" role="toolbar" aria-label="Alat peta" aria-orientation="vertical">
                <button type="button" class="icon-button" data-map-tool="distance" aria-pressed="false" aria-label="Ukur jarak" title="Ukur jarak"><x-icon name="ruler" /></button>
                <button type="button" class="icon-button" data-map-tool="area" aria-pressed="false" aria-label="Ukur keluasan" title="Ukur keluasan"><x-icon name="square-dashed" /></button>
                <button type="button" class="icon-button" data-map-tool="compare" aria-pressed="false" aria-label="Bandingkan" title="Bandingkan"><x-icon name="columns-2" /></button>
                <button type="button" class="icon-button" data-map-tool="zoom" aria-label="Zum ke tapak" title="Zum ke tapak"><x-icon name="crosshair" /></button>
            </div>
            <p class="map-measure-output" data-map-measure aria-live="polite"></p>
            <div class="map-layers">
                <div class="map-layers-panel" id="osc-map-layers" role="region" aria-labelledby="osc-map-layers-title" data-open="false" inert>
                    <h2 class="map-panel-title" id="osc-map-layers-title">Lapisan</h2>
                    <ul class="map-layer-list" data-map-layers></ul>
                </div>
                <button type="button" class="icon-button map-layers-toggle" data-map-layers-toggle aria-expanded="false" aria-controls="osc-map-layers" aria-label="Lapisan" title="Lapisan"><x-icon name="layers" /></button>
            </div>
            <div class="map-swipe" id="osc-map-swipe" role="slider" tabindex="0" aria-label="Bandingkan" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" hidden><span></span></div>
            <div class="map-popup" id="osc-map-popup" hidden>
                <button type="button" class="icon-button map-popup-close" data-map-popup-close aria-label="Tutup"><x-icon name="x" /></button>
                <div class="map-popup-body" data-map-popup-body></div>
            </div>
        </div>
        <aside class="map-panel map-sheet" id="osc-map-panel" role="dialog" aria-labelledby="osc-map-panel-title">
            <section class="map-panel-section">
                <h2 class="map-panel-title" id="osc-map-panel-title" tabindex="-1">Permohonan</h2>
                <ol class="map-item-list" data-map-items><li class="muted">Memuatkan…</li></ol>
                <div data-map-unmapped hidden></div>
            </section>
        </aside>
    </div>
</div>
<script type="application/json" id="osc-map-config">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script src="{{ asset('vendor/openlayers/ol.js') }}"></script>
<script src="{{ asset('js/gis-map.js') }}"></script>
<script src="{{ asset('js/osc-map.js') }}"></script>
@endsection
