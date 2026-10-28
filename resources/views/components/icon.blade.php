@props(['name'])
<svg {{ $attributes->class(['icon']) }} aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><use href="{{ asset('img/lucide.svg') }}#{{ $name }}"></use></svg>
