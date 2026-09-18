@extends('layouts.app')

@section('title', 'Ringkasan')

@section('content')
    <div class="page-heading heading-with-action">
        <div>
            <span class="eyebrow">Selamat datang</span>
            <h1>Ringkasan PKM</h1>
            <p>Pantau permohonan Kebenaran Merancang dan tindakan yang perlu dimulakan.</p>
        </div>
        <a href="{{ route('perancang.create') }}" class="button button-primary">
            <span>＋</span> Daftar permohonan
        </a>
    </div>

    <section class="metric-grid" aria-label="Metrik permohonan">
        <article class="metric-card metric-ink">
            <span class="metric-icon">▤</span>
            <div>
                <span>Jumlah PKM</span>
                <strong>{{ number_format($metrics['total']) }}</strong>
                <small>Semua permohonan berdaftar</small>
            </div>
        </article>
        <article class="metric-card metric-blue">
            <span class="metric-icon">↗</span>
            <div>
                <span>Baharu dihantar</span>
                <strong>{{ number_format($metrics['submitted']) }}</strong>
                <small>Menunggu semakan dimulakan</small>
            </div>
        </article>
        <article class="metric-card metric-amber">
            <span class="metric-icon">◷</span>
            <div>
                <span>Dalam semakan</span>
                <strong>{{ number_format($metrics['in_review']) }}</strong>
                <small>Semakan kelengkapan aktif</small>
            </div>
        </article>
        <article class="metric-card metric-green">
            <span class="metric-icon">✓</span>
            <div>
                <span>Diluluskan</span>
                <strong>{{ number_format($metrics['approved']) }}</strong>
                <small>Rekod kelulusan PKM</small>
            </div>
        </article>
    </section>

    <section class="content-card">
        <div class="card-heading">
            <div>
                <span class="eyebrow">Aktiviti terkini</span>
                <h2>Permohonan terbaru</h2>
            </div>
            <a href="{{ route('perancang.index') }}" class="text-link">Lihat semua <span>→</span></a>
        </div>

        @if ($recentApplications->isEmpty())
            <div class="empty-state">
                <span class="empty-icon">⌁</span>
                <h3>Belum ada permohonan PKM</h3>
                <p>Daftar permohonan pertama untuk mula menguji aliran kerja modul ini.</p>
                <a href="{{ route('perancang.create') }}" class="button button-primary">Daftar PKM pertama</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rujukan</th>
                            <th>Cadangan pembangunan</th>
                            <th>Lokasi</th>
                            <th>Peringkat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentApplications as $application)
                            <tr>
                                <td><a class="reference-link" href="{{ route('perancang.show', $application) }}">{{ $application->application_reference }}</a></td>
                                <td>
                                    <strong class="table-title">{{ $application->title }}</strong>
                                    <small>{{ $application->submission_date->format('d/m/Y') }}</small>
                                </td>
                                <td>{{ $application->sites->first()?->mukim ?? '—' }}</td>
                                <td>{{ $application->current_stage->label() }}</td>
                                <td><span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
