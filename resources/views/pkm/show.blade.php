@extends('layouts.app')

@section('title', $application->application_reference)

@section('content')
    <div class="record-heading">
        <div>
            <a href="{{ route('perancang.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a>
            <div class="record-reference-row">
                <span class="record-type">PKM</span>
                <span class="record-reference">{{ $application->application_reference }}</span>
                <span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span>
            </div>
            <h1>{{ $application->title }}</h1>
            <p>{{ $application->description ?: 'Tiada keterangan tambahan direkodkan.' }}</p>
        </div>
        @if ($application->current_stage === \App\Enums\ApplicationStage::Registration && $application->current_status === \App\Enums\ApplicationStatus::Submitted)
            <form method="POST" action="{{ route('perancang.start-review', $application) }}" data-confirm="Mulakan semakan kelengkapan untuk permohonan ini?">
                @csrf
                <button type="submit" class="button button-primary button-large">Mulakan semakan <span><x-icon name="arrow-right" /></span></button>
            </form>
        @elseif ($application->current_status === \App\Enums\ApplicationStatus::ReturnedForAmendment)
            <form method="POST" action="{{ route('perancang.resubmit', $application) }}" data-confirm="Buka kitaran semakan baharu untuk pindaan ini?">
                @csrf
                <button type="submit" class="button button-primary button-large">Hantar semula pindaan <span><x-icon name="arrow-right" /></span></button>
            </form>
        @endif
    </div>

    @include('partials.record-navigation', ['recordNavigation' => array_filter(['Ringkasan' => '#ringkasan', 'Borang Jabatan' => '#borang-jabatan', 'Dokumen' => $activeReview ? '#dokumen' : null, 'Pemohon & Perunding' => '#pihak', 'Tapak & Lot' => '#tapak', 'Garis Masa' => '#garis-masa'])])
    <section class="workflow-banner">
        <div class="workflow-current">
            <span class="workflow-symbol"><x-icon name="circle-dot" /></span>
            <div>
                <small>Peringkat semasa</small>
                <strong>{{ $application->current_stage->label() }}</strong>
            </div>
        </div>
        <div class="workflow-line"><span></span></div>
        <div class="workflow-next">
            <small>Tindakan seterusnya</small>
            <strong>
                @if ($application->current_stage === \App\Enums\ApplicationStage::Registration)
                    Semakan kelengkapan
                @elseif ($application->current_stage === \App\Enums\ApplicationStage::CompletenessCheck)
                    Lengkapkan senarai semak
                @else
                    Mengikut konfigurasi aliran kerja
                @endif
            </strong>
        </div>
        <div class="workflow-meta">
            <span>{{ $application->due_date ? 'Tarikh tindakan' : 'Versi rekod' }}</span>
            <strong>{{ $application->due_date?->format('d/m') ?? $application->row_version }}</strong>
        </div>
    </section>

    <div class="record-layout">
        <div class="record-main">
            <section class="content-card detail-card" id="ringkasan">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Ringkasan</span>
                        <h2>Maklumat permohonan</h2>
                    </div>
                </div>
                <dl class="detail-grid">
                    <div><dt>Rujukan iNDECS</dt><dd>{{ $application->application_reference }}</dd></div>
                    <div><dt>Rujukan OSC</dt><dd>{{ $application->external_reference ?: '—' }}</dd></div>
                    <div><dt>Tarikh penerimaan</dt><dd>{{ $application->submission_date->format('d/m/Y') }}</dd></div>
                    <div><dt>Keutamaan</dt><dd class="capitalize">{{ $application->priority }}</dd></div>
                    <div><dt>Kategori KM</dt><dd>{{ $application->pkm->permission_category }}</dd></div>
                    <div><dt>Jenis KM</dt><dd>{{ $application->pkm->permission_type }}</dd></div>
                    <div><dt>Guna tanah sedia ada</dt><dd>{{ $application->pkm->existing_land_use }}</dd></div>
                    <div><dt>Guna tanah dicadangkan</dt><dd>{{ $application->pkm->proposed_land_use }}</dd></div>
                </dl>
            </section>

            @if ($activeReview)
                <section class="content-card detail-card completeness-card" id="dokumen">
                    <div class="card-heading completeness-heading">
                        <div>
                            <span class="eyebrow">Kitaran {{ $activeReview->cycle_number }}</span>
                            <h2>Dokumen & semakan kelengkapan</h2>
                            <p>{{ $activeReview->items->where('status', \App\Enums\CompletenessItemStatus::Complete)->count() }} daripada {{ $activeReview->items->count() }} item telah disahkan.</p>
                        </div>
                        <span class="review-status review-status-{{ $activeReview->status->value }}">{{ $activeReview->status->label() }}</span>
                    </div>

                    <div class="completeness-list">
                        @foreach ($activeReview->items as $item)
                            @php($document = $currentDocuments->get($item->document_type_id))
                            <article class="completeness-item item-status-{{ $item->status->value }}">
                                <div class="item-status-symbol">
                                    @if ($item->status === \App\Enums\CompletenessItemStatus::Complete) <x-icon name="check" />
                                    @elseif ($item->status === \App\Enums\CompletenessItemStatus::Missing) !
                                    @else {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    @endif
                                </div>
                                <div class="item-body">
                                    <div class="item-title-row">
                                        <div>
                                            <strong>{{ $item->requirement_name }}</strong>
                                            <span class="requirement-tag {{ $item->is_required ? 'required' : '' }}">{{ $item->is_required ? 'Wajib' : 'Pilihan' }}</span>
                                        </div>
                                        <span class="item-status-label">{{ $item->status->label() }}</span>
                                    </div>

                                    @if ($document)
                                        <div class="document-current">
                                            <div>
                                                <strong>{{ $document->original_name }}</strong>
                                                <small>Versi {{ $document->revision_number }} · {{ number_format($document->size_bytes / 1024, 0) }} KB · {{ $document->verification_status->label() }}</small>
                                            </div>
                                            <a href="{{ route('perancang.documents.download', [$application, $document]) }}" class="button button-secondary button-small">Muat turun</a>
                                        </div>
                                    @else
                                        <p class="document-empty">Belum ada dokumen dimuat naik.</p>
                                    @endif

                                    @if (in_array($application->current_status, [\App\Enums\ApplicationStatus::InReview, \App\Enums\ApplicationStatus::ReturnedForAmendment], true))
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('perancang.documents.store', [$application, $item->documentType]) }}" class="document-upload-form">
                                            @csrf
                                            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required aria-label="Pilih {{ $item->requirement_name }}">
                                            <button type="submit" class="button button-secondary button-small">{{ $document ? 'Muat naik versi baharu' : 'Muat naik' }}</button>
                                        </form>
                                    @endif

                                    @if ($application->current_status === \App\Enums\ApplicationStatus::InReview)
                                        <form method="POST" action="{{ route('perancang.completeness.items.update', [$application, $item]) }}" class="item-review-form">
                                            @csrf
                                            <select name="status" aria-label="Status semakan {{ $item->requirement_name }}">
                                                @foreach (\App\Enums\CompletenessItemStatus::cases() as $status)
                                                    @if (! ($item->is_required && $status === \App\Enums\CompletenessItemStatus::NotApplicable))
                                                        <option value="{{ $status->value }}" @selected($item->status === $status)>{{ $status->label() }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                            <input type="text" name="remarks" value="{{ $item->remarks }}" placeholder="Catatan pegawai (jika ada)">
                                            <button type="submit" class="button button-primary button-small">Simpan semakan</button>
                                        </form>
                                    @elseif ($item->remarks)
                                        <p class="item-remarks"><strong>Catatan:</strong> {{ $item->remarks }}</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($application->current_status === \App\Enums\ApplicationStatus::InReview)
                        <div class="review-actions">
                            <form method="POST" action="{{ route('perancang.completeness.complete', $application) }}" data-confirm="Sahkan semua dokumen lengkap dan teruskan ke penilaian fi?">
                                @csrf
                                <label class="field">
                                    <span>Catatan keputusan <small>(pilihan)</small></span>
                                    <input type="text" name="remarks" placeholder="Ringkasan semakan kelengkapan">
                                </label>
                                <button type="submit" class="button button-primary">Sahkan lengkap <span><x-icon name="arrow-right" /></span></button>
                            </form>
                            <form method="POST" action="{{ route('perancang.completeness.return', $application) }}" data-confirm="Kembalikan permohonan ini untuk pindaan?">
                                @csrf
                                <label class="field">
                                    <span>Sebab pemulangan <b>*</b></span>
                                    <input type="text" name="remarks" required placeholder="Nyatakan dokumen atau pindaan yang diperlukan">
                                </label>
                                <button type="submit" class="button button-danger">Kembali untuk pindaan</button>
                            </form>
                        </div>
                    @elseif ($activeReview->status === \App\Enums\CompletenessReviewStatus::Returned)
                        <div class="return-summary">
                            <strong>Permohonan menunggu pindaan</strong>
                            <p>{{ $activeReview->decision_remarks }}</p>
                            <small>Dikembalikan oleh {{ $activeReview->decidedBy?->name ?? 'Pegawai' }} pada {{ $activeReview->decided_at?->format('d/m/Y H:i') }}.</small>
                        </div>
                    @endif
                </section>
            @endif

            <section class="content-card detail-card" id="borang-jabatan">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Parameter teknikal</span>
                        <h2>Butiran perancangan</h2>
                    </div>
                </div>
                <div class="planning-metrics">
                    <div><span>Kepadatan</span><strong>{{ $application->pkm->density ?? '—' }}</strong><small>unit / hektar</small></div>
                    <div><span>Nisbah plot</span><strong>{{ $application->pkm->plot_ratio ?? '—' }}</strong><small>nisbah</small></div>
                    <div><span>Bilangan unit</span><strong>{{ $application->pkm->number_of_units !== null ? number_format($application->pkm->number_of_units) : '—' }}</strong><small>unit</small></div>
                    <div><span>Bil. tingkat</span><strong>{{ $application->pkm->number_of_storeys ?? '—' }}</strong><small>tingkat</small></div>
                    <div><span>Ketinggian</span><strong>{{ $application->pkm->building_height ?? '—' }}</strong><small>meter</small></div>
                </div>

                @if ($application->pkm->planning_standards)
                    <div class="note-panel">
                        <span>Piawaian / catatan awal</span>
                        <p>{{ $application->pkm->planning_standards }}</p>
                    </div>
                @endif

                <div class="subsection-block">
                    <h3>Komponen pembangunan</h3>
                    @if ($application->pkm->components->isEmpty())
                        <p class="muted">Tiada pecahan komponen direkodkan.</p>
                    @else
                        <div class="component-list">
                            @foreach ($application->pkm->components as $component)
                                <article>
                                    <span class="component-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <strong>{{ $component->name }}</strong>
                                        <small>{{ $component->category ?: 'Kategori tidak dinyatakan' }}</small>
                                    </div>
                                    <div><span>Unit</span><strong>{{ $component->number_of_units !== null ? number_format($component->number_of_units) : '—' }}</strong></div>
                                    <div><span>Luas lantai</span><strong>{{ $component->floor_area !== null ? number_format((float) $component->floor_area, 2).' m²' : '—' }}</strong></div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="content-card detail-card" id="tapak">
                <div class="card-heading">
                    <div>
                        <span class="eyebrow">Lokasi</span>
                        <h2>Tapak dan lot</h2>
                    </div>
                    <span class="phase-tag">GIS akan dipautkan</span>
                </div>
                @php($site = $application->sites->first())
                <dl class="detail-grid">
                    <div class="detail-span-2"><dt>Alamat tapak</dt><dd>{{ $site?->development_address ?? '—' }}</dd></div>
                    <div><dt>Daerah</dt><dd>{{ $site?->district ?? '—' }}</dd></div>
                    <div><dt>Mukim</dt><dd>{{ $site?->mukim ?? '—' }}</dd></div>
                    <div><dt>Luas tapak</dt><dd>{{ $site?->site_area ? number_format((float) $site->site_area, 4).' '.($site->site_area_unit === 'hectare' ? 'ha' : 'm²') : '—' }}</dd></div>
                    <div><dt>Negeri</dt><dd>{{ $site?->state ?? '—' }}</dd></div>
                </dl>

                <div class="lot-chips">
                    @foreach ($application->lots as $lot)
                        <div class="lot-chip">
                            <span>{{ $lot->lot_type ?: 'Lot' }}</span>
                            <strong>{{ $lot->lot_number }}</strong>
                            <small>{{ $lot->land_area ? number_format((float) $lot->land_area, 4).' '.($lot->land_area_unit === 'hectare' ? 'ha' : 'm²') : $lot->section }}</small>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="record-side">
            <section class="content-card side-card" id="pihak">
                <div class="card-heading"><div><span class="eyebrow">Pihak</span><h2>Pemohon & perunding</h2></div></div>
                <div class="party-list">
                    @foreach ($application->applicationParties as $applicationParty)
                        @php($snapshot = $applicationParty->submitted_snapshot)
                        <article>
                            <span class="party-avatar">{{ mb_strtoupper(mb_substr($snapshot['name'] ?? '?', 0, 1)) }}</span>
                            <div>
                                <small>{{ str($applicationParty->role)->replace('_', ' ')->title() }}</small>
                                <strong>{{ $snapshot['name'] ?? '—' }}</strong>
                                @if (!empty($snapshot['registration_number']))<span>{{ $snapshot['registration_number'] }}</span>@endif
                                @if (!empty($snapshot['phone']))<span>{{ $snapshot['phone'] }}</span>@endif
                                @if (!empty($snapshot['email']))<span>{{ $snapshot['email'] }}</span>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            @if ($application->assignee)
                <section class="content-card side-card">
                    <div class="card-heading"><div><span class="eyebrow">Tugasan</span><h2>Pegawai bertanggungjawab</h2></div></div>
                    <div class="assignment-panel">
                        <span class="party-avatar">{{ mb_strtoupper(mb_substr($application->assignee->name, 0, 1)) }}</span>
                        <div>
                            <strong>{{ $application->assignee->name }}</strong>
                            <span>{{ str($application->assignee->role)->replace('_', ' ')->title() }}</span>
                            <small>Tarikh tindakan: {{ $application->due_date?->format('d/m/Y') ?? '—' }}</small>
                        </div>
                    </div>
                </section>
            @endif

            <section class="content-card side-card" id="garis-masa">
                <div class="card-heading"><div><span class="eyebrow">Jejak audit</span><h2>Garis masa</h2></div></div>
                <ol class="timeline">
                    @foreach ($application->stageHistory as $history)
                        <li>
                            <span class="timeline-marker"></span>
                            <div>
                                <strong>{{ $history->new_stage->label() }}</strong>
                                <span>{{ $history->new_status->label() }}</span>
                                <p>{{ $history->remarks }}</p>
                                <time>{{ $history->created_at->format('d/m/Y · H:i') }}</time>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
@endsection
