@extends('layouts.app')
@section('title', 'Tambah dokumen perpustakaan')
@section('content')
<div class="page-heading"><a class="back-link" href="{{ route('library.index') }}"><x-icon name="arrow-left" />Kembali ke perpustakaan</a><span class="eyebrow">Perpustakaan jabatan</span><h1>Tambah dokumen</h1><p>Fail PDF sahaja, sehingga 50 MB. Dokumen awam boleh dimuat turun oleh pemohon dalam Portal Awam bagi jenis permohonan yang dipilih.</p></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div><strong>Sila semak maklumat.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<form class="application-form" method="POST" action="{{ route('library.store') }}" enctype="multipart/form-data">
    @csrf
    <section class="form-card">
        <div class="form-section-heading"><div><h2>Maklumat dokumen</h2></div><span class="required-note">* Wajib diisi</span></div>
        <div class="form-grid">
            <label class="field field-span-2"><span>Tajuk *</span><input name="title" value="{{ old('title') }}" maxlength="255" required @error('title') aria-invalid="true" @enderror></label>
            <label class="field"><span>Kategori *</span><select name="category" required>@foreach (\App\Models\LibraryDocument::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field"><span>Paparan *</span><select name="visibility" required>@foreach (\App\Models\LibraryDocument::VISIBILITIES as $value => $label)<option value="{{ $value }}" @selected(old('visibility', 'internal') === $value)>{{ $label }}</option>@endforeach</select><small>Awam: boleh dimuat turun oleh pemohon. Dalaman: pegawai, AIRS dan AMIRA sahaja.</small></label>
            <label class="field field-span-2"><span>Keterangan</span><textarea name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea></label>
            <fieldset class="field field-span-2 library-types"><legend>Jenis permohonan berkaitan</legend>
                @foreach ($types as $type)<label class="checkbox-field"><input type="checkbox" name="application_type_ids[]" value="{{ $type->id }}" @checked(in_array($type->id, old('application_type_ids', []), true))><span>{{ $type->id }} · {{ $type->name }}</span></label>@endforeach
            </fieldset>
            <label class="field field-span-2"><span>Fail dokumen (PDF) *</span><input type="file" name="file" accept="application/pdf,.pdf" required @error('file') aria-invalid="true" @enderror></label>
        </div>
    </section>
    <div class="form-actions"><div><button class="button button-primary" type="submit"><x-icon name="check" />Simpan dokumen</button><a class="button button-secondary" href="{{ route('library.index') }}">Batal</a></div></div>
</form>
@endsection
