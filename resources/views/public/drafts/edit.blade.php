@extends('layouts.portal')
@section('title', $draft->exists ? 'Sambung draf' : 'Mulakan draf')
@section('counter', '1')
@section('content')
    <x-portal-counter :board="$board" :draft="$draft" guide="Lengkapkan jenis permohonan, tajuk dan butiran projek, kemudian simpan draf. Senarai dokumen mengikut butiran projek anda; muat naik setiap dokumen untuk semakan awal percuma. Bayaran dan penghantaran rasmi belum dibuka."><x-slot:file>
    <div class="page-heading"><a class="back-link" href="{{ route('portal.home') }}"><x-icon name="arrow-left" />Kembali ke Fail saya</a><span class="eyebrow">Persediaan permohonan</span><h1>{{ $draft->exists ? 'Sambung draf' : 'Mulakan draf baharu' }}</h1><p>Simpan maklumat awal cadangan anda. Maklumat boleh dikemas kini kemudian.</p></div>
    @php($paidCheckOpen = $draft->exists && (config('airs.modules.'.$draft->application_type_id.'.enabled') ?? false) === true && \App\Services\PaidCheck\CheckPaymentService::feeSen() !== null)
    @if ($paidCheckOpen)
    <div class="alert alert-info"><x-icon name="circle-alert" /><div><strong>Draf belum dihantar kepada MBSP.</strong> Semakan awal dokumen adalah percuma. Semakan AIRS berbayar dibuka untuk jenis permohonan ini: selepas bayaran, keputusan semakan dipaparkan dan anda boleh membuat satu pindaan sebelum permohonan diserahkan kepada pegawai.</div></div>
    @else
    <div class="alert alert-info"><x-icon name="circle-alert" /><div><strong>Draf belum dihantar kepada MBSP.</strong> Semakan awal dokumen adalah percuma. Fi, bayaran dan penghantaran rasmi belum dibuka.</div></div>
    @endif
    @unless ($draft->exists)
        @include('public.drafts.partials.drop-zone', ['types' => $types])
    <details class="draft-details-toggle" @if (old('title') || request()->has('prefill') || request()->has('type')) open @endif><summary>Atau isi butiran draf dahulu</summary>
    @endunless
    <form class="application-form" method="POST" action="{{ $draft->exists ? route('portal.drafts.update', $draft) : route('portal.drafts.store') }}">
        @csrf
        @if($draft->exists)@method('PUT')<input type="hidden" name="version" value="{{ old('version', $draft->version) }}">@endif
        <section class="form-card">
            <div class="form-section-heading"><div><h2>Maklumat asas cadangan</h2><p>Jenis permohonan dan tajuk diperlukan untuk menyimpan draf.</p></div><span class="required-note">* Wajib diisi</span></div>
            <div class="form-grid">
                <label class="field field-span-2"><span>Jenis permohonan <b>*</b></span><select name="application_type_id" required @error('application_type_id') aria-invalid="true" @enderror>
                    <option value="">Pilih jenis permohonan</option>
                    @foreach($types->groupBy('primaryDepartment.name') as $department => $departmentTypes)
                        <optgroup label="{{ $department }}">@foreach($departmentTypes as $type)<option value="{{ $type->id }}" @selected(old('application_type_id', $draft->application_type_id) === $type->id)>{{ $type->id }} · {{ $type->name }}</option>@endforeach</optgroup>
                    @endforeach
                </select><small>Jenis yang tersedia untuk persediaan awal. Landskap akan menyusul.</small></label>
                <label class="field field-span-2"><span>Tajuk cadangan <b>*</b></span><input name="title" value="{{ old('title', $draft->title_provisional ? '' : $draft->title) }}" maxlength="500" required @error('title') aria-invalid="true" @enderror><small>@if ($draft->title_provisional)Tajuk sementara diberi semasa fail dimuat naik. Isi tajuk cadangan sebenar dan simpan draf. @endif Contoh: Cadangan mendirikan sebuah rumah di Lot 101, Mukim 6.</small></label>
                <label class="field field-span-2"><span>Alamat atau lokasi tapak</span><textarea name="site_address" rows="3" maxlength="2000" @error('site_address') aria-invalid="true" @enderror>{{ old('site_address', $draft->site_address) }}</textarea><small>Nyatakan nombor lot, mukim dan daerah jika diketahui.</small></label>
                <label class="field field-span-2"><span>Catatan persediaan</span><textarea name="notes" rows="4" maxlength="5000" @error('notes') aria-invalid="true" @enderror>{{ old('notes', $draft->notes) }}</textarea><small>Catatan peribadi untuk membantu anda menyambung persediaan.</small></label>
            </div>
        </section>
        @if (in_array(old('application_type_id', $draft->application_type_id), \App\Models\BangunanApplication::TYPES, true))
            @include('public.drafts.partials.particulars')
        @endif
        @include('public.drafts.partials.lighting-types', ['showLighting' => old('application_type_id', $draft->application_type_id) === 'LJUP'])
        @if ($editable ?? true)
        <div class="form-actions"><div><button class="button button-primary" type="submit"><x-icon name="check" />Simpan draf</button><a class="button button-secondary" href="{{ route('portal.home') }}">Kembali ke Fail saya</a></div><small>@if($draft->exists)Versi {{ $draft->version }} · @endif Simpanan manual. Simpan sebelum meninggalkan halaman.</small></div>
        @else
        <div class="form-actions"><div><a class="button button-secondary" href="{{ route('portal.home') }}">Kembali ke Fail saya</a></div><small>Draf dikunci semasa semakan AIRS dan selepas penyerahan. Perubahan hanya dibenarkan dalam tempoh pindaan.</small></div>
        @endif
    </form>
    @unless ($draft->exists)</details>@endunless
    @if ($draft->exists)
        @if ($checkResult ?? null)
            @include('public.drafts.partials.check-result', ['result' => $checkResult])
        @endif
        @if (($editable ?? true) && ($checklist['available'] ?? false))@include('public.drafts.partials.drop-zone')@endif
        @include('public.drafts.partials.checklist')
    @endif
    </x-slot:file></x-portal-counter>
@endsection
