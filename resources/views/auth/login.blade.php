<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log masuk · iNDECS</title>
    <meta name="description" content="Log masuk iNDECS — Sistem Bersepada Kawalan Pembangunan MBSP">
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand-panel">
            <div class="auth-brand">
                <span class="brand-mark">iN</span>
                <div>
                    <strong>iNDECS</strong>
                    <small>Intergrated Development Control System</small>
                </div>
            </div>
            <div>
                <span class="eyebrow">Majlis Bandaraya Seberang Perai</span>
                <h1>Satu Platform,<br>Pelbagai Permohonan Pembangunan</h1>
                <p>Sistem Bersepada Kawalan Pembangunan untuk pengurusan permohonan teknikal yang selamat dan boleh diaudit.</p>
            </div>
        </section>

        <section class="auth-form-panel">
            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <div>
                    <span class="eyebrow">Ruang kerja pegawai</span>
                    <h2>Log masuk ke iNDECS</h2>
                    <p>Gunakan akaun {{ config('indecs.staff_auth_driver') === 'local' ? 'jabatan' : 'MBSP' }} yang telah dibenarkan.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">
                        <span>!</span>
                        {{ $errors->first() }}
                    </div>
                @endif

                <label class="field">
                    @if (config('indecs.staff_auth_driver') !== 'local')
                    <span>Nombor kakitangan</span>
                    <input type="text" name="staff_number" value="{{ old('staff_number') }}" inputmode="numeric" maxlength="30" autocomplete="username" required autofocus>
                    @else
                    <span>Emel</span>
                    <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                    @endif
                </label>

                <label class="field">
                    <span>Kata laluan</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                @if (config('indecs.staff_auth_driver') === 'local')
                <label class="checkbox-field">
                    <input type="checkbox" name="remember" value="1">
                    <span>Kekalkan sesi log masuk</span>
                </label>
                @endif

                <button type="submit" class="button button-primary button-large">Log masuk <span>→</span></button>
                <a href="{{ route('portal.login') }}">Akses orang awam</a>
            </form>
        </section>
    </main>
</body>
</html>
