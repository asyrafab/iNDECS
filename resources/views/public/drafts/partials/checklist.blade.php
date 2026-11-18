{{-- Free pre-check checklist (ADR-363, contract §3). Preparation labels only; no findings or approval wording. --}}
@php($pending = collect($checklist['items'])->contains(fn ($item) => $item['label'] === \App\Enums\PrecheckLabel::Pending) || collect($checklist['unsorted'] ?? [])->contains(fn ($file) => $file->precheck_status === 'pending'))
<section class="form-card precheck precheck-window" id="dokumen" data-precheck-url="{{ route('portal.drafts.checklist', $draft) }}" @if ($pending) data-precheck-pending @endif aria-live="polite">
    @include('public.drafts.partials.prefill-data')
    @if (! $checklist['available'])
        <div class="form-section-heading"><div><h2>Dokumen diperlukan</h2></div></div>
        <p class="precheck-note">Senarai semak belum tersedia untuk jenis permohonan ini.</p>
    @else
        <div class="form-section-heading">
            <div><h2>Dokumen diperlukan</h2><p><strong>{{ $checklist['required_ready'] }}/{{ $checklist['required_total'] }} dokumen wajib sedia</strong></p></div>
        </div>
        <p class="precheck-note">Pilih nama dokumen untuk melihat penerangan atau memuat naik fail.</p>
        @foreach (['Wajib' => true, 'Pilihan' => false] as $group => $required)
        @php($groupItems = array_filter($checklist['items'], fn ($item) => $item['is_required'] === $required))
        @continue($groupItems === [])
        <h3 class="precheck-group-title">{{ $group }}</h3>
        <ul class="precheck-list precheck-compact-list">
            @foreach ($groupItems as $item)
                <li>
                    <a class="precheck-document" href="#document-{{ $item['code'] }}" data-portal-open="document-{{ $item['code'] }}" aria-haspopup="dialog">
                        @include('public.drafts.partials.status-icon', ['label' => $item['label']])
                        <span>{{ $item['name'] }}</span><x-icon class="precheck-document-arrow" name="arrow-up-right" />
                    </a>
                    <dialog class="portal-dialog precheck-dialog" id="document-{{ $item['code'] }}" aria-labelledby="document-title-{{ $item['code'] }}">
                        <div class="portal-dialog-head"><span class="eyebrow">Dokumen diperlukan</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup butiran dokumen"><x-icon name="x" /></button></div>
                        <h2 id="document-title-{{ $item['code'] }}">{{ $item['name'] }}</h2>
                        <div class="precheck-detail-meta"><span class="status-badge {{ $item['label']->badgeClass() }}">{{ $item['label']->value }}</span><span>{{ $item['is_required'] ? 'Wajib' : 'Pilihan' }}@if ($item['conditional']) · berdasarkan butiran projek @endif</span></div>
                        @if ($item['guidance'])<p class="precheck-guidance">{{ $item['guidance'] }}</p>@endif
                        @include('public.drafts.partials.item-files')
                    </dialog>
                </li>
            @endforeach
        </ul>
        @endforeach
        <p class="precheck-note precheck-contract">Semakan ini membantu menyediakan dokumen sebelum penyerahan. Kandungan teknikal dan pematuhan akan disemak dalam proses rasmi.</p>
        @error('file')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        @include('public.drafts.partials.unsorted')
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
            @elseif (($checklist['unsorted'] ?? []) !== [])
                <div><strong>Fail belum dipadankan</strong><small>{{ \App\Services\PaidCheck\CheckPaymentService::UNSORTED }}</small></div>
                <a class="button button-secondary" href="#belum-dipadankan">Lihat fail belum dipadankan</a>
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
