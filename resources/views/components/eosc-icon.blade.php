@props(['compact' => false, 'label' => ''])
<img {{ $attributes->class(['eosc-icon', 'eosc-icon-compact' => $compact]) }} src="{{ asset('img/eosc-temporary.png') }}" alt="{{ $label }}" @if (! $label) aria-hidden="true" @endif width="64" height="64">
