@extends('layouts.app')

@section('title', $record->pmn_no_rujukan.' · iNDECS Bangunan')

@section('content')
    <div class="record-heading">
        <div>
            <a href="{{ route('bangunan.legacy.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke carian warisan</a>
            <div class="record-reference-row">
                <span class="record-type">WARISAN BANGUNAN</span>
                <span class="record-reference">{{ $record->pmn_no_rujukan }}</span>
                <span class="status-badge status-{{ $record->statusBadgeTone() }}">{{ $record->effectiveStatusLabel() }}</span>
            </div>
            <h1>{{ $record->pmn_tajuk ?: 'Tiada tajuk permohonan direkodkan' }}</h1>
            <p>Salinan baca-sahaja daripada <code>PERMOHONAN1</code> (<code>PmnID {{ $record->pmn_id }}</code> · <code>PmnNoSiri {{ $record->pmn_no_siri }}</code>).</p>
        </div>
        <span class="phase-tag">Baca Sahaja · Fasa A</span>
    </div>

    <section class="workflow-banner">
        <div class="workflow-current">
            <span class="workflow-symbol"><x-icon name="circle-dot" /></span>
            <div>
                <small>Status berkesan (hierarki tarikh cabang)</small>
                <strong>{{ $record->effectiveStatusLabel() }}</strong>
            </div>
        </div>
        <div class="workflow-line"><span></span></div>
        <div class="workflow-next">
            <small>Kod PmnStatus asal</small>
            <strong>{{ $record->pmn_status ?: '—' }}</strong>
        </div>
        <div class="workflow-meta">
            <span>No. Siri</span>
            <strong>{{ $record->pmn_no_siri }}</strong>
        </div>
    </section>

    <div class="record-layout">
        <div class="record-main">
            <section class="content-card detail-card">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Ringkasan</span>
                        <h2>Butiran permohonan warisan</h2>
                    </div>
                </div>
                <dl class="detail-grid">
                    <div><dt>No. Rujukan Fail (PmnNorujukan)</dt><dd>{{ $record->pmn_no_rujukan }}</dd></div>
                    <div><dt>No. Siri Borang (PmnNoSiri)</dt><dd>{{ $record->pmn_no_siri }}</dd></div>
                    <div><dt>ID Dalaman (PmnID)</dt><dd>{{ $record->pmn_id }}</dd></div>
                    <div><dt>Kategori (SENARAIKATEGORI)</dt><dd>{{ $record->sk_kategori ?: '—' }}</dd></div>
                    <div><dt>Tarikh Pohon</dt><dd>{{ $record->pmn_tkh_pohon?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Tarikh Bayar</dt><dd>{{ $record->pmn_tkh_bayar?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Tarikh Lulus</dt><dd>{{ $record->pmn_tkh_lulus?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Tarikh Tangguh / Tolak</dt><dd>{{ $record->pmn_tkh_tangguh?->format('d/m/Y') ?? $record->pmn_tkh_tolak?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Jumlah Fi Pelan (RM)</dt><dd>{{ $record->pmn_bayaran !== null ? number_format((float) $record->pmn_bayaran, 2) : '—' }}</dd></div>
                    <div><dt>Wang Cagaran (RM)</dt><dd>{{ $record->pmn_bayaran_cagaran !== null ? number_format((float) $record->pmn_bayaran_cagaran, 2) : '—' }}</dd></div>
                    <div><dt>Keputusan OSC / YDP</dt><dd>{{ collect([$record->pmn_keputusan_osc, $record->pmn_keputusan_ydp])->filter()->join(' / ') ?: '—' }}</dd></div>
                    <div><dt>Rujukan Perancang</dt><dd>{{ $record->pmn_no_rjk_perancang ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="content-card detail-card">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Borang Jabatan (`BANGUNAN1`)</span>
                        <h2>Spesifikasi kumpulan bangunan</h2>
                    </div>
                    <span class="phase-tag">Pautan: PmnNoSiri</span>
                </div>

                @if ($record->bangunan->isEmpty())
                    <p class="muted">Tiada rekod spesifikasi kumpulan bangunan (`BANGUNAN1`) bagi nombor siri ini.</p>
                @else
                    <div class="component-list">
                        @foreach ($record->bangunan as $block)
                            <article>
                                <span class="component-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <strong>{{ $block->sb_jenis_binaan ?: 'Jenis binaan tidak dinyatakan' }}</strong>
                                    <small>
                                        BgnID {{ $block->bgn_id }} · {{ $block->bgn_tingkat }} tingkat
                                        @if ($block->bgn_chk_spah || $block->bgn_chk_ottv || $block->bgn_chk_oku)
                                            · Hijau/OKU:
                                            {{ collect([
                                                $block->bgn_chk_spah ? 'SPAH' : null,
                                                $block->bgn_chk_ottv ? 'OTTV' : null,
                                                $block->bgn_chk_oku ? 'OKU' : null,
                                            ])->filter()->join(', ') }}
                                        @endif
                                    </small>
                                </div>
                                <div><span>Unit</span><strong>{{ number_format($block->bgn_unit) }}</strong></div>
                                <div><span>Fi Binaan</span><strong>RM {{ number_format((float) $block->bgn_jumlah, 2) }}</strong></div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="content-card detail-card">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Perakuan Siap dan Pematuhan (`CCC`)</span>
                        <h2>Penyerahan borang B, G1–G21 dan F</h2>
                    </div>
                    <span class="phase-tag">Pautan: PmnNorujukan</span>
                </div>

                @if ($hasSharedFileReference)
                    <div class="note-panel">
                        <span>Amaran rujukan fail dikongsi (needs_review)</span>
                        <p>Nombor rujukan fail <strong>{{ $record->pmn_no_rujukan }}</strong> dikongsi oleh {{ $siblings->count() + 1 }} permohonan. Rekod CCC di bawah dipaut melalui <code>CCC_PmnNoRujukan</code> dan memerlukan pengesahan pegawai sebelum pemetaan muktamad Fasa B.</p>
                    </div>
                @endif

                @if ($record->ccc->isEmpty())
                    <p class="muted">Tiada rekod borang CCC ditemui bagi rujukan fail ini.</p>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Borang</th>
                                    <th>Komponen (`SENARAICCC`)</th>
                                    <th>Status</th>
                                    <th>Tarikh Pengesahan</th>
                                    <th>ID Rekod</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($record->ccc as $cccItem)
                                    <tr>
                                        <td><strong>{{ $cccItem->sccc_jenis ?: '—' }}</strong></td>
                                        <td>{{ $cccItem->sccc_komponen ?: '—' }}</td>
                                        <td>
                                            <span class="status-badge {{ $cccItem->ccc_status === 'Y' ? 'status-approved' : 'status-in_review' }}">
                                                {{ $cccItem->ccc_status === 'Y' ? 'Disahkan (Y)' : ($cccItem->ccc_status ?: '—') }}
                                            </span>
                                        </td>
                                        <td>{{ $cccItem->ccc_tkh_ccc?->format('d/m/Y') ?? '—' }}</td>
                                        <td>{{ $cccItem->ccc_id }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="content-card detail-card">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Lokasi</span>
                        <h2>Tapak dan lot warisan</h2>
                    </div>
                </div>
                <dl class="detail-grid">
                    <div class="detail-span-2"><dt>Lokasi / Tempat (`PmnTempat`)</dt><dd>{{ $record->pmn_tempat ?: '—' }}</dd></div>
                    <div><dt>Daerah</dt><dd>{{ $record->district ?: '—' }}</dd></div>
                    <div><dt>Mukim (`SDMukim`)</dt><dd>{{ $record->sd_mukim ? 'Mukim '.$record->sd_mukim : '—' }}</dd></div>
                    <div class="detail-span-2"><dt>Senarai Lot (`PmnNoLot`)</dt><dd>{{ $record->pmn_no_lot ?: '—' }}</dd></div>
                </dl>
            </section>
        </div>

        <aside class="record-side">
            <section class="content-card side-card">
                <div class="card-heading"><div><span class="eyebrow">Pihak</span><h2>Pemohon & tuan tanah</h2></div></div>
                <div class="party-list">
                    <article>
                        <span class="party-avatar">P</span>
                        <div>
                            <small>Pemohon (`PmnPemohon`)</small>
                            <strong>{{ $record->pmn_pemohon ?: '—' }}</strong>
                        </div>
                    </article>
                    <article>
                        <span class="party-avatar">T</span>
                        <div>
                            <small>Tuan Tanah (`PmnTuanTanah`)</small>
                            <strong>{{ $record->pmn_tuan_tanah ?: '—' }}</strong>
                        </div>
                    </article>
                </div>
            </section>

            <section class="content-card side-card">
                <div class="card-heading"><div><span class="eyebrow">Perunding (`PERUNDING`)</span><h2>Firma & PSP</h2></div></div>
                @if ($record->perunding->isEmpty())
                    <p class="muted">Tiada rekod perunding bagi nombor siri ini.</p>
                @else
                    <div class="party-list">
                        @foreach ($record->perunding as $consultant)
                            <article>
                                <span class="party-avatar">{{ mb_strtoupper(mb_substr($consultant->prd_perunding ?? 'P', 0, 1)) }}</span>
                                <div>
                                    <small>{{ $consultant->prd_pelan ?: 'Perunding' }}</small>
                                    <strong>{{ $consultant->prd_perunding ?: '—' }}</strong>
                                    @if ($consultant->prd_arkitek)<span>{{ $consultant->prd_arkitek }}</span>@endif
                                    @if ($consultant->prd_no_daftar)<span>No. Daftar: {{ $consultant->prd_no_daftar }}</span>@endif
                                    @if ($consultant->prd_tel)<span>{{ $consultant->prd_tel }}</span>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            @if ($hasSharedFileReference)
                <section class="content-card side-card">
                    <div class="card-heading"><div><span class="eyebrow">Salasilah Fail</span><h2>Permohonan berkongsi rujukan</h2></div></div>
                    <div class="party-list">
                        @foreach ($siblings as $sibling)
                            <article>
                                <span class="party-avatar"><x-icon name="arrow-up-right" /></span>
                                <div>
                                    <small>Siri {{ $sibling->pmn_no_siri }} · {{ $sibling->pmn_tkh_pohon?->format('d/m/Y') ?? '—' }}</small>
                                    <strong><a class="reference-link" href="{{ route('bangunan.legacy.show', $sibling) }}">{{ $sibling->pmn_tajuk ?: $sibling->pmn_no_rujukan }}</a></strong>
                                    <span>{{ $sibling->effectiveStatusLabel() }} (PmnID {{ $sibling->pmn_id }})</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
@endsection
