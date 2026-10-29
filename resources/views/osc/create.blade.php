@extends('layouts.app')
@section('title', 'Mesyuarat baharu')
@section('content')
<div class="record-heading"><div><a href="{{ route('osc.meetings.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a><h1>Mesyuarat baharu</h1><p>Tetapkan tarikh, tarikh tutup, serta pengerusi, setiausaha dan ahli mesyuarat.</p></div></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<form method="POST" action="{{ route('osc.meetings.store') }}" class="application-form">
    @csrf
    <section class="form-card">
        <div class="form-section-heading"><div><h2>Butiran mesyuarat</h2><p>Semua waktu mengikut waktu tempatan.</p></div></div>
        <div class="form-grid">
            <label class="field"><span>Bil. mesyuarat <b>*</b></span><input type="text" name="number" value="{{ old('number') }}" maxlength="30" required placeholder="Bil. 22/2026"></label>
            <label class="field"><span>Tarikh dan masa <b>*</b></span><input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" required></label>
            <label class="field"><span>Tempat <b>*</b></span><input type="text" name="venue" value="{{ old('venue') }}" maxlength="255" required></label>
            <label class="field"><span>Tutup cadangan agenda <b>*</b></span><input type="datetime-local" name="proposal_cutoff_at" value="{{ old('proposal_cutoff_at') }}" required></label>
            <label class="field"><span>Tutup kertas kerja <b>*</b></span><input type="datetime-local" name="paper_cutoff_at" value="{{ old('paper_cutoff_at') }}" required></label>
        </div>
    </section>
    <section class="form-card">
        <div class="form-section-heading"><div><h2>Pegawai dan ahli</h2><p>Hanya kakitangan yang aktif boleh dilantik.</p></div></div>
        <div class="form-grid">
            @foreach (['chair_id' => ['Pengerusi', true], 'secretary_id' => ['Setiausaha', true], 'steno_id' => ['STENO', false]] as $name => [$label, $required])
                <label class="field"><span>{{ $label }} @if ($required)<b>*</b>@endif</span>
                    <select name="{{ $name }}" @required($required)><option value="">{{ $required ? 'Pilih' : 'Tiada' }}</option>@foreach ($staff as $person)<option value="{{ $person->id }}" @selected((string) old($name) === (string) $person->id)>{{ $person->name }}</option>@endforeach</select>
                </label>
            @endforeach
            <label class="field field-span-2"><span>Ahli lain</span>
                <select name="member_ids[]" multiple size="8">@foreach ($staff as $person)<option value="{{ $person->id }}" @selected(in_array($person->id, array_map('intval', (array) old('member_ids', []))))>{{ $person->name }}{{ $person->department_id ? ' · '.$person->department_id : '' }}</option>@endforeach</select>
                <small class="helper-text">Tahan Ctrl atau Cmd untuk memilih lebih daripada seorang.</small>
            </label>
        </div>
    </section>
    <div class="form-actions"><a href="{{ route('osc.meetings.index') }}" class="button button-ghost">Batal</a><button type="submit" class="button button-primary">Cipta mesyuarat</button></div>
</form>
@endsection
