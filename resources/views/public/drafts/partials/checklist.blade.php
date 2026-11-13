{{-- Free pre-check checklist (ADR-363, contract §3). Preparation labels only; no findings or approval wording. --}}
@php($pending = collect($checklist['items'])->contains(fn ($item) => $item['label'] === \App\Enums\PrecheckLabel::Pending))
<section class="form-card precheck" id="dokumen" data-precheck-url="{{ route('portal.drafts.checklist', $draft) }}" @if ($pending) data-precheck-pending @endif aria-live="polite">
    @if (! $checklist['available'])
        <div class="form-section-heading"><div><h2>Dokumen diperlukan</h2></div></div>
        <p class="precheck-note">Senarai semak belum tersedia untuk jenis permohonan ini.</p>
    @else
        <div class="form-section-heading">
            <div><h2>Dokumen diperlukan</h2><p><strong>{{ $checklist['required_ready'] }}/{{ $checklist['required_total'] }} dokumen wajib sedia</strong></p></div>
        </div>
        <p class="precheck-note">Semakan ini membantu menyediakan dokumen sebelum penyerahan. Kandungan teknikal dan pematuhan akan disemak dalam proses rasmi.</p>
        <p class="precheck-desktop-note"><x-icon name="circle-alert" />Muat naik dokumen menggunakan komputer.</p>
        <ul class="precheck-list">
            @foreach ($checklist['items'] as $item)
                <li class="precheck-item">
                    <div class="precheck-item-head">
                        <div>
                            <h3>{{ $item['name'] }}</h3>
                            <p>{{ $item['is_required'] ? 'Wajib' : 'Pilihan' }}@if ($item['conditional']) · berdasarkan butiran projek @endif</p>
                        </div>
                        <span class="status-badge {{ $item['label']->badgeClass() }}">{{ $item['label']->value }}</span>
                    </div>
                    @if ($item['guidance'])<p class="precheck-guidance">{{ $item['guidance'] }}</p>@endif
                    @if ($item['document'])
                        <p class="precheck-file"><x-icon name="file-text" /><a href="{{ route('portal.drafts.documents.download', [$draft, $item['document']]) }}">{{ $item['document']->original_name }}</a>
                            @if ($item['label'] === \App\Enums\PrecheckLabel::Unreadable) · Fail tidak dapat dibaca. Sila muat naik semula.
                            @elseif ($item['label'] === \App\Enums\PrecheckLabel::NeedsConfirmation) · {{ $item['document']->ocr_unavailable ? 'Kandungan tidak dapat disemak secara automatik.' : 'Perkataan yang dijangka tidak ditemui.' }} Pastikan ini dokumen yang betul.
                            @endif
                        </p>
                    @endif
                    <div class="precheck-actions">
                        @if ($item['label'] === \App\Enums\PrecheckLabel::NeedsConfirmation)
                            <form method="POST" action="{{ route('portal.drafts.documents.confirm', [$draft, $item['document']]) }}">@csrf<button class="button button-secondary button-small" type="submit"><x-icon name="check" />Saya sahkan dokumen ini betul</button></form>
                        @endif
                        <form class="precheck-upload" method="POST" action="{{ route('portal.drafts.documents.store', [$draft, $item['code']]) }}" enctype="multipart/form-data">
                            @csrf
                            <label><span class="sr-only">Fail untuk {{ $item['name'] }}</span><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"></label>
                            <button class="button button-secondary button-small" type="submit"><x-icon name="file-plus-2" />{{ $item['document'] ? 'Muat naik semula' : 'Muat naik' }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        @php($airsEnabled = (config('airs.modules.'.$draft->application_type_id.'.enabled') ?? false) === true)
        @php($fee = \App\Services\PaidCheck\CheckPaymentService::feeSen())
        @unless ($checkResult ?? null)
        <div class="precheck-gate {{ $checklist['ready'] ? 'is-ready' : '' }}">
            @if (! $airsEnabled)
                <div><strong>{{ $checklist['ready'] ? 'Sedia untuk semakan AIRS' : 'Lengkapkan dokumen wajib untuk meneruskan' }}</strong>
                    <small>Semakan AIRS berbayar dan pengesahan pegawai akan dibuka dalam fasa seterusnya.</small></div>
                <button class="button button-primary" type="button" disabled>Bayar semakan AIRS · dibuka tidak lama lagi</button>
            @elseif ($fee === null)
                <div><strong>{{ $checklist['ready'] ? 'Sedia untuk semakan AIRS' : 'Lengkapkan dokumen wajib untuk meneruskan' }}</strong>
                    <small>Semakan AIRS berbayar dibuka sebaik fi ditetapkan oleh MBSP.</small></div>
                <button class="button button-primary" type="button" disabled>Fi semakan belum ditetapkan</button>
            @else
                <form method="POST" action="{{ route('portal.drafts.payment.start', $draft) }}" class="precheck-pay">
                    @csrf
                    <div><strong>{{ $checklist['ready'] ? 'Sedia untuk semakan AIRS' : 'Lengkapkan dokumen wajib untuk meneruskan' }}</strong>
                        <small>Satu bayaran meliputi semakan AIRS dan satu semakan semula selepas pindaan. Keputusan dipaparkan sebelum penyerahan muktamad.</small></div>
                    @unless ($checklist['ready'])
                        <label class="checkbox-field"><input type="checkbox" name="acknowledge_missing" value="1" required><span>Saya faham dokumen wajib belum lengkap dan pegawai akan menilainya.</span></label>
                    @endunless
                    @error('acknowledge_missing')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    @error('payment')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                    <button class="button button-primary" type="submit">Bayar semakan AIRS (RM{{ number_format($fee / 100, 2) }})</button>
                </form>
            @endif
        </div>
        @endunless
    @endif
</section>
