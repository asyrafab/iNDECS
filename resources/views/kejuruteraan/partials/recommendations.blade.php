@if (auth()->user()->canPerformLjup('review'))
<section class="content-card" id="syor-dalaman" aria-labelledby="syor-title">
    <div class="card-heading"><h2 id="syor-title">Syor dalaman & draf surat</h2><span class="status-badge status-pending">Draf dalaman</span></div>
    <p>Syor pegawai untuk semakan dalaman. Menyimpan syor tidak mengubah status permohonan atau menghantar surat kepada pemohon.</p>
    @if ($recommendationContext)
        @php($latestRecommendation = $recommendations->first())
        @php($latestLetter = $latestRecommendation?->letterDraft?->content_snapshot ?? [])
        @if ($latestRecommendation && $latestRecommendation->source_hash !== $recommendationContext['source_hash'])
            <p class="alert alert-info">Bukti atau ulasan telah berubah sejak syor terakhir. Semak maklumat terkini sebelum menyediakan semakan syor baharu.</p>
        @endif
        <form method="POST" action="{{ route('kejuruteraan.recommendations.store', $application) }}" class="panel-stack">
            @csrf
            <input type="hidden" name="expected_revision" value="{{ old('expected_revision', $recommendationContext['revision']) }}">
            <input type="hidden" name="source_hash" value="{{ old('source_hash', $recommendationContext['source_hash']) }}">
            <label class="field"><span>Cadangan keputusan</span><select name="outcome" required>
                <option value="">Pilih cadangan</option>
                @foreach ($recommendationOutcomes as $value => $label)<option value="{{ $value }}" @selected(old('outcome', $latestRecommendation?->outcome) === $value)>{{ $label }}</option>@endforeach
            </select></label>
            <label class="field"><span>Sebab syor</span><textarea name="reasons" rows="4" maxlength="6000" required>{{ old('reasons', $latestRecommendation?->reasons) }}</textarea></label>
            <label class="field"><span>Syarat yang dicadangkan (jika berkaitan)</span><textarea name="conditions" rows="3" maxlength="6000">{{ old('conditions', $latestRecommendation?->conditions) }}</textarea></label>
            <label class="field"><span>Rujukan bukti (dokumen, halaman atau dapatan)</span><textarea name="evidence_references" rows="3" maxlength="4000" required>{{ old('evidence_references', $latestRecommendation?->evidence_references) }}</textarea></label>
            @if ($recommendationContext['snapshot']['missing_required_documents'] !== [])
                <label class="field"><span>Penjelasan dokumen wajib yang tiada</span><textarea name="missing_document_explanation" rows="3" maxlength="4000" required>{{ old('missing_document_explanation', $latestRecommendation?->missing_document_explanation) }}</textarea></label>
            @endif
            <h3>Kandungan draf surat</h3>
            <label class="field"><span>Penerima yang dicadangkan</span><textarea name="recipient" rows="3" maxlength="2000" required>{{ old('recipient', $latestLetter['recipient'] ?? '') }}</textarea></label>
            <label class="field"><span>Perkara</span><input name="subject" maxlength="500" required value="{{ old('subject', $latestLetter['subject'] ?? 'Cadangan keputusan bagi '.$application->application_reference) }}"></label>
            <label class="field"><span>Teks draf surat</span><textarea name="letter_body" rows="6" maxlength="12000" aria-describedby="draft-body-help">{{ old('letter_body', $latestLetter['body'] ?? '') }}</textarea></label>
            <p id="draft-body-help" class="muted">Jika dikosongkan, sebab syor digunakan sebagai teks draf. Draf ini belum menggunakan templat surat rasmi JKEJ yang diluluskan.</p>
            <button type="submit" class="button button-primary">Simpan syor & pratonton draf<x-icon name="arrow-right" /></button>
        </form>
    @else
        <p class="muted">Syor boleh disediakan selepas semakan muktamad selesai dan bukti penerimaan penyerahan tersedia.</p>
    @endif
    <h3>Sejarah syor</h3>
    @forelse ($recommendations as $recommendation)
        <details class="precheck-item">
            <summary>Semakan syor {{ $recommendation->revision }} · {{ $recommendationOutcomes[$recommendation->outcome] ?? $recommendation->outcome }} · {{ $recommendation->actor->name }} · {{ $recommendation->created_at->timezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }}</summary>
            <p class="internal-draft-text">{{ $recommendation->reasons }}</p>
            <p class="muted">Berdasarkan semakan AIRS {{ $recommendation->source_snapshot['check_number'] }} yang telah diserahkan.</p>
            <p><strong>Rujukan bukti:</strong></p><p class="internal-draft-text">{{ $recommendation->evidence_references }}</p>
            @if ($recommendation->conditions)<p><strong>Syarat cadangan:</strong></p><p class="internal-draft-text">{{ $recommendation->conditions }}</p>@endif
            @if ($recommendation->missing_document_explanation)<p><strong>Penjelasan dokumen:</strong></p><p class="internal-draft-text">{{ $recommendation->missing_document_explanation }}</p>@endif
            <a class="button button-secondary button-small" href="{{ route('kejuruteraan.recommendations.preview', [$application, $recommendation]) }}">Pratonton draf surat<x-icon name="file-text" /></a>
        </details>
    @empty
        <p class="muted">Belum ada syor dalaman.</p>
    @endforelse
</section>
@endif
