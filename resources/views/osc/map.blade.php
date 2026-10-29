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
            <div class="map-swipe" id="osc-map-swipe" role="slider" tabindex="0" aria-label="Bandingkan" aria-orientation="horizontal" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50" hidden><span></span></div>
            <div class="map-popup" id="osc-map-popup" hidden>
                <button type="button" class="icon-button map-popup-close" data-map-popup-close aria-label="Tutup"><x-icon name="x" /></button>
                <div class="map-popup-body" data-map-popup-body></div>
            </div>
        </div>
        <aside class="map-panel map-sheet" id="osc-map-panel" aria-label="Peta">
            <section class="map-panel-section">
                <div class="map-tools" role="group" aria-label="Alat">
                    <button type="button" class="button button-secondary button-small" data-map-tool="distance" aria-pressed="false">Ukur jarak</button>
                    <button type="button" class="button button-secondary button-small" data-map-tool="area" aria-pressed="false">Ukur keluasan</button>
                    <button type="button" class="button button-secondary button-small" data-map-tool="compare" aria-pressed="false">Bandingkan</button>
                    <button type="button" class="button button-secondary button-small" data-map-tool="zoom">Zum ke tapak</button>
                </div>
                <p class="map-measure-output" data-map-measure aria-live="polite"></p>
            </section>
            <section class="map-panel-section">
                <h2 class="map-panel-title">Lapisan</h2>
                <ul class="map-layer-list" data-map-layers></ul>
            </section>
            <section class="map-panel-section">
                <h2 class="map-panel-title">Permohonan</h2>
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
