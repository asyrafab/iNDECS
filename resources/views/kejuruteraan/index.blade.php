@extends('layouts.app')
@section('title', 'Permohonan LJUP')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">iNDECS Engineering · Jabatan Kejuruteraan</span><h1>Permohonan Lampu Jalan (LJUP)</h1>
        <p>Permohonan yang telah melalui semakan AIRS. Skor adalah panduan sahaja; keputusan rasmi dibuat oleh pegawai.</p></div>
</div>
<section class="content-card">
    <div class="card-heading"><h2>Senarai permohonan</h2><span class="muted">{{ number_format($applications->total()) }} rekod</span></div>
    @if ($applications->isEmpty())
        <div class="empty-state compact"><h2>Tiada permohonan LJUP</h2><p>Permohonan akan muncul selepas pemohon membayar semakan AIRS.</p></div>
    @else
        <table class="data-table">
            <thead><tr><th>Rujukan</th><th>Tajuk</th><th>Peringkat</th><th>Semakan AIRS</th></tr></thead>
            <tbody>
            @foreach ($applications as $application)
                @php($journey = $journeys->get($application->id))
                <tr>
                    <td><a href="{{ route('kejuruteraan.show', $application) }}">{{ $application->application_reference }}</a></td>
                    <td>{{ $application->title }}</td>
                    <td>{{ $application->current_stage->label() }}</td>
                    <td>{{ $journey?->state->label() ?? 'Tiada semakan berbayar' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $applications->links() }}
    @endif
</section>
@endsection
