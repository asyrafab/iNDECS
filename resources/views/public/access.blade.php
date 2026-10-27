@extends('layouts.public')
@section('title', 'Akses Awam')
@section('content')
    <nav class="public-tabs" aria-label="Kaedah akses">
        <a href="{{ route('portal.login') }}" @if ($page === 'login') aria-current="page" @endif>Log masuk</a>
        <a href="{{ route('portal.register') }}" @if ($page === 'register') aria-current="page" @endif>Daftar akaun</a>
        <a href="{{ route('portal.email') }}" @if ($page === 'email') aria-current="page" @endif>Akses sekali melalui emel</a>
    </nav>
    <h1>{{ match ($page) { 'register' => 'Daftar akaun awam', 'email' => 'Akses tanpa pendaftaran', 'forgot' => 'Tetapkan semula kata laluan', default => 'Log masuk awam' } }}</h1>
    @if ($page === 'email')
        <p>Masukkan emel untuk menerima pautan akses sekali guna.</p>
    @endif
    <form class="public-form" method="POST" action="{{ match ($page) { 'register' => route('portal.register.store'), 'email' => route('portal.email.send', 'login'), 'forgot' => route('portal.email.send', 'reset'), default => route('portal.login.store') } }}">
        @csrf
        @if ($page === 'register')
            <label class="field"><span>Nama penuh</span><input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required></label>
        @endif
        <label class="field"><span>Emel</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required></label>
        @if (in_array($page, ['login', 'register']))
            <label class="field"><span>Kata laluan{{ $page === 'register' ? ' (minimum 12 aksara)' : '' }}</span><input type="password" name="password" autocomplete="{{ $page === 'register' ? 'new-password' : 'current-password' }}" @if ($page === 'register') minlength="12" @endif required></label>
            @if ($page === 'register')
                <label class="field"><span>Sahkan kata laluan</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label>
            @endif
        @endif
        <button class="button button-primary" type="submit">{{ match ($page) { 'register' => 'Daftar', 'email', 'forgot' => 'Hantar pautan emel', default => 'Log masuk' } }}</button>
    </form>
    @if ($page === 'login')
        <a href="{{ route('portal.forgot') }}">Lupa kata laluan?</a>
        <div class="public-section">
            @if (config('services.google.client_id') && config('services.google.client_secret'))
                <a class="button button-secondary" href="{{ route('portal.google') }}">Teruskan dengan Google</a>
            @else
                <button class="button button-secondary" disabled>Google belum diaktifkan</button>
            @endif
        </div>
    @endif
@endsection
