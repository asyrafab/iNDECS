<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Portal Awam') · iNDECS</title>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/indecs.css') }}">
</head>
<body>
    <header class="public-header">
        <a href="{{ route('portal.home') }}" class="public-brand" aria-label="Portal Awam iNDECS">
            <img src="{{ asset('img/mbsp.png') }}" alt="Majlis Bandaraya Seberang Perai" class="public-crest">
            <span class="public-wordmark">iNDECS <small>Portal Awam</small></span>
        </a>
        <a href="{{ route('login') }}">Kakitangan MBSP</a>
    </header>
    <main class="public-shell">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
