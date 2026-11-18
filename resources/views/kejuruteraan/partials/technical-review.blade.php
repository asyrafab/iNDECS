@php
    $reviewedCount = collect($check['items'])->whereNotNull('officer_status')->count();
    $tone = fn ($status) => match ($status) {
        'DITUNAIKAN' => 'compliant', 'TIDAK_BERKAITAN' => 'info', 'BELUM_DITUNAIKAN' => 'attention', default => 'pending',
    };
@endphp
<div class="technical-review" data-technical-review>
    <div class="technical-review-overview">
        <div><h3>Ulasan teknikal</h3><p class="muted">Semak bukti, nilai dapatan AI, kemudian rekodkan ulasan pegawai.</p></div>
        <dl class="technical-review-progress" aria-label="Kemajuan ulasan">
            <div><dt>Jumlah dapatan</dt><dd>{{ count($check['items']) }}</dd></div>
            <div><dt>Telah diulas</dt><dd>{{ $reviewedCount }}</dd></div>
            <div><dt>Belum diulas</dt><dd>{{ count($check['items']) - $reviewedCount }}</dd></div>
        </dl>
    </div>
    <div class="technical-review-tools" data-review-tools hidden>
        <label class="field"><span>Cari dapatan atau bukti</span><input type="search" data-review-search placeholder="Kod, tajuk, dokumen atau ulasan" aria-controls="review-list-{{ $check['number'] }}"></label>
        <label class="field"><span>Paparan</span><select data-review-filter aria-controls="review-list-{{ $check['number'] }}"><option value="all">Semua dapatan</option><option value="pending">Belum diulas</option><option value="reviewed">Telah diulas</option><option value="attention">Perlu perhatian</option></select></label>
        <label class="field"><span>Bahagian</span><select data-review-section aria-controls="review-list-{{ $check['number'] }}"><option value="">Semua bahagian</option>@foreach (collect($check['items'])->pluck('section')->unique() as $section)<option value="{{ $section }}">{{ $section }}</option>@endforeach</select></label>
        <div class="technical-review-actions"><button type="button" class="button button-secondary button-small" data-review-next>Belum diulas seterusnya<x-icon name="arrow-down" /></button><button type="button" class="button button-ghost button-small" data-review-collapse>Ringkaskan senarai</button></div>
        <p class="muted technical-review-results" data-review-results role="status" aria-live="polite"></p>
    </div>
    <div class="technical-review-desk">
        @include('kejuruteraan.partials.plan-viewer')
        <div class="technical-review-comments">
    <div class="technical-finding-list" id="review-list-{{ $check['number'] }}">
    @foreach ($check['items'] as $item)
        @php
            $reviewed = $item['officer_status'] !== null;
            $attention = ! in_array($item['officer_status'] ?? $item['ai_status'], ['DITUNAIKAN', 'TIDAK_BERKAITAN'], true);
            $searchText = implode(' ', array_filter([$item['rule_id'], $item['title'], $item['section'], $item['requirement'], $item['finding'], $item['officer_comment'], ...array_column($item['evidence'], 'document'), ...array_column($item['evidence'], 'quote')]));
        @endphp
        <details class="technical-finding" id="finding-{{ $item['finding_id'] }}" data-review-finding data-review-corpus="{{ $searchText }}" data-review-section-value="{{ $item['section'] }}" data-review-reviewed="{{ $reviewed ? 'true' : 'false' }}" data-review-attention="{{ $attention ? 'true' : 'false' }}" @if (($loop->first && ! $collapsed) || old('feedback_finding_id') === $item['finding_id']) open @endif>
            <summary class="technical-finding-summary">
                <span class="technical-finding-number">{{ $loop->iteration }}</span>
                <span class="technical-finding-title"><small>{{ $item['rule_id'] }} · {{ $item['section'] }}</small><strong>{{ $item['title'] }}</strong></span>
                <span class="technical-finding-status"><span class="status-badge status-{{ $tone($item['ai_status']) }}">AI: {{ $statusLabels[$item['ai_status']] ?? $item['ai_status'] }}</span><span class="status-badge status-{{ $tone($item['officer_status']) }}">{{ $reviewed ? 'Pegawai: '.($statusLabels[$item['officer_status']] ?? $item['officer_status']) : 'Belum diulas' }}</span><span class="status-badge status-attention" data-review-dirty hidden>Ulasan belum disimpan</span></span>
                <x-icon name="chevron-down" class="technical-finding-chevron" />
            </summary>
            <div class="technical-finding-body">
                <div class="technical-finding-requirement"><span class="eyebrow">Keperluan semakan</span><p>{{ $item['requirement'] }}</p></div>
                <div class="technical-finding-columns">
                    <div class="technical-finding-sources">
                        <section class="technical-evidence" aria-label="Bukti sumber">
                            <h4><x-icon name="file-text" />Bukti sumber</h4>
                            @forelse ($item['evidence'] as $evidence)
                                <div class="technical-evidence-entry">
                                    @if ($evidence['document_id'])<a href="{{ route('kejuruteraan.documents.show', [$application, $evidence['document_id']]) }}{{ $evidence['page'] ? '#page='.$evidence['page'] : '' }}" @if (collect($check['documents'])->contains('id', $evidence['document_id'])) target="technical-document-{{ $check['number'] }}" data-review-evidence="{{ $evidence['document_id'] }}" data-review-page="{{ $evidence['page'] ?? 1 }}" @endif>{{ $evidence['document'] }}@if ($evidence['page']), halaman {{ $evidence['page'] }}@endif<x-icon name="file-text" /></a>
                                    @else<p class="muted">Rujukan dokumen tidak tersedia.</p>@endif
                                    @if ($evidence['quote'])<blockquote>{{ $evidence['quote'] }}</blockquote>@endif
                                </div>
                            @empty<p class="muted">Tiada bukti dokumen dipautkan. Semak dokumen sebelum merekodkan penilaian.</p>@endforelse
                        </section>
                        <details class="technical-ai" open>
                            <summary>Penjelasan AI @if ($item['confidence'] !== null)<span class="status-badge status-ai">Keyakinan: {{ number_format($item['confidence'] * 100) }}%</span>@endif</summary>
                            <div><p>{{ $item['finding'] }}</p>
                                @if ($item['manual_review_reason'])<p class="muted"><strong>Sebab semakan manual:</strong> {{ $item['manual_review_reason'] }}</p>@endif
                                @if ($item['warnings'] !== [])<details><summary>Maklumat analisis</summary><p class="muted">Amaran: {{ implode(', ', $item['warnings']) }}</p></details>@endif
                            </div>
                        </details>
                    </div>
                    <section class="technical-officer" aria-label="Ulasan pegawai">
                        <h4><x-icon name="messages-square" />Ulasan pegawai</h4>
                        @include('kejuruteraan.partials.feedback')
                    </section>
                </div>
            </div>
        </details>
    @endforeach
    </div>
    <div class="technical-review-empty" data-review-empty hidden><h4>Tiada dapatan sepadan</h4><p>Ubah carian atau penapis untuk melihat dapatan lain.</p><button type="button" class="button button-secondary button-small" data-review-reset>Reset carian & penapis</button></div>
        </div>
    </div>
</div>
