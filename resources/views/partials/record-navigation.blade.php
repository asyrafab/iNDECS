<nav class="record-tabs" aria-label="Bahagian permohonan">
    @foreach ($recordNavigation as $label => $url)<a href="{{ $url }}">{{ $label }}</a>@endforeach
</nav>
