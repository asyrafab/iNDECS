@php
    $statusText = $statusText ?? match ($label) {
        \App\Enums\PrecheckLabel::Missing => 'Belum dikemukakan',
        \App\Enums\PrecheckLabel::Pending => 'Sedang dibaca',
        \App\Enums\PrecheckLabel::NeedsConfirmation => 'Untuk perhatian',
        default => $label->value,
    };
    $statusShape = match ($label) {
        \App\Enums\PrecheckLabel::Missing => 'missing',
        \App\Enums\PrecheckLabel::Pending => 'reading',
        \App\Enums\PrecheckLabel::Attached => 'attached',
        default => 'attention',
    };
@endphp
<span class="precheck-status-icon precheck-status-{{ $statusShape }}" title="{{ $statusText }}">
    @if ($statusShape === 'reading')
        <span class="precheck-reading-dots" aria-hidden="true"><i></i><i></i><i></i></span>
    @else
        <x-icon :name="match ($statusShape) { 'attached' => 'check', 'missing' => 'x', default => 'triangle-alert' }" />
    @endif
    <span class="sr-only">{{ $statusText }}</span>
</span>
