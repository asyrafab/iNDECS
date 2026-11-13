@extends('layouts.app')
@section('title', 'Dapatan AIRS · '.$application->application_reference)
@section('content')
<div class="page-heading"><div><h1>Dapatan semakan AIRS</h1><p>{{ $application->application_reference }} · {{ $application->title }}</p></div><a class="button button-secondary" href="{{ route('airs.application', $application) }}">Sejarah semakan</a></div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="content-card"><h2>Ringkasan pemprosesan</h2><p>{{ $review->module_code }} · {{ ['queued' => 'Dalam baris gilir', 'processing' => 'Sedang diproses', 'completed' => 'Dapatan tersedia', 'failed' => 'Memerlukan tindakan', 'blocked' => 'Perlu konfigurasi', 'superseded' => 'Versi terdahulu'][$review->state] ?? $review->state }} · {{ $review->result['progress'] ?? 0 }}%</p>
<p class="helper-text">Rule pack: {{ $review->result['rule_pack'] ?? '—' }} · Versi: {{ $review->result['rule_version'] ?? '—' }}. Dapatan ialah bantuan semakan; pegawai membuat keputusan rasmi.</p>
@if (! $current)<div class="alert alert-info">Semakan ini merujuk serahan atau versi dokumen terdahulu. Maklum balas baharu tidak tersedia.</div>@endif
@if ($review->last_error)<div class="alert alert-error">{{ $review->last_error }}</div>@endif
@if ($canReview)<form method="post" action="{{ route('airs.refresh', $review) }}">@csrf<button class="button button-secondary" type="submit">Segerakkan status</button></form>@endif</section>
@forelse ($review->result['findings'] ?? [] as $finding)
<section class="content-card"><h2>{{ $finding['rule_id'] }} · {{ $finding['title'] }}</h2><p>{{ $finding['requirement'] ?? '' }}</p>
<div class="evidence"><h3>Bukti dokumen</h3>@forelse ($finding['evidence'] as $evidence)<p><a href="{{ route('airs.document', [$review, $evidence['document_id']]) }}">Dokumen sumber · Halaman {{ $evidence['page'] }}</a></p><p>{{ $evidence['quote'] ?? '' }}</p>@empty<p>Bukti belum dikesan.</p>@endforelse</div>
<div class="ai-explanation"><h3>Penjelasan AI</h3><p><span class="status-badge status-ai">{{ $finding['status'] }}</span> · Keyakinan {{ number_format(($finding['confidence'] ?? 0) * 100, 0) }}%</p><p>{{ $finding['finding'] ?? '' }}</p></div>
<div class="form-divider"></div><h3>Semakan pegawai</h3>
@foreach ($feedback->get($finding['id'], collect()) as $entry)<p>{{ $entry->status }} · {{ $entry->comment }} · {{ $entry->sent_at ? 'Dihantar kepada AIRS' : 'Menunggu penyegerakan' }}</p>@endforeach
@if ($current && $canReview && $review->state === 'completed')<form method="post" action="{{ route('airs.feedback', $review) }}" class="application-form">@csrf
<input type="hidden" name="event_id" value="{{ (string) \Illuminate\Support\Str::ulid() }}"><input type="hidden" name="finding_id" value="{{ $finding['id'] }}">
<label class="field"><span>Status dapatan</span><select name="status">@foreach (\App\Services\Airs\AirsReviews::STATUSES as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></label>
<label class="field"><span>Catatan pegawai</span><textarea name="comment" maxlength="2000" rows="3"></textarea></label><button class="button button-secondary" type="submit">Rekod maklum balas</button></form>@endif
</section>
@empty<section class="content-card"><p>Dapatan belum tersedia. Segerakkan status selepas pemprosesan AIRS selesai.</p></section>@endforelse
@endsection
