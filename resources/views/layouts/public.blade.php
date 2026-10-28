<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Portal Awam') · iNDECS</title>
    <link rel="icon" href="{{ asset('img/indecs-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="{{ asset('js/theme.js') }}"></script><link rel="stylesheet" href="{{ asset('css/indecs.css') }}"><script src="{{ asset('js/indecs.js') }}" defer></script>
</head>
<body class="public-page">
    <a class="skip-link" href="#main-content">Langkau ke kandungan</a>
    <header class="public-header">
        <a href="{{ route('portal.home') }}" class="public-brand" aria-label="Portal Awam iNDECS"><img src="{{ asset('img/mbsp.png') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest"><span class="public-wordmark"><x-mark /><span>iNDECS<small>Portal Awam</small></span></span></a>
        <div class="public-header-actions">@include('partials.theme-control')<a class="button button-ghost button-small" href="{{ route('login') }}"><x-icon name="log-in" />Kakitangan MBSP</a></div>
    </header>
    <div class="public-workspace"><main class="public-shell" id="main-content" tabindex="-1">
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif
        @yield('content')
        <button type="button" class="button button-ghost" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" />Perlukan panduan? Tanya AMIRA</button>
        <footer class="workspace-footer"><x-mark class="mark-small" /><span>Persekitaran iNDECS · MBSP</span></footer>
    </main>@include('partials.amira')</div>
</body>
</html>
