@extends('layouts.public')
@section('title', 'Pengesahan Emel')
@section('content')
    <h1>{{ $purpose === 'reset' ? 'Kata laluan baharu' : 'Sahkan akses emel' }}</h1>
    <form method="POST" class="public-form" action="{{ route('portal.link.consume', [$purpose, $token]) }}">
        @csrf
        @if ($purpose === 'reset')
            <label class="field"><span>Kata laluan baharu (minimum 12 aksara)</span><input type="password" name="password" autocomplete="new-password" minlength="12" required></label>
            <label class="field"><span>Sahkan kata laluan</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
        @endif
        <button type="submit" class="button button-primary">{{ $purpose === 'reset' ? 'Simpan kata laluan' : 'Sahkan dan teruskan' }}</button>
    </form>
    <a href="{{ route('portal.email') }}">Dapatkan pautan baharu</a>
@endsection
