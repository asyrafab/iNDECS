@extends('layouts.app')
@section('title', 'Perpustakaan jabatan')
@section('content')
<div class="page-heading heading-with-action">
    <div><span class="eyebrow">Rujukan jabatan</span><h1>Perpustakaan jabatan</h1><p>Garis panduan, piawaian dan rujukan yang digunakan oleh pegawai, AIRS dan AMIRA. Hanya Penolong Pengarah jabatan boleh menambah, mengemas kini atau membatalkan dokumen.</p></div>
    @if ($canManage)<a class="button button-primary" href="{{ route('library.create') }}"><x-icon name="file-plus-2" />Tambah dokumen</a>@endif
</div>
@if (session('success'))<div class="alert alert-success" role="status"><x-icon name="circle-check" /><div>{{ session('success') }}</div></div>@endif
<form class="content-card library-filter" method="GET" action="{{ route('library.index') }}">
    <label class="field"><span>Jabatan</span><select name="department" onchange="this.form.requestSubmit()">@foreach ($departments as $item)<option value="{{ $item->id }}" @selected($department?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></label>
    <noscript><button class="button button-secondary" type="submit">Papar</button></noscript>
</form>
<section class="content-card">
    @if ($documents->isEmpty())
        <div class="empty-state compact"><x-icon name="files" /><h3>Belum ada dokumen</h3><p>Perpustakaan {{ $department?->name }} belum mempunyai dokumen rujukan.</p></div>
    @else
        <ul class="library-list">
            @foreach ($documents as $document)
                <li class="{{ $document->status === 'retired' ? 'is-retired' : '' }}">
                    <div>
                        <h3><a href="{{ route('library.show', $document) }}">{{ $document->title }}</a></h3>
                        <p>{{ $document->categoryLabel() }} · Versi {{ $document->current_version }}@if ($document->applicationTypes->isNotEmpty()) · {{ $document->applicationTypes->pluck('id')->join(', ') }}@endif</p>
                    </div>
                    <span class="status-badge {{ $document->visibility === 'public' ? 'status-info' : 'status-pending' }}">{{ \App\Models\LibraryDocument::VISIBILITIES[$document->visibility] }}</span>
                    @if ($document->status === 'retired')<span class="status-badge status-critical">Dibatalkan</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
