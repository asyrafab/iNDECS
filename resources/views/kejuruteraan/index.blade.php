@extends('layouts.app')
@section('title', 'Permohonan Kejuruteraan')
@section('content')
<div class="page-heading">
    <div><span class="eyebrow">iNDECS Engineering · Jabatan Kejuruteraan</span><h1>Permohonan Kejuruteraan</h1>
        <p>PKT, PKP dan LJUP dalam skop akses anda. Skor AIRS bagi LJUP adalah panduan sahaja.</p></div>
    @if (app(\App\Services\Kejuruteraan\EngineeringApplicationAccess::class)->types(auth()->user(), 'register') !== [])<a class="button button-primary" href="{{ route('kejuruteraan.create') }}"><x-icon name="plus" />Daftar permohonan</a>@endif
</div>
<section class="content-card">
    <div class="card-heading"><h2>Senarai permohonan</h2><span class="muted">{{ number_format($applications->total()) }} rekod</span></div>
    @if ($applications->isEmpty())
        <div class="empty-state compact"><h2>Tiada permohonan</h2><p>Permohonan dalam skop akses anda akan dipaparkan di sini.</p></div>
    @else
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Rujukan</th><th>Jenis</th><th>Tajuk</th><th>Peringkat</th><th>Semakan AIRS</th></tr></thead>
            <tbody>
            @foreach ($applications as $application)
                @php($journey = $journeys->get($application->id))
                <tr>
                    <td><a href="{{ route('kejuruteraan.show', $application) }}">{{ $application->application_reference }}</a></td>
                    <td>{{ $application->application_type_id }}</td>
                    <td>{{ $application->title }}</td>
                    <td>{{ $application->current_stage->label() }}</td>
                    <td>{{ $application->application_type_id === 'LJUP' ? ($journey?->state->label() ?? 'Tiada semakan berbayar') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $applications->links() }}
    @endif
</section>
@endsection
