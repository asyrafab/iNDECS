@extends('layouts.app')
@section('title', 'Daftar permohonan Bangunan')
@section('content')
<div class="page-heading"><a href="{{ route('bangunan.index') }}" class="back-link">Kembali ke senarai</a><span class="eyebrow">iNDECS Bangunan</span><h1>Daftar permohonan baharu</h1><p>Maklumat bertanda * wajib diisi. Nombor rujukan dijana mengikut jenis permohonan dan tahun penerimaan.</p></div>
@if ($errors->any())<div class="alert alert-error validation-summary" role="alert"><div><strong>Sila semak maklumat berikut.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<form method="POST" action="{{ route('bangunan.store') }}" class="application-form">
@csrf
<section class="form-card"><div class="form-section-heading"><div><h2>Maklumat permohonan</h2><p>PB memerlukan rujukan OSC.</p></div></div><div class="form-grid">
    <label class="field"><span>Jenis permohonan *</span><select name="application_type_id" required>@foreach ($types as $type)<option value="{{ $type->id }}" @selected(old('application_type_id') === $type->id)>{{ $type->id }} · {{ $type->name }}</option>@endforeach</select></label>
    <label class="field"><span>Tarikh penerimaan *</span><input type="date" name="submission_date" value="{{ old('submission_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></label>
    <label class="field"><span>Rujukan OSC (wajib bagi PB)</span><input name="external_reference" value="{{ old('external_reference') }}" maxlength="255"></label>
    <label class="field field-span-2"><span>Tajuk cadangan *</span><input name="application_title" value="{{ old('application_title') }}" maxlength="255" required></label>
    <label class="field"><span>Keutamaan *</span><select name="priority">@foreach (['normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Segera'] as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="field field-span-2"><span>Keterangan cadangan</span><textarea name="application_description" rows="3" maxlength="5000">{{ old('application_description') }}</textarea></label>
</div></section>
<section class="form-card"><div class="form-section-heading"><div><h2>Pemohon dan perunding</h2><p>Butiran pihak disimpan bersama permohonan pada tarikh pendaftaran.</p></div></div>
@foreach (['applicant' => 'Pemohon', 'consultant' => 'Perunding'] as $role => $label)
<h3 class="subsection-title">{{ $label }}</h3><div class="form-grid">
    <label class="field"><span>Jenis pihak</span><select name="{{ $role }}[party_type]"><option value="organisation" @selected(old($role.'.party_type', 'organisation') === 'organisation')>Organisasi</option><option value="individual" @selected(old($role.'.party_type') === 'individual')>Individu</option></select></label>
    <label class="field field-span-2"><span>Nama {{ $label }} {{ $role === 'applicant' ? '*' : '' }}</span><input name="{{ $role }}[name]" value="{{ old($role.'.name') }}" maxlength="255" @required($role === 'applicant')></label>
    <label class="field"><span>No. pendaftaran / pengenalan</span><input name="{{ $role }}[registration_number]" value="{{ old($role.'.registration_number') }}" maxlength="100"></label>
    <label class="field"><span>E-mel</span><input type="email" name="{{ $role }}[email]" value="{{ old($role.'.email') }}" maxlength="255"></label>
    <label class="field"><span>Telefon</span><input type="tel" name="{{ $role }}[phone]" value="{{ old($role.'.phone') }}" maxlength="40"></label>
    <label class="field field-span-2"><span>Alamat {{ $label }}</span><textarea name="{{ $role }}[address]" rows="2" maxlength="2000">{{ old($role.'.address') }}</textarea></label>
</div>@if ($loop->first)<div class="form-divider"></div>@endif
@endforeach
</section>
<section class="form-card"><div class="form-section-heading"><div><h2>Tapak dan lot</h2><p>Pendaftaran tertakluk kepada skop daerah pegawai.</p></div></div><div class="form-grid">
    <label class="field field-span-2"><span>Alamat tapak *</span><textarea name="site[development_address]" rows="2" maxlength="2000" required>{{ old('site.development_address') }}</textarea></label>
    <label class="field"><span>Daerah *</span><select name="site[district]" required>@foreach ($districts as $district)<option value="{{ $district }}" @selected(old('site.district') === $district)>{{ $district }}</option>@endforeach</select></label>
    <label class="field"><span>Mukim *</span><input name="site[mukim]" value="{{ old('site.mukim') }}" maxlength="100" required></label>
</div><div class="form-divider"></div>
<div class="repeater-heading"><h3 class="subsection-title">Senarai lot *</h3><button type="button" class="button button-secondary button-small" data-repeater-add="lots">Tambah lot</button></div>
<div data-repeater="lots"><div data-repeater-items>
    @foreach (old('lots', [['lot_number' => '']]) as $index => $lot)<div class="form-grid" data-repeater-item><label class="field field-span-2"><span>No. lot *</span><input name="lots[{{ $index }}][lot_number]" value="{{ $lot['lot_number'] ?? '' }}" maxlength="100" required></label><button type="button" class="button button-ghost" data-repeater-remove aria-label="Buang lot">Buang lot</button></div>@endforeach
</div><template data-repeater-template><div class="form-grid" data-repeater-item><label class="field field-span-2"><span>No. lot *</span><input name="lots[__INDEX__][lot_number]" maxlength="100" required></label><button type="button" class="button button-ghost" data-repeater-remove aria-label="Buang lot">Buang lot</button></div></template></div>
</section>
<section class="form-card"><div class="form-section-heading"><div><h2>Borang Jabatan Bangunan</h2><p>Butiran teknikal asas bangunan atau struktur yang dimohon.</p></div></div><div class="form-grid">
    <label class="field"><span>Kategori bangunan / struktur *</span><input name="bangunan[building_category]" value="{{ old('bangunan.building_category') }}" maxlength="100" required></label>
    <label class="field field-span-2"><span>Kegunaan bangunan / struktur *</span><input name="bangunan[building_use]" value="{{ old('bangunan.building_use') }}" maxlength="255" required></label>
    <label class="field"><span>Bilangan tingkat</span><input type="number" name="bangunan[number_of_storeys]" value="{{ old('bangunan.number_of_storeys') }}" min="1" max="999"></label>
    <label class="field"><span>Bilangan unit</span><input type="number" name="bangunan[number_of_units]" value="{{ old('bangunan.number_of_units') }}" min="1" max="1000000"></label>
    <label class="field"><span>Jumlah luas lantai (m²)</span><input type="number" name="bangunan[gross_floor_area]" value="{{ old('bangunan.gross_floor_area') }}" step="0.01" min="0.01"></label>
    <label class="field field-span-2"><span>Catatan teknikal</span><textarea name="bangunan[technical_notes]" rows="3" maxlength="5000">{{ old('bangunan.technical_notes') }}</textarea></label>
</div></section>
<div class="form-actions"><a href="{{ route('bangunan.index') }}" class="button button-ghost">Batal</a><button type="submit" class="button button-primary">Daftar permohonan</button></div>
</form>
@endsection
