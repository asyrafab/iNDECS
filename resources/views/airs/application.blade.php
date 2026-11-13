@extends('layouts.app')
@section('title', 'Semakan AIRS · '.$application->application_reference)
@section('content')
<div class="page-heading"><div><h1>Semakan AIRS</h1><p>{{ $application->application_reference }} · {{ $application->title }}</p></div></div>
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="content-card"><h2>Kelayakan semakan</h2>
<p>Hanya dokumen dalam serahan rasmi selepas pengesahan bayaran atau pengecualian yang diluluskan dihantar untuk semakan teknikal. Dapatan tidak menjadi keputusan kelulusan.</p>
@if (! config('airs.enabled'))<p class="helper-text">Sambungan AIRS belum diaktifkan.</p>
@elseif (! $submission)<p class="helper-text">Serahan rasmi dengan pengesahan bayaran belum direkodkan. Pendaftaran pegawai dan muat naik dokumen sahaja tidak memulakan semakan teknikal.</p>
@elseif (! $module)<p class="helper-text">Modul semakan belum dikonfigurasi.</p>
@elseif (! $canReview)<p class="helper-text">Peranan anda mempunyai akses baca sahaja kepada semakan ini.</p>
@else<form method="post" action="{{ route('airs.store', $application) }}" class="application-form">@csrf
@if ($module === 'STREET_LIGHTING')<fieldset><legend>Jenis lampu</legend>@foreach (['BESI' => 'Besi', 'KONKRIT' => 'Konkrit', 'SOLAR' => 'Solar'] as $value => $label)<label><input type="checkbox" name="lighting_types[]" value="{{ $value }}"> {{ $label }}</label>@endforeach</fieldset>@endif
<p class="helper-text">Ketersediaan rule pack disahkan oleh AIRS semasa permintaan diterima.</p><button class="button button-primary" type="submit">Minta semakan AIRS</button></form>@endif
</section>
<section class="content-card"><h2>Sejarah semakan</h2>@forelse ($reviews as $review)<p><a href="{{ route('airs.show', $review) }}">{{ $review->created_at->format('d/m/Y H:i') }} · {{ $review->module_code }}</a></p>@empty<p>Belum ada permintaan.</p>@endforelse</section>
@endsection
