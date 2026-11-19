@extends('layouts.app')

@section('title', 'Dashboard jabatan')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">Dashboard jabatan · {{ implode(' / ', $types) }}</span>
            <h1>{{ $name }}</h1>
            <p>Ringkasan permohonan dan tugasan dalam skop akses anda.</p>
        </div>
        <div class="inline-form">
            <a href="{{ route($department.'.index') }}" class="button button-secondary"><x-icon name="files" />Semua permohonan</a>
            @if ($registerUrl)<a href="{{ $registerUrl }}" class="button button-primary"><x-icon name="plus" />Daftar permohonan</a>@endif
        </div>
    </div>
    @if ($department === 'kejuruteraan')
        <p class="muted">Permohonan PKT, PKP dan LJUP dipaparkan mengikut skop akses anda.</p>
    @endif
    <section class="metric-grid" aria-label="Metrik permohonan">
        @foreach ([['total', 'Jumlah permohonan', 'files', 'ink'], ['submitted', 'Dihantar', 'arrow-up-right', 'blue'], ['in_review', 'Dalam semakan', 'clock-3', 'amber'], ['approved', 'Diluluskan', 'check', 'green']] as [$key, $label, $icon, $colour])
            <article class="metric-card metric-{{ $colour }}">
                <span class="metric-icon"><x-icon :name="$icon" /></span>
                <div><span>{{ $label }}</span><strong>{{ number_format($metrics[$key]) }}</strong><small>{{ $key === 'total' ? 'Semua status dalam skop anda' : 'Status permohonan semasa' }}</small></div>
            </article>
        @endforeach
    </section>
    <div class="panel-stack">
    <div class="department-dashboard-grid">
        <div class="panel-stack">
    <section class="content-card">
        <div class="card-heading"><div><span class="eyebrow">{{ $canAssign ? 'Tugasan pasukan dalam skop anda' : 'Tugasan saya' }}</span><h2>Kerja yang perlu diselesaikan</h2></div>
            @if ($hasTasks)<a class="text-link" href="{{ route('tasks.index', ['department' => $departmentId, 'scope' => $canAssign ? 'team' : 'mine']) }}">Buka tugasan pegawai <x-icon name="arrow-right" /></a>@endif
        </div>
        @if (! $hasTasks)
            <p>Akses tugasan pegawai belum tersedia untuk peranan anda dalam jabatan ini.</p>
        @else
            <p>{{ number_format($taskMetrics['accessible']) }} tugasan aktif · {{ number_format($taskMetrics['mine']) }} ditugaskan kepada anda · {{ number_format($taskMetrics['overdue']) }} melepasi tarikh sasaran.</p>
            <p class="muted">Tarikh sasaran ditetapkan secara manual; bilangan ini bukan ukuran SLA rasmi.</p>
            @if ($tasks->isEmpty())<div class="empty-state"><h3>Tiada tugasan aktif</h3><p>Tugasan baharu akan muncul selepas diagihkan oleh penyelia.</p></div>
            @else
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Tugasan</th><th>Permohonan</th><th>Pegawai</th><th>Tarikh sasaran</th><th>Status</th></tr></thead>
                    <tbody>@foreach ($tasks as $task)<tr><td><a class="reference-link" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td><td>{{ $task->application->application_reference }}</td><td>{{ $task->assignee->name }}</td><td>{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td><td><span class="status-badge status-info">{{ \App\Models\OfficerTask::STATUSES[$task->status] }}</span></td></tr>@endforeach</tbody>
                </table></div>
            @endif
        @endif
    </section>
        </div>
        <div class="panel-stack">
    <section class="content-card">
        <div class="card-heading"><div><span class="eyebrow">Rekod semasa</span><h2>Pecahan status permohonan</h2></div></div>
        <dl class="detail-grid">
            @foreach ($statuses as $row)<div><dt>{{ $row['status']->label() }}</dt><dd>{{ number_format($row['count']) }}</dd></div>@endforeach
        </dl>
    </section>
        </div>
    </div>
    <section class="content-card">
        <div class="card-heading"><div><span class="eyebrow">Aktiviti terkini</span><h2>Permohonan terbaru didaftarkan</h2></div><a href="{{ route($department.'.index') }}" class="text-link">Lihat semua <x-icon name="arrow-right" /></a></div>
        @if ($recentApplications->isEmpty())
            <div class="empty-state"><span class="empty-icon"><x-icon name="folder-open" /></span><h3>Belum ada permohonan</h3><p>Permohonan dalam skop akses anda akan dipaparkan di sini.</p></div>
        @else
            <div class="table-wrap"><table class="data-table">
                <thead><tr><th>Rujukan / jenis</th><th>Cadangan pembangunan</th><th>Tarikh dihantar</th><th>Peringkat</th><th>Status</th></tr></thead>
                <tbody>@foreach ($recentApplications as $application)
                    <tr><td><a class="reference-link" href="{{ route($department.'.show', $application) }}">{{ $application->application_reference }}</a><small>{{ $application->application_type_id }}</small></td>
                        <td><strong class="table-title">{{ $application->title }}</strong></td><td>{{ $application->submission_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $application->current_stage->label() }}</td><td><span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </section>
    </div>
@endsection
