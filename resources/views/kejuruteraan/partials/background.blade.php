<section class="content-card review-background" aria-labelledby="review-background-title">
    <div class="card-heading"><h2 id="review-background-title">Latar belakang permohonan</h2><span class="status-badge status-{{ match ($application->current_status->value) { 'approved' => 'compliant', 'rejected' => 'critical', 'returned_for_amendment' => 'attention', default => 'info' } }}">{{ $application->current_status->label() }}</span></div>
    <dl class="review-background-grid">
        <div><dt>Jenis permohonan</dt><dd>{{ $view['background']['type'] ?? $application->application_type_id }}</dd></div>
        <div><dt>Peringkat semasa</dt><dd>{{ $application->current_stage->label() }}</dd></div>
        <div><dt>Status penyerahan / semakan</dt><dd>{{ $view['journey']?->state->label() ?? 'Tiada semakan berbayar' }}</dd></div>
        <div class="review-background-wide"><dt>Alamat / keterangan tapak</dt><dd>{{ $application->description ?: 'Belum direkodkan' }}</dd></div>
        <div><dt>Rujukan luar</dt><dd>{{ $application->external_reference ?: 'Belum direkodkan' }}</dd></div>
        <div><dt>Tarikh permohonan</dt><dd>{{ $application->submission_date?->format('d/m/Y') ?? 'Belum direkodkan' }}</dd></div>
        <div><dt>Diserahkan kepada jabatan</dt><dd>{{ $view['journey']?->handed_over_at?->timezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') ?? 'Belum diserahkan' }}</dd></div>
        <div><dt>Semakan muktamad</dt><dd>{{ $view['final_check_number'] !== null ? 'Semakan '.$view['final_check_number'] : 'Belum ditetapkan' }}</dd></div>
        <div><dt>Jabatan bertanggungjawab</dt><dd>{{ $view['background']['department'] ?? 'Belum direkodkan' }}</dd></div>
        <div><dt>Pegawai ditugaskan</dt><dd>{{ $view['background']['assignee'] ?? 'Belum ditugaskan' }}</dd></div>
        <div><dt>Pengemuka (akaun)</dt><dd>{{ $view['background']['submitter'] ?? 'Belum direkodkan' }}</dd></div>
        <div><dt>Jenis lampu dinyatakan</dt><dd>{{ implode(', ', $view['background']['lighting_types']) ?: 'Belum direkodkan' }}</dd></div>
        @foreach ($view['background']['parties'] as $party)<div><dt>{{ $party['role'] }}</dt><dd>{{ $party['name'] }}</dd></div>@endforeach
    </dl>
    @if ($view['background']['sites'] !== [] || $view['background']['lots'] !== [])
        <details class="review-background-location"><summary>Butiran tapak & lot berdaftar</summary>
            @foreach ($view['background']['sites'] as $site)<p>{{ $site }}</p>@endforeach
            @foreach ($view['background']['lots'] as $lot)<p>Lot {{ $lot }}</p>@endforeach
        </details>
    @endif
    <p class="muted">Maklumat daripada rekod permohonan. Profil diekstrak oleh MATRIX dipaparkan berasingan di bawah.</p>
</section>
