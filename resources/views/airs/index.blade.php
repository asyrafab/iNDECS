@extends('layouts.app')
@section('title', 'AIRS · Semakan pelan')
@section('content')
<div class="page-heading"><div><h1>AIRS · Semakan pelan</h1><p>Semakan dokumen berasaskan bukti. Rekod dan keputusan rasmi kekal dalam iNDECS.</p></div></div>
<div class="alert alert-info">Semakan teknikal memerlukan serahan rasmi, pengesahan bayaran dan modul semakan yang tersedia. AMIRA ialah perkhidmatan berasingan.</div>
<section class="content-card"><h2>Permintaan semakan terkini</h2><div class="table-wrap"><table class="data-table"><thead><tr><th>Permohonan</th><th>Modul</th><th>Status semakan</th><th>Tindakan</th></tr></thead><tbody>
@forelse ($reviews as $review)<tr><td>{{ $review->application->application_reference }}</td><td>{{ $review->module_code }}</td><td>{{ ['queued' => 'Dalam baris gilir', 'processing' => 'Sedang diproses', 'completed' => 'Dapatan tersedia', 'failed' => 'Memerlukan tindakan', 'blocked' => 'Perlu konfigurasi', 'superseded' => 'Versi terdahulu'][$review->state] ?? $review->state }}</td><td><a href="{{ route('airs.show', $review) }}">Lihat semakan</a></td></tr>
@empty<tr><td colspan="4">Belum ada permintaan semakan untuk rekod yang anda boleh akses. Buka permohonan untuk melihat kelayakan semakan AIRS.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
