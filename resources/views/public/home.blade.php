@extends('layouts.portal')
@section('title', 'Portal Awam')
@section('counter', '1')
@section('content')
    <x-portal-counter :board="$board"><x-slot:file>
        @include('public.partials.file-board')
    </x-slot:file></x-portal-counter>
    <dialog class="portal-dialog" id="portal-new" aria-labelledby="portal-new-title" aria-describedby="portal-new-description">
        <div class="portal-dialog-head"><span class="eyebrow">Permohonan baru</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup pilihan permohonan"><x-icon name="x" /></button></div>
        <h2 id="portal-new-title">Apakah urusan anda?</h2>
        <p id="portal-new-description">Pilih jenis permohonan untuk menyediakan draf maklumat awal. Penghantaran rasmi belum dibuka.</p>
        @if(! $user->email_verified_at)
            <div class="alert alert-info"><x-icon name="circle-alert" /><div>Sahkan emel anda sebelum memulakan draf. <a href="{{ route('portal.account') }}">Buka Akaun saya</a>.</div></div>
        @else
            <div class="portal-type-groups">
                @forelse($types->groupBy('primaryDepartment.name') as $department => $departmentTypes)
                    <section><h3>{{ $department }}</h3><div class="portal-type-list">
                        @foreach($departmentTypes as $type)
                            <div class="portal-type-row">
                                <a href="{{ route('portal.drafts.create', ['type' => $type->id]) }}"><span class="portal-type-code">{{ $type->id }}</span><span>{{ $type->name }}</span><x-icon name="arrow-right" /></a>
                                <a class="icon-button portal-type-download" href="#portal-download-{{ $type->id }}" data-portal-open="portal-download-{{ $type->id }}" aria-haspopup="dialog" aria-controls="portal-download-{{ $type->id }}" aria-label="Muat turun dokumen {{ $type->id }} · {{ $type->name }}" title="Muat turun dokumen {{ $type->id }}"><x-icon name="download" /></a>
                            </div>
                        @endforeach
                    </div></section>
                @empty
                    <p>Tiada jenis permohonan tersedia buat masa ini.</p>
                @endforelse
            </div>
        @endif
    </dialog>
    @if($user->email_verified_at)
        @foreach($types as $type)
            <dialog class="portal-dialog portal-download-dialog" id="portal-download-{{ $type->id }}" data-portal-parent="portal-new" aria-labelledby="portal-download-title-{{ $type->id }}" aria-describedby="portal-download-description-{{ $type->id }}">
                <div class="portal-dialog-head"><span class="eyebrow">Dokumen rujukan · {{ $type->id }}</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup dokumen {{ $type->id }}"><x-icon name="x" /></button></div>
                <h2 id="portal-download-title-{{ $type->id }}">{{ $type->name }}</h2>
                <p id="portal-download-description-{{ $type->id }}">{{ empty($libraryByType[$type->id]) || $libraryByType[$type->id]->isEmpty() ? 'Dokumen rujukan jabatan akan disediakan kemudian.' : 'Pilih dokumen rujukan jabatan untuk dimuat turun (PDF).' }}</p>
                <div class="portal-download-options">
                    @forelse($libraryByType[$type->id] ?? [] as $libraryDocument)
                        {{-- ADR-362: public, active department library documents for this type. --}}
                        <a href="{{ route('portal.library.download', $libraryDocument) }}"><x-icon name="file-text" /><span><strong>{{ $libraryDocument->title }}</strong><small>{{ $libraryDocument->categoryLabel() }} · PDF · v{{ $libraryDocument->current_version }}</small></span><x-icon name="download" /></a>
                    @empty
                        <button type="button" disabled><x-icon name="file-text" /><span><strong>Senarai semak (Checklist)</strong><small>Belum tersedia</small></span><x-icon name="download" /></button>
                        <button type="button" disabled><x-icon name="file-text" /><span><strong>Garis panduan</strong><small>Belum tersedia</small></span><x-icon name="download" /></button>
                    @endforelse
                </div>
            </dialog>
        @endforeach
    @endif
@endsection
