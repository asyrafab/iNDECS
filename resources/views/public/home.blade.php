@extends('layouts.public')
@section('title', 'Akaun Awam')
@section('content')
    <h1>Akaun awam</h1>
    <dl class="public-details">
        <dt>Nama</dt><dd>{{ $user->name }}</dd>
        <dt>Emel</dt><dd>{{ $user->email }}</dd>
        <dt>Status emel</dt><dd>{{ $user->email_verified_at ? 'Disahkan' : 'Belum disahkan' }}</dd>
        <dt>Kaedah akses</dt><dd>{{ $user->account_type === 'guest' ? 'Akses emel tanpa pendaftaran' : 'Akaun berdaftar' }}</dd>
    </dl>
    @if (! $user->email_verified_at)
        <form method="POST" action="{{ route('portal.email.send', 'verify') }}">@csrf<input type="hidden" name="email" value="{{ $user->email }}"><button class="button button-primary">Hantar semula pengesahan emel</button></form>
    @endif
    <section class="public-section">
        <h2>Permohonan</h2>
        <p>Penghantaran permohonan dalam talian belum dibuka.</p>
    </section>
    @if ($user->email_verified_at && ! $user->has_password)
        <section class="public-section">
            <h2>Daftar akaun tetap</h2>
            <form method="POST" class="public-form" action="{{ route('portal.claim') }}">
                @csrf
                <label class="field"><span>Nama penuh</span><input name="name" value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required></label>
                <label class="field"><span>Kata laluan (minimum 12 aksara)</span><input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
                <label class="field"><span>Sahkan kata laluan</span><input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
                <button class="button button-primary">Simpan akaun</button>
            </form>
        </section>
    @endif
    @if ($user->email_verified_at && ! $user->google_subject && config('services.google.client_id') && config('services.google.client_secret'))
        <a class="button button-secondary" href="{{ route('portal.google.link') }}">Pautkan akaun Google</a>
    @endif
    <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="button button-secondary">Log keluar</button></form>
@endsection
