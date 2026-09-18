@extends('layouts.app')

@section('title', 'Permohonan PKM')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">Modul 01</span>
            <h1>Permohonan PKM</h1>
            <p>Daftar, cari dan jejak permohonan Kebenaran Merancang.</p>
        </div>
        <a href="{{ route('pkm.create') }}" class="button button-primary"><span>＋</span> Daftar PKM</a>
    </div>

    <section class="content-card">
        <form method="GET" action="{{ route('pkm.index') }}" class="filter-bar">
            <label class="search-field">
                <span class="sr-only">Cari permohonan</span>
                <span class="search-icon">⌕</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Cari rujukan, OSC atau tajuk…">
            </label>
            <label>
                <span class="sr-only">Tapis status</span>
                <select name="status">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-secondary" type="submit">Tapis</button>
            @if ($search !== '' || $selectedStatus)
                <a href="{{ route('pkm.index') }}" class="button button-ghost">Kosongkan</a>
            @endif
        </form>

        @if ($applications->isEmpty())
            <div class="empty-state compact">
                <span class="empty-icon">⌕</span>
                <h3>Tiada permohonan ditemui</h3>
                <p>Cuba ubah carian atau daftar satu permohonan baharu.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rujukan</th>
                            <th>Cadangan pembangunan</th>
                            <th>Guna tanah</th>
                            <th>Lokasi</th>
                            <th>Peringkat</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($applications as $application)
                            <tr>
                                <td>
                                    <a class="reference-link" href="{{ route('pkm.show', $application) }}">{{ $application->application_reference }}</a>
                                    <small>{{ $application->external_reference ?: 'Tiada rujukan OSC' }}</small>
                                </td>
                                <td>
                                    <strong class="table-title">{{ $application->title }}</strong>
                                    <small>Diterima {{ $application->submission_date->format('d/m/Y') }}</small>
                                </td>
                                <td>{{ $application->pkm->proposed_land_use }}</td>
                                <td>{{ $application->sites->first()?->mukim ?? '—' }}</td>
                                <td>{{ $application->current_stage->label() }}</td>
                                <td><span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></td>
                                <td><a class="row-action" href="{{ route('pkm.show', $application) }}" aria-label="Buka {{ $application->application_reference }}">→</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($applications->hasPages())
                <nav class="pagination" aria-label="Navigasi halaman">
                    @if ($applications->onFirstPage())
                        <span class="button button-ghost disabled">← Sebelumnya</span>
                    @else
                        <a class="button button-ghost" href="{{ $applications->previousPageUrl() }}">← Sebelumnya</a>
                    @endif
                    <span>Halaman {{ $applications->currentPage() }} daripada {{ $applications->lastPage() }}</span>
                    @if ($applications->hasMorePages())
                        <a class="button button-ghost" href="{{ $applications->nextPageUrl() }}">Seterusnya →</a>
                    @else
                        <span class="button button-ghost disabled">Seterusnya →</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
