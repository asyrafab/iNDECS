@extends('layouts.app')
@section('title', 'Dokumen · '.$application->application_reference)
@section('content')
<div class="page-heading"><a class="back-link" href="{{ route('bangunan.show', $application) }}">Kembali ke permohonan</a><span class="eyebrow">{{ $application->application_reference }}</span><h1>Dokumen dan pengesahan</h1><p>{{ $application->title }} · {{ $application->current_stage->label() }} · {{ $application->current_status->label() }}</p></div>
@if ($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@include('partials.record-navigation', ['recordNavigation' => ['Ringkasan' => route('bangunan.show', $application).'#ringkasan', 'Borang Jabatan' => route('bangunan.show', $application).'#borang-jabatan', 'Dokumen & Pengesahan' => '#senarai-dokumen', 'Garis Masa' => route('bangunan.show', $application).'#garis-masa', 'Sejarah Audit' => route('bangunan.show', $application).'#audit']])
<div class="application-form" id="senarai-dokumen">
@if (! $checklist)
<section class="content-card detail-card"><h2>Senarai semak belum diwujudkan</h2><p>Senarai semak diwujudkan daripada katalog jabatan yang disahkan apabila pengesahan dokumen dimulakan.</p>
@if ($application->current_stage === \App\Enums\ApplicationStage::DocumentVerification && auth()->user()->canPerformBangunan('documents.verify', $application->application_type_id))<form method="POST" action="{{ route('bangunan.documents.initialise', $application) }}">@csrf<input type="hidden" name="row_version" value="{{ $application->row_version }}"><button class="button button-primary" type="submit">Wujudkan senarai semak</button></form>@endif
@if (auth()->user()->canPerformBangunan('documents.configure', $application->application_type_id))<a class="button button-secondary" href="{{ route('bangunan.document-catalogue.edit', $application->application_type_id) }}">Tetapkan katalog jabatan</a>@endif</section>
@else
<section class="content-card detail-card"><h2>Senarai semak versi {{ $checklist->catalogue_version }}</h2><p>{{ $checklist->source_reference }}</p><p>Revisi baharu memerlukan pengesahan semula. Revisi terdahulu kekal dalam sejarah dokumen.</p></section>
@foreach ($checklist->items as $item)
@php($revisions = $documents->where('document_type_id', $item->document_type_id))
@php($current = $revisions->firstWhere('is_current', true))
<section class="content-card detail-card"><div class="card-heading"><div><h2>{{ $item->document_name }}</h2><span class="status-badge status-{{ $item->status === 'accepted' ? 'approved' : ($item->status === 'rejected' ? 'returned_for_amendment' : 'submitted') }}">{{ ['accepted' => 'Diterima', 'rejected' => 'Ditolak', 'pending' => 'Belum disemak', 'not_applicable' => 'Tidak berkenaan'][$item->status] }}</span></div><span>{{ $item->is_required ? 'Wajib' : 'Pilihan' }}</span></div>
@if ($current)<p><a class="reference-link" href="{{ route('bangunan.documents.download', [$application, $current]) }}">{{ $current->original_name }}</a> · Revisi {{ $current->revision_number }} · {{ number_format($current->size_bytes / 1024, 1) }} KB</p><p>Dimuat naik oleh {{ $current->uploader?->name }} pada {{ $current->uploaded_at->format('d/m/Y H:i') }}. Imbasan keselamatan selesai.</p>@else<p>Tiada dokumen dikemukakan.</p>@endif
@if ($item->remarks)<p>Catatan semakan: {{ $item->remarks }}</p>@endif
@if ($application->current_stage === \App\Enums\ApplicationStage::DocumentVerification && in_array($application->current_status, [\App\Enums\ApplicationStatus::InReview, \App\Enums\ApplicationStatus::ReturnedForAmendment], true) && auth()->user()->canPerformBangunan('documents.upload', $application->application_type_id))
<form class="application-form" method="POST" enctype="multipart/form-data" action="{{ route('bangunan.documents.store', [$application, $item]) }}">@csrf<input type="hidden" name="row_version" value="{{ $application->row_version }}"><label class="field"><span>{{ $current ? 'Ganti dengan revisi baharu' : 'Muat naik dokumen' }} · maksimum {{ number_format($item->max_file_size_kb / 1024) }} MB</span><input type="file" name="document" accept=".pdf,.png,.jpg,.jpeg" required></label><button class="button button-secondary" type="submit">Muat naik dan imbas</button></form>
@endif
@if ($application->current_stage === \App\Enums\ApplicationStage::DocumentVerification && $application->current_status === \App\Enums\ApplicationStatus::InReview && auth()->user()->canPerformBangunan('documents.verify', $application->application_type_id) && ($current || ! $item->is_required))
<div class="form-divider"></div><form class="application-form" method="POST" action="{{ route('bangunan.documents.verify', [$application, $item]) }}">@csrf<input type="hidden" name="row_version" value="{{ $application->row_version }}"><input type="hidden" name="document_id" value="{{ $current?->id }}"><div class="form-grid"><label class="field"><span>Keputusan semakan</span><select name="status">@if ($current)<option value="accepted">Diterima</option><option value="rejected">Ditolak</option>@endif
@if (! $item->is_required)<option value="not_applicable">Tidak berkenaan</option>@endif</select></label><label class="field field-span-2"><span>Catatan · wajib bagi penolakan / pengecualian</span><textarea name="remarks" maxlength="2000" rows="2"></textarea></label></div><button type="submit" class="button button-primary">Rekod keputusan semakan</button></form>
@endif
@if ($revisions->count() > 1)<details><summary>Sejarah revisi ({{ $revisions->count() }})</summary><ul>@foreach ($revisions as $revision)<li><a href="{{ route('bangunan.documents.download', [$application, $revision]) }}">Revisi {{ $revision->revision_number }} · {{ $revision->original_name }}</a> · {{ $revision->verification_status->label() }}</li>@endforeach</ul></details>@endif
</section>
@endforeach
@endif
<section class="content-card detail-card"><h2>Tindakan aliran kerja</h2><p>Gunakan halaman permohonan untuk pengesahan lengkap, pemulangan atau penghantaran semula selepas semakan dokumen.</p><a class="button button-secondary" href="{{ route('bangunan.show', $application) }}">Buka tindakan permohonan</a></section>
</div>
@endsection
