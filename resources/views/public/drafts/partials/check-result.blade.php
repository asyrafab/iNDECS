{{-- Applicant-safe AIRS check result (design §5.6). Data comes only from ApplicantCheckPresenter. --}}
@php($running = collect($result['checks'])->contains(fn ($check) => $check['status'] === 'running') || $result['checks'] === [])
<section class="form-card precheck" id="semakan-airs" @if ($running) data-check-poll-url="{{ route('portal.drafts.check', $draft) }}" @endif aria-live="polite">
    <div class="form-section-heading"><div><h2>Semakan AIRS</h2><p><strong>{{ $result['state']->label() }}</strong></p></div></div>
    <p class="precheck-note">Keputusan ini membantu anda menyemak dokumen sebelum penyerahan muktamad. Skor adalah panduan sahaja dan bukan keputusan kelulusan; keputusan rasmi dibuat oleh pegawai.</p>

    @if ($result['checks'] === [])
        <p>Semakan AIRS sedang dijalankan. Halaman ini dikemas kini secara automatik.</p>
    @endif

    @foreach ($result['checks'] as $check)
        <article class="precheck-item">
            <div class="precheck-item-head"><div><h3>Semakan {{ $check['number'] }}</h3>
                @if ($check['status'] === 'running')
                    <p>Semakan AIRS sedang dijalankan. Halaman ini dikemas kini secara automatik.</p>
                @elseif ($check['status'] === 'failed')
                    <p>{{ $check['message'] }}</p>
                @else
                    <p>Skor kesediaan dokumen: <strong>{{ $check['score'] }}</strong>/100</p>
                @endif
            </div></div>
            @if ($check['missing_required'] !== [])
                <div class="alert alert-info"><x-icon name="circle-alert" /><div><strong>Dokumen wajib belum dilampirkan</strong>
                    <ul>@foreach ($check['missing_required'] as $name)<li>{{ $name }}</li>@endforeach</ul>
                    <small>Anda masih boleh meneruskan; pegawai akan menilai jika terdapat pengecualian.</small></div></div>
            @endif
            @if ($check['items'] !== [])
                <ul class="precheck-list">
                    @foreach ($check['items'] as $item)
                        <li class="precheck-item">
                            <div class="precheck-item-head"><div><h4>{{ $item['title'] }}</h4><p>{{ $item['section'] }}</p></div><span class="precheck-label">{{ $item['status_label'] }}</span></div>
                            @if ($item['standard_comment'])<p>{{ $item['standard_comment'] }}</p>@endif
                            @foreach ($item['evidence'] as $evidence)
                                <a href="{{ route('portal.drafts.check.document', [$draft, $evidence['document_id']]) }}{{ $evidence['page'] ? '#page='.$evidence['page'] : '' }}" target="_blank" rel="noopener">{{ $evidence['document'] }}@if ($evidence['page']), halaman {{ $evidence['page'] }}@endif</a>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
            @endif
        </article>
    @endforeach

    @if ($result['state'] === \App\Enums\PaidCheckState::AwaitingApplicant)
        <div class="precheck-gate is-ready">
            <div><strong>Tarikh akhir pindaan: {{ $result['amendment_due_at']?->setTimezone('Asia/Kuala_Lumpur')->format('d/m/Y, g:i a') }}</strong>
                <small>Anda boleh membuat satu pindaan dan semakan semula. Jika tiada tindakan sebelum tarikh akhir, Semakan 1 menjadi muktamad dan permohonan diserahkan kepada pegawai.</small></div>
            @if ($result['can_amend'])
                <form method="POST" action="{{ route('portal.drafts.check.amend', $draft) }}">@csrf<button class="button button-primary" type="submit">Hantar pindaan untuk semakan semula</button></form>
            @endif
            @if ($result['can_submit'])
                <form method="POST" action="{{ route('portal.drafts.check.submit', $draft) }}">@csrf<button class="button button-secondary" type="submit">Hantar tanpa pindaan</button></form>
            @endif
            @if ($result['can_extend'])
                <form method="POST" action="{{ route('portal.drafts.check.extend', $draft) }}">@csrf<button class="button button-secondary" type="submit">Lanjutkan 5 hari bekerja</button></form>
            @endif
        </div>
    @endif
    @if ($running)
        <script>(() => { const s = document.currentScript.closest('[data-check-poll-url]'); if (!s) return; setTimeout(() => fetch(s.dataset.checkPollUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r => r.ok ? r.text() : null).then(html => { if (html) s.outerHTML = html; }), 10000); })();</script>
    @endif
</section>
