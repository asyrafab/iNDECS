{{-- Optional site boundary drawn on a map. Without JavaScript the hidden input stays empty and the form submits without a boundary.
     Props: $name (input name), $value (GeoJSON string or null). With $application the field saves straight to that
     application's primary site (draw or redraw) and is only shown to officers allowed to do so. --}}
@php
    $boundaryConfig = \App\Services\Gis\MapConfig::forBoundaryField();
    $forApplication = isset($application);
    if ($forApplication) {
        $name = 'boundary';
        $value = old('boundary');
        if ($value === null) {
            $primary = $application->sites()->wherePivot('is_primary', true)->first();
            $value = $primary ? \Illuminate\Support\Facades\DB::selectOne('SELECT ST_AsGeoJSON(boundary) AS geojson FROM sites WHERE id = ? AND boundary IS NOT NULL', [$primary->id])?->geojson : null;
        }
    }
@endphp
@if (! $forApplication || app(\App\Services\Gis\BoundaryService::class)->canSet(auth()->user(), $application))
@if ($forApplication)
<form method="POST" action="{{ route('gis.applications.boundary', $application) }}" class="application-form boundary-form">
    @csrf
    <input type="hidden" name="expected_version" value="{{ $application->row_version }}">
@endif
<div class="boundary-field" data-boundary-field>
    <div class="boundary-field-head">
        <h3 class="subsection-title">Sempadan tapak <span>Pilihan</span></h3>
        <p class="muted">Lukis sempadan tapak di atas peta. Zum masuk untuk melihat lot kadaster dan melekap pada sempadannya.</p>
    </div>
    <div class="boundary-field-toolbar" role="group" aria-label="Alat sempadan" hidden>
        <button type="button" class="button button-secondary button-small" data-boundary-clear><x-icon name="x" />Padam</button>
        <button type="button" class="button button-secondary button-small" data-boundary-done><x-icon name="check" />Selesai</button>
        <output class="boundary-field-area" data-boundary-area aria-live="polite"></output>
    </div>
    <div class="boundary-field-stage" data-boundary-stage hidden>
        <div class="map-canvas" data-boundary-map role="application" aria-label="Peta sempadan tapak"></div>
    </div>
    <input type="hidden" name="{{ $name }}" value="{{ $value ?? '' }}" data-boundary-input>
    <script type="application/json" data-boundary-config>{!! json_encode($boundaryConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
@if ($forApplication)
    <div class="form-actions"><button type="submit" class="button button-primary">Simpan sempadan</button></div>
</form>
@endif
@endif
@once
<link rel="stylesheet" href="{{ asset('vendor/openlayers/ol.css') }}">
<script src="{{ asset('vendor/openlayers/ol.js') }}" defer></script>
<script src="{{ asset('js/gis-map.js') }}" defer></script>
<script src="{{ asset('js/gis-draw.js') }}" defer></script>
@endonce
