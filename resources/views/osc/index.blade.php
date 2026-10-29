@extends('layouts.app')
@section('title', 'Mesyuarat OSC')
@section('content')
@php
    $badge = ['draft' => 'status-pending', 'agenda_published' => 'status-info', 'in_progress' => 'status-attention', 'closed' => 'status-compliant', 'minutes_confirmed' => 'status-compliant'];
@endphp
<div class="page-heading heading-with-action">
    <div><span class="eyebrow">eOSC+ · Bilik mesyuarat OSC</span><h1>Mesyuarat OSC</h1><p>Mesyuarat Jawatankuasa OSC yang boleh anda lihat, beserta agenda dan kertas kerja.</p></div>
    @if ($canManage)
        <a href="{{ route('osc.meetings.create') }}" class="button button-primary"><x-icon name="plus" />Mesyuarat baharu</a>
    @endif
</div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<div class="panel-stack">
@if ($application)
<section class="content-card" id="pilih-mesyuarat">
    <div class="card-heading"><h2>Pilih mesyuarat</h2><span class="muted">{{ $application->application_reference }} · {{ $application->title }}</span></div>
    @if ($pickable->isEmpty())
        <div class="empty-state compact"><h3>Tiada mesyuarat yang menerima cadangan</h3><p>Hanya mesyuarat berstatus draf atau agenda diterbitkan, dengan tarikh akan datang, disenaraikan di sini.</p></div>
    @else
        <ol class="agenda-list">
            @foreach ($pickable as $option)
                <li class="agenda-row">
                    <span class="agenda-row-number" aria-hidden="true"><x-icon name="file-plus-2" /></span>
                    <div class="agenda-row-body"><strong>{{ $option->number }}</strong><p class="agenda-row-meta"><span>{{ \App\Services\Osc\OscTime::format($option->scheduled_at) }}</span><span>{{ $option->venue }}</span><span>Tutup cadangan {{ \App\Services\Osc\OscTime::format($option->proposal_cutoff_at) }}</span></p></div>
                    <div class="agenda-row-actions"><a class="button button-primary button-small" href="{{ route('osc.items.create', [$option, 'application' => $application->id]) }}">Bentang ke mesyuarat ini</a></div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
@endif
<section class="content-card">
    <div class="card-heading"><h2>Senarai mesyuarat</h2><span class="muted">{{ number_format($meetings->count()) }} mesyuarat</span></div>
    @if ($meetings->isEmpty())
        <div class="empty-state compact"><h3>Tiada mesyuarat</h3><p>Mesyuarat akan dipaparkan di sini apabila anda dilantik sebagai ahli atau penyampai, mencadangkan perkara, atau perlu menyokong cadangan jabatan anda.</p></div>
    @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Bil.</th><th>Tarikh</th><th>Tempat</th><th>Status</th></tr></thead><tbody>
            @foreach ($meetings as $meeting)
                <tr>
                    <td><a class="reference-link" href="{{ route('osc.meetings.show', $meeting) }}">{{ $meeting->number }}</a></td>
                    <td>{{ \App\Services\Osc\OscTime::format($meeting->scheduled_at) }}</td>
                    <td>{{ $meeting->venue }}</td>
                    <td><span class="status-badge {{ $badge[$meeting->status->value] }}">{{ $meeting->status->label() }}</span>@if ($meeting->awaiting_endorsement_count > 0 && ! in_array($meeting->status->value, ['closed', 'minutes_confirmed'], true)) <span class="status-badge status-pending">{{ number_format($meeting->awaiting_endorsement_count) }} menunggu sokongan anda</span>@endif</td>
                </tr>
            @endforeach
        </tbody></table></div>
    @endif
</section>
</div>
@endsection
