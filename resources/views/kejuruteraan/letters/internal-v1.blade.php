@extends('layouts.app')
@section('title', 'Pratonton draf surat')
@section('content')
<a class="back-link" href="{{ route('kejuruteraan.show', $application) }}#syor-dalaman"><x-icon name="arrow-left" />Kembali ke syor dalaman</a>
<article class="content-card panel-stack" aria-labelledby="draft-title">
    <div class="alert alert-info"><x-icon name="file-text" /><strong>DRAF — BUKAN SURAT RASMI</strong></div>
    <header class="public-brand"><img class="public-crest" src="{{ asset('img/mbsp.png') }}" alt="Majlis Bandaraya Seberang Perai"><span class="public-wordmark"><x-mark /><span>iNDECS<small>Jabatan Kejuruteraan</small></span></span></header>
    <h1 id="draft-title">{{ $letter['subject'] }}</h1>
    <p>Rujukan permohonan: <strong>{{ $letter['application_reference'] }}</strong><br>{{ $letter['title'] }}</p>
    <p class="internal-draft-text">{{ $letter['recipient'] }}</p>
    <p><strong>Cadangan keputusan:</strong> {{ $letter['outcome_label'] }}</p>
    <p class="internal-draft-text">{{ $letter['body'] }}</p>
    @if ($letter['conditions'])<h2>Syarat yang dicadangkan</h2><p class="internal-draft-text">{{ $letter['conditions'] }}</p>@endif
    <p class="muted">Disediakan oleh {{ $letter['prepared_by'] }} · Semakan syor {{ $letter['revision'] }} · {{ $draft->created_at->timezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }}</p>
    <p class="muted">Pratonton dalaman sahaja. Tiada nombor surat rasmi, tandatangan atau pengesahan rasmi dikeluarkan. Kandungan ini belum dihantar kepada pemohon.</p>
</article>
@endsection
