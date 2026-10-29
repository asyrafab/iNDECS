@php($oscUser = auth()->user())
@if ($oscUser->canAccessOsc() && (\App\Services\Osc\OscAccess::canManage($oscUser) || $oscUser->canPerformOsc('osc.agenda.propose', (string) $application->responsible_department_id)))
    <a href="{{ route('osc.meetings.index', ['application' => $application->id]) }}" class="button button-secondary"><x-icon name="file-plus-2" />Bentang ke mesyuarat OSC</a>
@endif
