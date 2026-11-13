@extends('layouts.portal')
@section('title', 'Bayaran ujian')
@section('content')
    <div class="page-heading"><a class="back-link" href="{{ route('portal.drafts.edit', $draft) }}"><x-icon name="arrow-left" />Kembali ke draf</a>
        <span class="eyebrow">Persekitaran ujian</span><h1>Bayaran ujian semakan AIRS</h1>
        <p>Halaman ini hanya wujud dalam persekitaran ujian. Tiada wang sebenar dikutip.</p></div>
    <div class="alert alert-info"><x-icon name="circle-alert" /><div><strong>{{ $draft->title }}</strong><br>Rujukan bayaran {{ $payment->reference }} · RM{{ number_format($payment->amount_sen / 100, 2) }}</div></div>
    @if ($payment->status === 'paid')
        <p>Bayaran ini telah disahkan.</p>
    @else
        <form method="POST" action="{{ route('portal.payments.staging.confirm', $payment) }}">
            @csrf
            <button class="button button-primary" type="submit">Sahkan bayaran ujian</button>
        </form>
    @endif
@endsection
