@php
    $taskCheck = collect($view['checks'])->firstWhere('number', $view['final_check_number']) ?? collect($view['checks'])->last();
    $taskTotal = count($taskCheck['items'] ?? []);
    $taskReviewed = collect($taskCheck['items'] ?? [])->whereNotNull('officer_status')->count();
    $taskCompleted = ($taskCheck['status'] ?? null) === 'COMPLETED';
    $taskLabel = match (true) {
        $taskCheck === null => 'Tiada semakan',
        ($taskCheck['status'] ?? null) === 'FAILED' => 'Analisis gagal',
        !$taskCompleted => 'Analisis sedang berjalan',
        $taskTotal === 0 => 'Tiada dapatan tersedia',
        $taskReviewed === $taskTotal => 'Ulasan lengkap',
        $taskReviewed > 0 => 'Dalam semakan',
        default => 'Belum bermula',
    };
@endphp
<aside class="review-task-status" data-review-task-status aria-label="Status tugasan dan skor nasihat">
    <details class="review-task-tool">
        <summary class="review-task-toggle">
            <x-icon name="panel-right" />
            <span>Semakan @if ($taskCompleted && $taskTotal > 0)<strong>{{ $taskReviewed }}/{{ $taskTotal }}</strong><span class="sr-only"> diulas</span>@endif</span>
            <span class="review-task-compact-score">Skor nasihat <strong>@if ($taskCompleted && $taskCheck['profile_stale'])Ditangguhkan @elseif ($taskCompleted && $taskCheck['officer_score'] !== null){{ $taskCheck['officer_score'] }}/100 @else Belum tersedia @endif</strong></span>
            <x-icon name="chevron-down" class="review-task-chevron" />
        </summary>
        <div class="review-task-card">
        <div class="review-task-progress">
            <span class="eyebrow">Tugasan semakan @if ($taskCheck) · {{ $taskCheck['number'] }} @endif</span>
            <strong>{{ $taskLabel }}</strong>
            @if ($taskCompleted && $taskTotal > 0)<span class="muted">{{ $taskReviewed }} / {{ $taskTotal }} diulas · {{ $taskTotal - $taskReviewed }} berbaki</span>@endif
        </div>
        <div class="review-task-score">
            <span class="eyebrow">Skor nasihat</span>
            @if ($taskCompleted && $taskCheck['profile_stale'])
                <strong>Ditangguhkan</strong><span class="muted">Profil dibetulkan</span>
            @elseif ($taskCompleted && $taskCheck['officer_score'] !== null)
                <strong>{{ $taskCheck['officer_score'] }}<small> / 100</small></strong><span class="muted">Bukan keputusan rasmi</span>
            @else
                <strong>Belum tersedia</strong>
            @endif
        </div>
        </div>
    </details>
</aside>
