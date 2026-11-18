@extends('layouts.app')
@section('title', 'Cipta tugasan')
@section('content')
<div class="page-heading"><a class="text-link" href="{{ route('tasks.index') }}">Kembali ke tugasan</a><h1>Cipta tugasan</h1><p>Pilih rekod yang dibenarkan, kemudian agihkan kerja kepada pegawai jabatan yang sama.</p></div>
<section class="content-card">
    <form method="GET" action="{{ route('tasks.create') }}" class="filter-bar">
        <label><span>Cari permohonan</span><input name="search" type="search" value="{{ $search }}" maxlength="255" placeholder="Rujukan atau tajuk permohonan"></label><button class="button button-secondary" type="submit">Cari</button>
    </form>
    <form method="GET" action="{{ route('tasks.create') }}" class="filter-bar">
        <label><span>Permohonan</span><select name="application" required><option value="">Pilih permohonan</option>@foreach ($applications as $record)<option value="{{ $record->id }}" @selected($application?->id === $record->id)>{{ $record->application_reference }} · {{ $record->title }}</option>@endforeach</select></label>
        <button class="button button-secondary" type="submit">Pilih rekod</button>
    </form>
    <p class="muted">Sehingga 100 rekod terkini dipaparkan. Gunakan carian untuk mengecilkan senarai.</p>
</section>
@if ($application)
<form method="POST" action="{{ route('tasks.store') }}" class="form-card application-form">
    @csrf<input type="hidden" name="application_id" value="{{ $application->id }}">
    <div class="card-heading"><h2>{{ $application->application_reference }}</h2><span>{{ $application->title }}</span></div>
    <label class="field"><span>Tajuk tugasan *</span><input name="title" required maxlength="255" value="{{ old('title') }}"></label>
    <label class="field"><span>Arahan kerja</span><textarea name="instructions" rows="4" maxlength="5000">{{ old('instructions') }}</textarea></label>
    <label class="field"><span>Pegawai bertanggungjawab *</span><select name="assignee_id" required><option value="">Pilih pegawai</option>@foreach ($assignees as $officer)<option value="{{ $officer->id }}" @selected((string) old('assignee_id') === (string) $officer->id)>{{ $officer->name }}</option>@endforeach</select></label>
    <label class="field"><span>Tarikh sasaran manual</span><input name="due_date" type="date" value="{{ old('due_date') }}"><small>Ini bukan tarikh akhir SLA berkanun.</small></label>
    <div class="form-actions"><button class="button button-primary" type="submit">Cipta dan agih tugasan</button></div>
</form>
@endif
@endsection
