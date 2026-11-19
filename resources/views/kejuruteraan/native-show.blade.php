@extends('layouts.app')
@section('title', $application->application_reference)
@section('content')
<div class="page-heading"><a class="back-link" href="{{ route('kejuruteraan.index') }}">Kembali ke senarai</a><span class="eyebrow">{{ $application->applicationType->name }}</span><h1>{{ $application->application_reference }}</h1><p>{{ $application->title }}</p></div>
<div class="panel-stack">
<section class="content-card"><div class="card-heading"><h2>Ringkasan permohonan</h2><span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></div>
<dl class="detail-grid"><div><dt>Jenis permohonan</dt><dd>{{ $application->application_type_id }}</dd></div><div><dt>Tarikh penerimaan</dt><dd>{{ $application->submission_date->format('d/m/Y') }}</dd></div><div><dt>Peringkat</dt><dd>{{ $application->current_stage->label() }}</dd></div><div><dt>Rujukan OSC</dt><dd>{{ $application->external_reference ?? '—' }}</dd></div><div class="detail-span-2"><dt>Keterangan cadangan</dt><dd>{{ $application->description ?? '—' }}</dd></div><div class="detail-span-2"><dt>Catatan teknikal</dt><dd>{{ $notes ?? '—' }}</dd></div></dl>
@if (app(\App\Services\Tasks\OfficerTaskAccess::class)->canAssign(auth()->user(), $application))<a class="button button-secondary" href="{{ route('tasks.create', ['application' => $application->id]) }}">Cipta tugasan</a>@endif
</section>
<section class="content-card"><div class="card-heading"><h2>Pemohon dan perunding</h2></div><dl class="detail-grid">
@foreach ($application->applicationParties as $party)<div><dt>{{ $party->role === 'applicant' ? 'Pemohon' : 'Perunding' }}</dt><dd>{{ $party->submitted_snapshot['name'] ?? '—' }}<br>{{ $party->submitted_snapshot['address'] ?? '' }}</dd></div>@endforeach
</dl></section>
<section class="content-card"><div class="card-heading"><h2>Tapak dan lot</h2></div><dl class="detail-grid">
@foreach ($application->sites as $site)<div><dt>Alamat tapak</dt><dd>{{ $site->development_address }}</dd></div><div><dt>Daerah / mukim</dt><dd>{{ $site->district }} / {{ $site->mukim }}</dd></div>@endforeach
<div class="detail-span-2"><dt>No. lot</dt><dd>{{ $application->lots->pluck('lot_number')->join(', ') }}</dd></div></dl></section>
<section class="content-card"><div class="card-heading"><h2>Sejarah peringkat</h2></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Tarikh</th><th>Peringkat</th><th>Status</th><th>Pegawai</th></tr></thead><tbody>
@foreach ($application->stageHistory as $history)<tr><td>{{ $history->created_at->format('d/m/Y H:i') }}</td><td>{{ $history->new_stage->label() }}</td><td>{{ $history->new_status->label() }}</td><td>{{ $history->actor?->name ?? '—' }}</td></tr>@endforeach
</tbody></table></div></section>
</div>
@endsection
