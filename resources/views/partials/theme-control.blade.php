{{-- resources/views/partials/theme-control.blade.php
     Drop into layouts/app.blade.php inside .topbar, before .user-chip:
       @include('partials.theme-control')
     and load the script in <head>:
       <script src="{{ asset('js/theme.js') }}"></script> --}}
<div class="theme-control">
    <div class="theme-seg" role="group" aria-label="Mod paparan">
        <button type="button" data-theme-mode="light" aria-pressed="false">Terang</button>
        <button type="button" data-theme-mode="dark" aria-pressed="false">Gelap</button>
        <button type="button" data-theme-mode="system" aria-pressed="false">Sistem</button>
    </div>
    <div class="accent-swatches" role="group" aria-label="Warna aksen">
        <button type="button" class="accent-swatch" data-theme-accent="merah" style="--swatch: #ec3013" aria-label="Merah" aria-pressed="false"></button>
        <button type="button" class="accent-swatch" data-theme-accent="hijau" style="--swatch: #20845e" aria-label="Hijau" aria-pressed="false"></button>
        <button type="button" class="accent-swatch" data-theme-accent="biru" style="--swatch: #2669d8" aria-label="Biru" aria-pressed="false"></button>
        <button type="button" class="accent-swatch" data-theme-accent="ambar" style="--swatch: #a96813" aria-label="Ambar" aria-pressed="false"></button>
    </div>
</div>
