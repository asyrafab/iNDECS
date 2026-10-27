@extends('layouts.app')

@section('title', 'iNDECS Bangunan · Carian Rekod Warisan')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">iNDECS Bangunan · Fasa A</span>
            <h1>Carian Rekod Warisan</h1>
            <p>Semakan baca-sahaja ke atas salinan penyegerakan pangkalan data warisan Jabatan Bangunan sementara sistem lama kekal sebagai penulis.</p>
        </div>
        <span class="phase-tag">Mod Baca Sahaja</span>
    </div>

    <section class="metric-grid" aria-label="Metrik rekod warisan Bangunan">
        <article class="metric-card metric-ink">
            <span class="metric-icon">▤</span>
            <div>
                <span>Jumlah Rekod</span>
                <strong>{{ number_format($metrics['total']) }}</strong>
                <small>Permohonan dalam skop daerah</small>
            </div>
        </article>
        <article class="metric-card metric-green">
            <span class="metric-icon">✓</span>
            <div>
                <span>Pelan Lulus</span>
                <strong>{{ number_format($metrics['approved']) }}</strong>
                <small>Termasuk peringkat CCC</small>
            </div>
        </article>
        <article class="metric-card metric-blue">
            <span class="metric-icon">◷</span>
            <div>
                <span>Dalam Proses</span>
                <strong>{{ number_format($metrics['in_process']) }}</strong>
                <small>Semakan SKP2 hingga SKP10</small>
            </div>
        </article>
        <article class="metric-card metric-amber">
            <span class="metric-icon">!</span>
            <div>
                <span>Tangguh / Ditutup</span>
                <strong>{{ number_format($metrics['deferred_or_closed']) }}</strong>
                <small>Tangguh, tolak, batal atau tarik balik</small>
            </div>
        </article>
    </section>

    <section class="content-card">
        <form method="GET" action="{{ route('bangunan.legacy.index') }}" class="filter-bar">
            <label class="search-field">
                <span class="sr-only">Cari rekod warisan</span>
                <span class="search-icon">⌕</span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Cari no. rujukan fail, no. siri, lot, pemohon atau lokasi…">
            </label>
            <label>
                <span class="sr-only">Tapis daerah</span>
                <select name="district">
                    <option value="">Semua daerah</option>
                    @foreach ($districts as $districtOption)
                        <option value="{{ $districtOption }}" @selected($selectedDistrict === $districtOption)>{{ $districtOption }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Tapis status</span>
                <select name="status">
                    <option value="">Semua status</option>
                    @foreach ($statusFilters as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected($selectedStatus === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </label>
            <button class="button button-secondary" type="submit">Tapis</button>
            @if ($search !== '' || $selectedDistrict !== '' || $selectedStatus !== '')
                <a href="{{ route('bangunan.legacy.index') }}" class="button button-ghost">Kosongkan</a>
            @endif
        </form>

        @if ($records->isEmpty())
            <div class="empty-state compact">
                <span class="empty-icon">⌕</span>
                <h3>Tiada rekod warisan ditemui</h3>
                <p>Cuba ubah kata kunci carian, penapis daerah atau jalankan penyegerakan salinan warisan.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Rujukan & Siri</th>
                            <th>Cadangan & Pemohon</th>
                            <th>Tapak & Lot</th>
                            <th>Kategori & Blok</th>
                            <th>Status Warisan</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>
                                    <a class="reference-link" href="{{ route('bangunan.legacy.show', $record) }}">{{ $record->pmn_no_rujukan }}</a>
                                    <small>Siri {{ $record->pmn_no_siri }} · ID {{ $record->pmn_id }}</small>
                                </td>
                                <td>
                                    <strong class="table-title">{{ $record->pmn_tajuk ?: 'Tiada tajuk direkodkan' }}</strong>
                                    <small>{{ $record->pmn_pemohon ?: '—' }} · Pohon {{ $record->pmn_tkh_pohon?->format('d/m/Y') ?? '—' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $record->pmn_tempat ?: '—' }}</strong>
                                    <small>Lot {{ $record->pmn_no_lot ?: '—' }} · {{ $record->district ?: '—' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $record->sk_kategori ?: '—' }}</strong>
                                    <small>{{ $record->bangunan_count }} kumpulan binaan · {{ $record->perunding_count }} perunding</small>
                                </td>
                                <td>
                                    <span class="status-badge status-{{ $record->statusBadgeTone() }}">{{ $record->effectiveStatusLabel() }}</span>
                                    @if ($record->pmn_tkh_lulus)
                                        <small>Lulus {{ $record->pmn_tkh_lulus->format('d/m/Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    <a class="row-action" href="{{ route('bangunan.legacy.show', $record) }}" aria-label="Buka {{ $record->pmn_no_rujukan }}">→</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <nav class="pagination" aria-label="Navigasi halaman">
                    @if ($records->onFirstPage())
                        <span class="button button-ghost disabled">← Sebelumnya</span>
                    @else
                        <a class="button button-ghost" href="{{ $records->previousPageUrl() }}">← Sebelumnya</a>
                    @endif
                    <span>Halaman {{ $records->currentPage() }} daripada {{ $records->lastPage() }}</span>
                    @if ($records->hasMorePages())
                        <a class="button button-ghost" href="{{ $records->nextPageUrl() }}">Seterusnya →</a>
                    @else
                        <span class="button button-ghost disabled">Seterusnya →</span>
                    @endif
                </nav>
            @endif
        @endif
    </section>
@endsection
