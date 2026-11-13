@php
    $reopenLogin = $errors->any() && old('_welcome_login') === '1';
    $loginTab = $reopenLogin && old('_welcome_tab') === 'staff' ? 'staff' : 'public';
    $localStaffLogin = config('indecs.staff_auth_driver') === 'local';
@endphp
<dialog class="welcome-login-dialog" id="welcome-login" aria-labelledby="welcome-login-title" data-login-dialog data-login-tab="{{ $loginTab }}" @if ($reopenLogin) data-reopen-login @endif>
    <div class="welcome-login-layout">
    <div class="welcome-login-content">
        <button class="icon-button welcome-panel-close" type="button" aria-label="Tutup log masuk" data-login-close><x-icon name="x" /></button>
        <span class="eyebrow">Akses iNDECS</span>
        <h2 id="welcome-login-title">Log masuk</h2>
        <p class="welcome-login-intro">Selamat kembali. Pilih akses anda untuk meneruskan.</p>
        <div class="welcome-login-tabs" role="tablist" aria-label="Jenis akaun">
            <button type="button" id="login-tab-public" role="tab" aria-selected="{{ $loginTab === 'public' ? 'true' : 'false' }}" aria-controls="login-panel-public" tabindex="{{ $loginTab === 'public' ? '0' : '-1' }}" data-login-tab-button="public">Orang awam & perunding</button>
            <button type="button" id="login-tab-staff" role="tab" aria-selected="{{ $loginTab === 'staff' ? 'true' : 'false' }}" aria-controls="login-panel-staff" tabindex="{{ $loginTab === 'staff' ? '0' : '-1' }}" data-login-tab-button="staff">Kakitangan</button>
        </div>
        @if ($reopenLogin)
            <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
        @endif
        <div id="login-panel-public" role="tabpanel" aria-labelledby="login-tab-public" @if ($loginTab !== 'public') hidden @endif data-login-panel="public">
            <form class="public-form" method="POST" action="{{ route('portal.login.store') }}">
                @csrf
                <input type="hidden" name="_welcome_login" value="1">
                <input type="hidden" name="_welcome_tab" value="public">
                <label class="field" for="welcome-public-email"><span>Nama pengguna (emel)</span><input id="welcome-public-email" type="email" name="email" value="{{ $loginTab === 'public' ? old('email') : '' }}" autocomplete="username" maxlength="255" required></label>
                <label class="field" for="welcome-public-password"><span>Kata laluan</span><input id="welcome-public-password" type="password" name="password" autocomplete="current-password" required></label>
                <button class="button button-primary" type="submit">Log masuk <x-icon name="arrow-right" /></button>
                <div class="welcome-login-divider" role="separator" aria-label="atau"><span aria-hidden="true">atau</span></div>
                <div class="google-login-option">
                    <span class="google-login-logo" aria-hidden="true"><img src="{{ asset('img/google-g.png') }}" width="20" height="20" alt=""></span>
                    @if (config('services.google.client_id') && config('services.google.client_secret'))
                        <a class="button button-google" href="{{ route('portal.google') }}"><span>Teruskan dengan Google</span></a>
                    @else
                        <button class="button button-google" type="button" disabled><span>Google belum diaktifkan</span></button>
                    @endif
                </div>
                <div class="welcome-login-links"><a href="{{ route('portal.forgot') }}">Lupa kata laluan?</a><a href="{{ route('portal.register') }}">Daftar pengguna</a></div>
            </form>
        </div>
        <div id="login-panel-staff" role="tabpanel" aria-labelledby="login-tab-staff" @if ($loginTab !== 'staff') hidden @endif data-login-panel="staff">
            <form class="public-form" method="POST" action="{{ route('login.store') }}">
                @csrf
                <input type="hidden" name="_welcome_login" value="1">
                <input type="hidden" name="_welcome_tab" value="staff">
                <label class="field" for="welcome-staff-username"><span>{{ $localStaffLogin ? 'Nama pengguna (emel)' : 'Nombor kakitangan' }}</span>
                    @if ($localStaffLogin)
                        <input id="welcome-staff-username" type="email" name="email" value="{{ $loginTab === 'staff' ? old('email') : '' }}" autocomplete="username" maxlength="255" required>
                    @else
                        <input id="welcome-staff-username" type="text" name="staff_number" value="{{ $loginTab === 'staff' ? old('staff_number') : '' }}" autocomplete="username" inputmode="numeric" pattern="[0-9]{1,30}" maxlength="30" required>
                    @endif
                </label>
                <label class="field" for="welcome-staff-password"><span>Kata laluan</span><input id="welcome-staff-password" type="password" name="password" autocomplete="current-password" required></label>
                <button class="button button-primary" type="submit">Log masuk <x-icon name="arrow-right" /></button>
                <div class="welcome-login-links"><button type="button" aria-expanded="false" aria-controls="welcome-staff-help" data-staff-login-help>Lupa kata laluan?</button><a href="{{ route('portal.register') }}">Daftar pengguna awam</a></div>
                <p class="welcome-login-help" id="welcome-staff-help" hidden>Untuk menetapkan semula kata laluan atau mendapatkan akses kakitangan, hubungi pentadbir akaun MBSP / iNDECS. Pendaftaran awam tidak memberikan akses kakitangan.</p>
            </form>
        </div>
    </div>
    <div class="welcome-login-portrait" aria-hidden="true">
        <img src="{{ asset('img/login/public-users-v2.png') }}" alt="" width="793" height="1983" decoding="async" @if ($loginTab !== 'public') hidden @endif data-login-portrait="public">
        <img src="{{ asset('img/login/staff-users-v3.png') }}" alt="" width="793" height="1983" decoding="async" @if ($loginTab !== 'staff') hidden @endif data-login-portrait="staff">
    </div>
    </div>
</dialog>
