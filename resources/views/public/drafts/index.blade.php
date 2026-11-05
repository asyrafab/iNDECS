@extends('layouts.portal')
@section('title', 'Draf saya')
@section('content')
    <div class="page-heading heading-with-action"><div><span class="eyebrow">Persediaan permohonan</span><h1>Draf saya</h1><p>Draf hanya boleh diakses oleh anda. Draf belum dihantar dan belum mempunyai nombor rujukan rasmi.</p></div>@if(auth()->user()->email_verified_at)<a class="button button-primary" href="{{ route('portal.drafts.create') }}"><x-icon name="file-plus-2" />Mulakan draf</a>@endif</div>
    <section class="content-card">@include('public.drafts.list')</section>
    <nav class="portal-pagination" aria-label="Halaman draf">
        @if($drafts->previousPageUrl())<a class="button button-secondary" href="{{ $drafts->previousPageUrl() }}">Sebelumnya</a>@endif
        <span>{{ $drafts->total() }} draf · Halaman {{ $drafts->currentPage() }} daripada {{ $drafts->lastPage() }}</span>
        @if($drafts->nextPageUrl())<a class="button button-secondary" href="{{ $drafts->nextPageUrl() }}">Seterusnya</a>@endif
    </nav>
@endsection
