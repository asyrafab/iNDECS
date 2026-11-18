@extends('layouts.app')
@section('title', 'Katalog dokumen '.$type)
@section('content')
<div class="page-heading"><a class="back-link" href="{{ route('landskap.index') }}">Kembali ke senarai</a><span class="eyebrow">Jabatan Landskap · {{ $type }}</span><h1>Katalog dokumen</h1><p>Rekodkan senarai semak yang disahkan jabatan beserta rujukan sumbernya. Perubahan digunakan untuk senarai semak baharu.</p></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('landskap.document-catalogue.publish', $type) }}" class="application-form" data-confirm="Sahkan katalog dokumen ini berdasarkan rujukan jabatan yang dimasukkan?">
@csrf<input type="hidden" name="version" value="{{ old('version', $catalogue?->version ?? 0) }}">
<section class="form-card"><div class="form-section-heading"><div><h2>Sumber dan pengesahan</h2><p>{{ $catalogue ? 'Versi semasa '.$catalogue->version : 'Tiada katalog disahkan' }}</p></div></div><label class="field"><span>Rujukan senarai semak jabatan *</span><input name="source_reference" value="{{ old('source_reference', $catalogue?->source_reference) }}" maxlength="500" required placeholder="Tajuk dokumen / nombor rujukan / tarikh pengesahan"></label></section>
<section class="form-card"><div class="repeater-heading"><h2>Keperluan dokumen</h2><button type="button" class="button button-secondary" data-repeater-add="items">Tambah dokumen</button></div><p>Gunakan kod ringkas huruf kecil dan garis bawah, contohnya pelan_landskap. Fail PDF, JPG dan PNG sehingga 20 MB dibenarkan. Tetapkan syarat jika dokumen hanya diperlukan untuk projek tertentu.</p>
<div data-repeater="items"><div data-repeater-items>@foreach (old('items', $items ?: [['code' => '', 'name' => '', 'is_required' => true]]) as $index => $item)@include('bangunan.partials.catalogue-item', ['index' => $index, 'item' => $item])@endforeach</div>
<template data-repeater-template>@include('bangunan.partials.catalogue-item', ['index' => '__INDEX__', 'item' => []])</template></div>
</section><div class="form-actions"><a class="button button-ghost" href="{{ route('landskap.index') }}">Batal</a><button type="submit" class="button button-primary">Sahkan katalog versi baharu</button></div>
</form>
@endsection
