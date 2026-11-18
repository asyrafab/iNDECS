@extends('layouts.app')
@section('title', $application->application_reference)
@section('content')
@php($focusReview = request()->query('review') !== 'overview')
<div class="officer-review-page" data-review-page data-review-mode="{{ $focusReview ? 'focus' : 'overview' }}">
@include('kejuruteraan.partials.task-status')
@php($statusLabels = ['DITUNAIKAN' => 'Ditunaikan', 'BELUM_DITUNAIKAN' => 'Belum ditunaikan', 'TIDAK_BERKAITAN' => 'Tidak berkaitan', 'TIDAK_DAPAT_DIKESAN' => 'Tidak dapat dikesan', 'PERLU_SEMAKAN_MANUAL' => 'Perlu semakan manual', 'PERLU_SEMAKAN_MANUAL_PEGAWAI' => 'Perlu semakan pegawai'])
<div class="record-heading"><div>
    <a href="{{ route('kejuruteraan.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a>
    <div class="record-reference-row"><span class="record-type">LJUP</span><span class="record-reference">{{ $application->application_reference }}</span>
        <span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></div>
    <h1>{{ $application->title }}</h1>
</div></div>
<nav class="review-mode-nav" aria-label="Paparan semakan"><a class="button {{ $focusReview ? 'button-primary' : 'button-secondary' }} button-small" href="{{ route('kejuruteraan.show', ['application' => $application->id, 'review' => 'focus']) }}" @if ($focusReview) aria-current="page" @endif>Semakan fokus</a><a class="button {{ !$focusReview ? 'button-primary' : 'button-secondary' }} button-small" href="{{ route('kejuruteraan.show', ['application' => $application->id, 'review' => 'overview']) }}" @if (!$focusReview) aria-current="page" @endif>Gambaran keseluruhan</a></nav>
<details class="review-context-disclosure" @if (!$focusReview) open @endif><summary>Latar belakang permohonan <span class="muted">{{ $application->current_stage->label() }} · {{ $view['journey']?->state->label() ?? 'Tiada semakan berbayar' }}</span></summary>@include('kejuruteraan.partials.background')</details>
@if ($errors->any())<div class="alert alert-error" role="alert"><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
@if (auth()->user()->canPerformLjup('review'))
    <a class="button button-secondary button-small" href="#syor-dalaman"><x-icon name="file-text" />Syor dalaman & draf surat</a>
@endif

@if ($view['missing_required'] !== [])
    <details class="review-missing-documents alert alert-info" @if (!$focusReview) open @endif><summary>Dokumen wajib tidak dilampirkan ({{ count($view['missing_required']) }})</summary>
        <ul>@foreach ($view['missing_required'] as $name)<li>{{ $name }}</li>@endforeach</ul>
        <small>Permohonan tetap diterima. Pegawai menentukan pengecualian atau tindakan seterusnya.</small></details>
@endif

@foreach ($view['checks'] as $check)
@php($collapsed = $view['final_check_number'] !== null && $view['final_check_number'] !== $check['number'])
@if ($collapsed)
<details class="content-card" data-review-check-history id="semakan-{{ $check['number'] }}"><summary>Semakan {{ $check['number'] }} — semakan terdahulu (sejarah, baca sahaja)</summary>
@else
<section class="content-card" id="semakan-{{ $check['number'] }}">
@endif
    <div class="card-heading"><h2>Semakan {{ $check['number'] }}@if ($view['final_check_number'] === $check['number']) · muktamad @endif</h2>
        <span class="muted">{{ $check['status'] }}</span></div>
    @if ($check['status'] === 'COMPLETED')
        <details class="review-analysis-details" @if (!$focusReview) open @endif><summary>Ringkasan analisis & skor (nasihat)</summary>
        <p>Skor kesediaan dokumen: <strong>{{ $check['score'] }}</strong>/100
            @if ($check['profile_stale']) · <strong>Skor ditangguhkan: profil dibetulkan</strong> — dapatan dan kebolehgunaan perlu disemak semula.
            @elseif ($check['officer_score'] !== $check['score']) · selepas ulasan pegawai: <strong>{{ $check['officer_score'] }}</strong>/100 @endif</p>
        <p class="muted">Keputusan bayangan — tidak dikuatkuasakan: {{ $check['shadow_outcome'] === 'BELOW_THRESHOLD' ? 'di bawah ambang' : 'mencapai ambang' }} (ambang {{ config('airs.score_threshold') }})</p>
        <p class="muted">Kos MATRIX (anggaran pelanggan, bukan invois): USD {{ number_format((float) ($check['usage']['estimated_cost_usd'] ?? 0), 4) }}</p>
        </details>
        @include('kejuruteraan.partials.technical-review')
        <details class="review-secondary-details" @if (!$focusReview) open @endif><summary>Profil diekstrak oleh MATRIX & semakan medan</summary>@includeIf('kejuruteraan.partials.fields', ['check' => $check])</details>
    @elseif ($check['status'] === 'FAILED')
        <p>Semakan gagal ({{ $check['error_code'] }}). Semakan boleh dijalankan semula tanpa menggunakan semakan berbayar.</p>
    @else
        <p>Semakan sedang dijalankan.</p>
    @endif
@if ($collapsed)</details>@else</section>@endif
@endforeach
<details class="review-secondary-details" @if (!$focusReview) open @endif><summary>Syor dalaman & draf surat</summary>@include('kejuruteraan.partials.recommendations')</details>
</div>
<script src="{{ asset('js/officer-review.js') }}" defer></script>
<script src="{{ asset('js/officer-documents.js') }}" defer></script>
<script src="{{ asset('js/officer-panel-split.js') }}" defer></script>
@endsection
