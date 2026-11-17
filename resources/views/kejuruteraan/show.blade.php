@extends('layouts.app')
@section('title', $application->application_reference)
@section('content')
@php($statusLabels = ['DITUNAIKAN' => 'Ditunaikan', 'BELUM_DITUNAIKAN' => 'Belum ditunaikan', 'TIDAK_BERKAITAN' => 'Tidak berkaitan', 'TIDAK_DAPAT_DIKESAN' => 'Tidak dapat dikesan', 'PERLU_SEMAKAN_MANUAL' => 'Perlu semakan manual', 'PERLU_SEMAKAN_MANUAL_PEGAWAI' => 'Perlu semakan pegawai'])
<div class="record-heading"><div>
    <a href="{{ route('kejuruteraan.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a>
    <div class="record-reference-row"><span class="record-type">LJUP</span><span class="record-reference">{{ $application->application_reference }}</span>
        <span class="status-badge status-{{ $application->current_status->value }}">{{ $application->current_status->label() }}</span></div>
    <h1>{{ $application->title }}</h1><p>{{ $application->description }}</p>
    <p class="muted">{{ $view['journey']?->state->label() ?? 'Tiada semakan berbayar' }} · Peringkat: {{ $application->current_stage->label() }}</p>
</div></div>
@if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="alert alert-error" role="alert"><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
@if (auth()->user()->canPerformLjup('review'))
    <a class="button button-secondary button-small" href="#syor-dalaman"><x-icon name="file-text" />Syor dalaman & draf surat</a>
@endif

@if ($view['missing_required'] !== [])
    <div class="alert alert-info"><x-icon name="circle-alert" /><div><strong>Dokumen wajib tidak dilampirkan</strong>
        <ul>@foreach ($view['missing_required'] as $name)<li>{{ $name }}</li>@endforeach</ul>
        <small>Permohonan tetap diterima. Pegawai menentukan pengecualian atau tindakan seterusnya.</small></div></div>
@endif

@foreach ($view['checks'] as $check)
@php($collapsed = $view['final_check_number'] !== null && $view['final_check_number'] !== $check['number'])
@if ($collapsed)
<details class="content-card" id="semakan-{{ $check['number'] }}"><summary>Semakan {{ $check['number'] }} — semakan terdahulu (sejarah, baca sahaja)</summary>
@else
<section class="content-card" id="semakan-{{ $check['number'] }}">
@endif
    <div class="card-heading"><h2>Semakan {{ $check['number'] }}@if ($view['final_check_number'] === $check['number']) · muktamad @endif</h2>
        <span class="muted">{{ $check['status'] }}</span></div>
    @if ($check['status'] === 'COMPLETED')
        <p>Skor kesediaan dokumen: <strong>{{ $check['score'] }}</strong>/100
            @if ($check['profile_stale']) · <strong>Skor ditangguhkan: profil dibetulkan</strong> — dapatan dan kebolehgunaan perlu disemak semula.
            @elseif ($check['officer_score'] !== $check['score']) · selepas ulasan pegawai: <strong>{{ $check['officer_score'] }}</strong>/100 @endif</p>
        <p class="muted">Keputusan bayangan — tidak dikuatkuasakan: {{ $check['shadow_outcome'] === 'BELOW_THRESHOLD' ? 'di bawah ambang' : 'mencapai ambang' }} (ambang {{ config('airs.score_threshold') }})</p>
        @foreach ($check['items'] as $item)
            <article class="precheck-item" id="finding-{{ $item['finding_id'] }}">
                <div class="precheck-item-head"><div><h3>{{ $item['rule_id'] }} · {{ $item['title'] }}</h3><p>{{ $item['section'] }}</p></div>
                    <span class="precheck-label">AI: {{ $statusLabels[$item['ai_status']] ?? $item['ai_status'] }}</span></div>
                <p><strong>Keperluan:</strong> {{ $item['requirement'] }}</p>
                <p><strong>Dapatan AI:</strong> {{ $item['finding'] }}</p>
                @if ($item['confidence'] !== null)<p class="muted">Keyakinan: {{ number_format($item['confidence'] * 100) }}%</p>@endif
                @if ($item['manual_review_reason'])<p class="muted">Sebab semakan manual: {{ $item['manual_review_reason'] }}</p>@endif
                @if ($item['warnings'] !== [])<p class="muted">Amaran: {{ implode(', ', $item['warnings']) }}</p>@endif
                @foreach ($item['evidence'] as $evidence)
                    @if ($evidence['document_id'])
                        <p><a href="{{ route('kejuruteraan.documents.show', [$application, $evidence['document_id']]) }}{{ $evidence['page'] ? '#page='.$evidence['page'] : '' }}" target="_blank" rel="noopener">{{ $evidence['document'] }}@if ($evidence['page']), halaman {{ $evidence['page'] }}@endif</a>@if ($evidence['quote']) — “{{ \Illuminate\Support\Str::limit($evidence['quote'], 200) }}”@endif</p>
                    @endif
                @endforeach
                @includeIf('kejuruteraan.partials.feedback', ['item' => $item, 'check' => $check])
            </article>
        @endforeach
        @includeIf('kejuruteraan.partials.fields', ['check' => $check])
        <p class="muted">Kos MATRIX (anggaran pelanggan, bukan invois): USD {{ number_format((float) ($check['usage']['estimated_cost_usd'] ?? 0), 4) }}</p>
    @elseif ($check['status'] === 'FAILED')
        <p>Semakan gagal ({{ $check['error_code'] }}). Semakan boleh dijalankan semula tanpa menggunakan semakan berbayar.</p>
    @else
        <p>Semakan sedang dijalankan.</p>
    @endif
@if ($collapsed)</details>@else</section>@endif
@endforeach
@include('kejuruteraan.partials.recommendations')
@endsection
