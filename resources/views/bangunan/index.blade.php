@extends('layouts.app')
@section('title', 'Permohonan Bangunan')
@section('content')
<div class="page-heading heading-with-action">
    <div><span class="eyebrow">iNDECS Bangunan · Jabatan Bangunan</span><h1>Permohonan Bangunan</h1><p>Pantau permohonan, semakan dokumen dan tindakan dalam ruang kerja anda.</p></div>
    @if ($types->contains(fn ($type) => auth()->user()->canPerformBangunan('register', $type)))
        <a href="{{ route('bangunan.create') }}" class="button button-primary"><x-icon name="plus" />Daftar permohonan</a>
    @endif
</div>
@php($catalogueTypes = $types->filter(fn ($family) => auth()->user()->canPerformBangunan('documents.configure', $family)))
<div class="metric-grid">
    @foreach (['total' => 'Jumlah permohonan', 'submitted' => 'Baharu didaftarkan', 'in_review' => 'Dalam pengesahan'] as $key => $label)
    <article class="metric-card"><div><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong><small>Dalam skop akses anda</small></div></article>
    @endforeach
    <article class="metric-card"><div><span>Jenis permohonan</span><strong>{{ $types->count() }}</strong><small>{{ $types->join(' · ') }}</small></div></article>
</div>
@if ($catalogueTypes->isNotEmpty())
<details class="catalogue-tools"><summary><x-icon name="settings-2" />Katalog dokumen jabatan <x-icon name="chevron-down" /></summary><div class="action-links">@foreach ($catalogueTypes as $family)<a class="button button-secondary button-small" href="{{ route('bangunan.document-catalogue.edit', $family) }}">{{ $family }}</a>@endforeach</div></details>
@endif
<section class="content-card">
    <div class="card-heading"><h2>Senarai permohonan</h2><span class="muted">{{ number_format($applications->total()) }} rekod mengikut penapis</span></div>
    <form method="GET" action="{{ route('bangunan.index') }}" class="filter-bar">
        <label class="search-field"><span class="sr-only">Cari rujukan, OSC atau tajuk</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari rujukan, OSC atau tajuk" maxlength="255"></label>
        <label><span class="sr-only">Jenis permohonan</span><select name="type"><option value="">Semua jenis</option>@foreach ($types as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></label>
        <label><span class="sr-only">Status</span><select name="status"><option value="">Semua status</option>@foreach (\App\Enums\ApplicationStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        <button type="submit" class="button button-secondary">Tapis</button><a href="{{ route('bangunan.index') }}" class="button button-ghost">Kosongkan</a>
    </form>
    @if ($applications->isEmpty())
    <div class="empty-state compact"><h2>Tiada permohonan ditemui</h2><p>Daftar permohonan baharu atau ubah penapis carian. Rekod sistem lama boleh dirujuk melalui Carian Rekod Warisan.</p></div>
    @else
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Rujukan</th><th>Cadangan</th><th>Jenis</th><th>Daerah</th><th>Peringkat</th><th>Status</th></tr></thead><tbody>
        @foreach ($applications as $application)<tr>
            <td><a class="reference-link" href="{{ route('bangunan.show', $application) }}">{{ $application->application_reference }}</a><small>{{ $application->external_reference }}</small></td>
            <td><strong class="table-title">{{ $application->title }}</strong><small>{{ $application->submission_date->format('d/m/Y') }}</small></td><td>{{ $application->application_type_id }}</td><td>{{ $application->sites->first()?->district }}</td><td>{{ $application->current_stage->label() }}</td><td><span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></td>
        </tr>@endforeach
    </tbody></table></div>
    @if ($applications->hasPages())<nav class="pagination" aria-label="Halaman permohonan">@if ($applications->previousPageUrl())<a class="button button-ghost" href="{{ $applications->previousPageUrl() }}">Sebelumnya</a>@endif<span>Halaman {{ $applications->currentPage() }} / {{ $applications->lastPage() }}</span>@if ($applications->nextPageUrl())<a class="button button-ghost" href="{{ $applications->nextPageUrl() }}">Seterusnya</a>@endif</nav>@endif
    @endif
</section>
@endsection
