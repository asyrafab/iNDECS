{{-- Officer comments on one AIRS finding: the AI status stays visible beside the officer status. --}}
@php($canComment = $view['journey']?->state === \App\Enums\PaidCheckState::HandedOver && $view['final_check_number'] === $check['number'] && auth()->user()->canPerformLjup('review'))
@if ($item['officer_status'])
    <p><strong>Pegawai: {{ $statusLabels[$item['officer_status']] ?? $item['officer_status'] }}</strong>@if ($item['officer_comment']) — {{ $item['officer_comment'] }}@endif</p>
@endif
@if ($item['feedback_history'] !== [])
    <details><summary>Sejarah ulasan ({{ count($item['feedback_history']) }})</summary><ul>
        @foreach ($item['feedback_history'] as $entry)
            <li>{{ $entry['at']?->setTimezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }} · {{ $entry['officer'] }} · {{ $statusLabels[$entry['status']] ?? $entry['status'] }}@if ($entry['comment']) — {{ $entry['comment'] }}@endif</li>
        @endforeach
    </ul></details>
@endif
@if ($canComment)
    <form method="POST" action="{{ route('kejuruteraan.findings.feedback', [$application, $item['finding_id']]) }}" class="form-grid">
        @csrf
        <label class="field"><span>Status pegawai</span><select name="officer_status" required>
            @foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($item['officer_status'] ?? $item['ai_status']) === $value)>{{ $label }}</option>@endforeach
        </select></label>
        <label class="field field-span-2"><span>Ulasan</span><textarea name="officer_comment" rows="2" maxlength="2000"></textarea></label>
        <button class="button button-secondary button-small" type="submit">Simpan ulasan</button>
    </form>
@endif
