@extends('layouts.app')
@section('title', 'Kedudukan pelan · '.$item->title)
@section('content')
<link rel="stylesheet" href="{{ asset('vendor/openlayers/ol.css') }}">
<div class="record-heading"><div>
    <a href="{{ route('osc.meetings.show', $meeting) }}" class="back-link"><x-icon name="arrow-left" /> Mesyuarat OSC {{ $meeting->number }}</a>
    <h1>Kedudukan pelan susun atur</h1>
    <p>{{ $item->title }}@if ($item->reference) · {{ $item->reference }}@endif</p>
</div></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<form method="POST" action="{{ route('osc.layout.store', $item) }}" enctype="multipart/form-data" class="application-form">
    @csrf
    @if ($current)<input type="hidden" name="expected_version" value="{{ $current->row_version }}">@endif
    <section class="form-card">
        <div class="form-section-heading"><div>
            <h2>Imej pelan</h2>
            <p>Pilih imej JPG atau PNG (tidak melebihi 20 MB). Klik satu titik pada imej, kemudian titik yang sama pada peta. Ulang tiga kali dengan titik yang tidak sebaris, sebaiknya jauh antara satu sama lain.@if ($current) Menyimpan akan menggantikan kedudukan pelan sedia ada; fail lama disimpan.@endif</p>
        </div></div>
        <div class="layout-editor" data-layout-editor>
            <label class="field"><span>Fail imej pelan <b>*</b></span><input type="file" name="layout" accept="image/jpeg,image/png" required data-layout-file></label>
            <noscript><p class="muted">Kedudukan pelan memerlukan JavaScript untuk menandakan titik pada imej dan peta.</p></noscript>
            <p class="layout-editor-status" role="status" aria-live="polite" data-layout-status></p>
            <div class="layout-editor-panes">
                <div class="layout-editor-image" data-layout-stage>
                    <div class="layout-editor-wrap" data-layout-wrap><img alt="Imej pelan yang dimuat naik" hidden data-layout-image></div>
                </div>
                <div class="layout-editor-map"><div class="map-canvas" data-layout-map role="application" aria-label="Peta kedudukan pelan"></div></div>
            </div>
            <div class="form-actions">
                <button type="button" class="button button-secondary" data-layout-reset hidden data-layout-needs-js><x-icon name="x" />Ulang titik</button>
                <button type="submit" class="button button-primary" disabled data-layout-save><x-icon name="check" />Simpan kedudukan</button>
            </div>
            <div data-layout-points></div>
            <script type="application/json" data-layout-config>{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        </div>
    </section>
</form>
<script src="{{ asset('vendor/openlayers/ol.js') }}" defer></script>
<script src="{{ asset('js/gis-map.js') }}" defer></script>
<script src="{{ asset('js/gis-draw.js') }}" defer></script>
@endsection
