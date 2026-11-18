@extends('layouts.public')
@section('title', 'Akses MyDigital ID sandbox')
@section('content')
    <h1>Akses MyDigital ID sandbox</h1>
    @if (auth()->check())
        @if (app(\App\Services\Identity\SandboxIdentity::class)->eligible(auth()->user()) && config('mydigitalid.enrolment_enabled'))
            <h2>Pautkan MyDigital ID</h2>
            <p>Pautan ini menggunakan akaun awam anda yang sedia ada. Sahkan emel, teruskan ke MyDigital ID, kemudian sahkan pautan.</p>
            <form class="public-form" method="POST" action="{{ route('portal.identity.enrol') }}">@csrf
                <button class="button button-secondary">Hantar kod pengesahan emel</button>
            </form>
            <form class="public-form" method="POST" action="{{ route('portal.identity.enrol.verify') }}">@csrf
                <label class="field"><span>Kod pengesahan emel</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                <button class="button button-secondary">Sahkan emel</button>
            </form>
            <form method="POST" action="{{ route('portal.identity.link') }}">@csrf<button class="button button-primary">Pautkan MyDigital ID</button></form>
            @if ($maskedIdentity)
                <p>Identiti MyDigital ID: {{ $maskedIdentity }}. Sahkan untuk memautkan identiti ini kepada akaun awam anda.</p>
                <form method="POST" action="{{ route('portal.identity.confirm') }}">@csrf<button class="button button-primary">Sahkan pautan</button></form>
            @endif
        @else
            <p>Akaun ini tidak layak untuk penyertaan sandbox.</p>
        @endif
        <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="button button-secondary">Log keluar</button></form>
    @elseif (config('mydigitalid.login_enabled'))
        <form method="POST" action="{{ route('portal.identity.login') }}">@csrf<button class="button button-primary">Log masuk dengan MyDigital ID</button></form>
        <h2>Log masuk alternatif dengan kod OTP</h2>
        <form class="public-form" method="POST" action="{{ route('portal.identity.otp.request') }}">@csrf
            <label class="field"><span>Emel berdaftar</span><input type="email" name="email" maxlength="255" autocomplete="email" required></label>
            <button class="button button-secondary">Hantar kod OTP</button>
        </form>
        <form class="public-form" method="POST" action="{{ route('portal.identity.otp.verify') }}">@csrf
            <label class="field"><span>Kod OTP</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
            <button class="button button-primary">Log masuk</button>
        </form>
    @endif
    <p><a href="{{ route('portal.login') }}">Kembali ke log masuk awam</a></p>
@endsection
