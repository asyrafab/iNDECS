@extends('layouts.app')
@section('title', 'Bentang ke mesyuarat')
@section('content')
<div class="record-heading"><div><a href="{{ route('osc.meetings.index', $application ? ['application' => $application->id] : []) }}" class="back-link"><x-icon name="arrow-left" /> Kembali</a><h1>Bentang ke mesyuarat {{ $meeting->number }}</h1><p>{{ $meeting->scheduled_at->format('d/m/Y H:i') }} · {{ $meeting->venue }} · tutup cadangan {{ $meeting->proposal_cutoff_at->format('d/m/Y H:i') }}</p></div></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<form method="POST" action="{{ route('osc.items.store', $meeting) }}" class="application-form">
    @csrf
    <section class="form-card">
        <div class="form-section-heading"><div><h2>{{ $application ? 'Permohonan yang dibentangkan' : 'Perkara yang dicadangkan' }}</h2><p>{{ $application ? 'Butiran diambil daripada permohonan dan tidak boleh diubah di sini.' : 'Perkara tanpa permohonan, contohnya penamaan jalan atau taman.' }}</p></div></div>
        <div class="form-grid">
            @if ($application)
                <input type="hidden" name="application_id" value="{{ $application->id }}">
                <label class="field"><span>Rujukan</span><input type="text" value="{{ $application->application_reference }}" readonly></label>
                <label class="field field-span-2"><span>Tajuk</span><input type="text" value="{{ $application->title }}" readonly></label>
            @else
                <label class="field"><span>Jenis perkara <b>*</b></span><input type="text" name="standalone_type" value="{{ old('standalone_type') }}" maxlength="100" required></label>
                <label class="field field-span-2"><span>Tajuk <b>*</b></span><input type="text" name="title" value="{{ old('title') }}" maxlength="1000" required></label>
                <label class="field"><span>Rujukan</span><input type="text" name="reference" value="{{ old('reference') }}" maxlength="255"></label>
                @if ($canManage)
                    <label class="field"><span>Jabatan <b>*</b></span><select name="department_id" required><option value="">Pilih jabatan</option>@foreach ($departments as $id => $name)<option value="{{ $id }}" @selected(old('department_id') === $id)>{{ $name }}</option>@endforeach</select></label>
                @endif
            @endif
            <label class="field"><span>Tujuan pembentangan <b>*</b></span><select name="purpose" required>@foreach ($purposes as $purpose)<option value="{{ $purpose->value }}" @selected(old('purpose') === $purpose->value)>{{ $purpose->label() }}</option>@endforeach</select></label>
            <label class="field"><span>Penyampai <b>*</b></span><select name="presenter_id" required>@foreach ($presenters as $person)<option value="{{ $person->id }}" @selected((string) old('presenter_id', auth()->id()) === (string) $person->id)>{{ $person->name }}</option>@endforeach</select></label>
        </div>
    </section>
    <div class="form-actions"><a href="{{ route('osc.meetings.index', $application ? ['application' => $application->id] : []) }}" class="button button-ghost">Batal</a><button type="submit" class="button button-primary">Hantar cadangan</button></div>
</form>
@endsection
