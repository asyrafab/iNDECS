{{-- Evidence, AI explanation and officer assessment remain separate; saved comments are append-only. --}}
@php
    $canComment = $view['journey']?->state === \App\Enums\PaidCheckState::HandedOver && $view['final_check_number'] === $check['number'] && auth()->user()->canPerformLjup('review');
    $isFailedForm = old('feedback_finding_id') === $item['finding_id'];
    $latestFeedback = collect($item['feedback_history'])->last();
@endphp
@if ($latestFeedback)
    <div class="technical-officer-latest">
        <p><strong>Pegawai: {{ $statusLabels[$item['officer_status']] ?? $item['officer_status'] }}</strong></p>
        @if ($item['officer_comment'])<p class="internal-draft-text">{{ $item['officer_comment'] }}</p>@endif
        <small class="muted">{{ $latestFeedback['officer'] }} · {{ $latestFeedback['at']?->setTimezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }}</small>
    </div>
@else
    <p class="muted">Belum ada penilaian pegawai bagi dapatan ini.</p>
@endif
@if ($canComment)
    <form method="POST" action="{{ route('kejuruteraan.findings.feedback', [$application, $item['finding_id']]) }}" class="technical-feedback-form" data-review-form>
        @csrf
        <input type="hidden" name="feedback_finding_id" value="{{ $item['finding_id'] }}">
        <label class="field"><span>Status pegawai</span><select name="officer_status" required>
            <option value="">Pilih status pegawai</option>
            @foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($isFailedForm ? old('officer_status') : $item['officer_status']) === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field"><span>Ulasan teknikal</span><textarea name="officer_comment" rows="5" maxlength="2000" placeholder="Catat penilaian, dokumen/halaman dan pindaan yang diperlukan." data-review-comment>{{ $isFailedForm ? old('officer_comment') : $item['officer_comment'] }}</textarea></label>
        @if ($item['standard_comment'])
            <details class="technical-standard-comment"><summary>Ulasan piawai untuk rujukan</summary><p class="internal-draft-text" data-review-standard-text>{{ $item['standard_comment'] }}</p><button type="button" class="button button-ghost button-small" data-review-use-standard hidden>Masukkan ke dalam ulasan</button></details>
        @endif
        @if ($isFailedForm && $errors->any())<p class="alert alert-error" role="alert">Ulasan ini belum disimpan. Semak status dan panjang ulasan, kemudian cuba semula.</p>@endif
        <div class="technical-feedback-actions"><button class="button button-primary button-small" type="submit">Simpan ulasan<x-icon name="check" /></button><button class="button button-ghost button-small" type="button" data-review-next hidden>Belum diulas seterusnya<x-icon name="arrow-down" /></button></div>
        <small class="muted">Setiap simpanan menambah rekod sejarah. Dapatan AI tidak diubah.</small>
    </form>
@else
    <p class="muted">Paparan baca sahaja. Ulasan boleh direkodkan pada semakan muktamad selepas penyerahan oleh pegawai yang diberi akses semakan.</p>
@endif
@if ($item['feedback_history'] !== [])
    <details class="technical-feedback-history"><summary>Sejarah ulasan ({{ count($item['feedback_history']) }})</summary><ol>
        @foreach (array_reverse($item['feedback_history']) as $entry)
            <li><strong>{{ $entry['officer'] }}</strong><small>{{ $entry['at']?->setTimezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }} · {{ $statusLabels[$entry['status']] ?? $entry['status'] }}</small>@if ($entry['comment'])<p class="internal-draft-text">{{ $entry['comment'] }}</p>@endif</li>
        @endforeach
    </ol></details>
@endif
