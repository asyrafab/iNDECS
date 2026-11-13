@props(['department', 'compact' => false, 'label' => null])
@php
    if (! in_array($department, ['perancang', 'bangunan', 'kejuruteraan', 'landskap'], true)) {
        throw new InvalidArgumentException('Unknown department icon.');
    }
    $variant = $compact ? 'compact' : 'detailed';
    $spriteVersion = filemtime(public_path('img/departments/sprite.svg'));
@endphp
<svg {{ $attributes->class(['department-icon', 'department-icon-'.$department, 'department-icon-compact' => $compact]) }} viewBox="0 0 120 120" @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif focusable="false"><use href="{{ asset('img/departments/sprite.svg') }}?v={{ $spriteVersion }}#{{ $department }}-{{ $variant }}"/></svg>
