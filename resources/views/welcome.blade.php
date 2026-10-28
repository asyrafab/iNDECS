@extends('layouts.public')
@section('title', 'Selamat datang')
@section('content')
    <span class="eyebrow">Majlis Bandaraya Seberang Perai</span>
    <h1>Selamat datang ke iNDECS</h1>
    <p>Sistem Bersepada Kawalan Pembangunan untuk urusan permohonan dan semakan jabatan.</p>
    <div class="action-links">
        <a class="button button-primary" href="{{ route('portal.login') }}"><x-icon name="log-in" />Akses orang awam</a>
        <a class="button button-secondary" href="{{ route('login') }}"><x-icon name="log-in" />Akses kakitangan MBSP</a>
    </div>
@endsection
