@extends('layouts.app')
@section('title', $document->title)
@section('content')
<div class="page-heading"><a class="back-link" href="{{ route('library.index', ['department' => $document->department_id]) }}"><x-icon name="arrow-left" />Kembali ke perpustakaan</a><span class="eyebrow">{{ $document->department->name }} · {{ $document->categoryLabel() }}</span><h1>{{ $document->title }}</h1>@if ($document->description)<p>{{ $document->description }}</p>@endif</div>
@if (session('success'))<div class="alert alert-success" role="status"><x-icon name="circle-check" /><div>{{ session('success') }}</div></div>@endif
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<section class="content-card detail-card">
    <dl class="detail-grid">
        <div><dt>Status</dt><dd>@if ($document->status === 'retired')<span class="status-badge status-critical">Dibatalkan</span> {{ $document->retired_at?->timezone('Asia/Kuala_Lumpur')->format('d/m/Y') }}@else<span class="status-badge status-compliant">Aktif</span>@endif</dd></div>
        <div><dt>Paparan</dt><dd>{{ \App\Models\LibraryDocument::VISIBILITIES[$document->visibility] }}</dd></div>
        <div><dt>Versi semasa</dt><dd>Versi {{ $document->current_version }}</dd></div>
        <div><dt>Jenis permohonan</dt><dd>{{ $document->applicationTypes->isEmpty() ? 'Semua' : $document->applicationTypes->pluck('id')->join(', ') }}</dd></div>
    </dl>
</section>
<section class="content-card">
    <h2>Sejarah versi</h2>
    <ul class="library-list">
        @foreach ($document->versions as $version)
            <li>
                <div><h3>Versi {{ $version->version }}@if ($version->version === $document->current_version) <span class="status-badge status-info">Semasa</span>@endif</h3>
                <p>{{ $version->original_name }} · {{ number_format($version->size_bytes / 1024, 0) }} KB · {{ $version->uploader?->name }} · {{ $version->created_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }}</p>@if ($version->notes)<p>{{ $version->notes }}</p>@endif</div>
                <a class="button button-secondary button-small" href="{{ route('library.download', [$document, $version->version]) }}"><x-icon name="download" />Muat turun</a>
            </li>
        @endforeach
    </ul>
</section>
@if ($canManage && $document->status === 'active')
    <form class="application-form" method="POST" action="{{ route('library.versions.store', $document) }}" enctype="multipart/form-data">
        @csrf
        <section class="form-card">
            <div class="form-section-heading"><div><h2>Muat naik versi baharu</h2><p>Versi lama dikekalkan dan tidak boleh diubah.</p></div></div>
            <div class="form-grid">
                <label class="field field-span-2"><span>Fail dokumen (PDF) *</span><input type="file" name="file" accept="application/pdf,.pdf" required></label>
                <label class="field field-span-2"><span>Catatan versi</span><input name="notes" maxlength="1000" value="{{ old('notes') }}"></label>
            </div>
        </section>
        <div class="form-actions"><div><button class="button button-primary" type="submit"><x-icon name="file-plus-2" />Muat naik versi</button></div></div>
    </form>
    <form method="POST" action="{{ route('library.retire', $document) }}" data-confirm="Batalkan dokumen ini? Ia tidak lagi dipaparkan kepada pemohon atau digunakan sebagai rujukan. Tindakan ini tidak boleh dibatalkan.">
        @csrf<div class="form-actions"><div><button class="button button-secondary" type="submit"><x-icon name="x" />Batalkan dokumen</button></div></div>
    </form>
@endif
@endsection
