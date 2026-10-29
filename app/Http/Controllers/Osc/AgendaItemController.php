<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Enums\Osc\MeetingStatus;
use App\Enums\Osc\TablingPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Osc\ReturnAgendaItemRequest;
use App\Http\Requests\Osc\StoreAgendaItemRequest;
use App\Models\Application;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\OscAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AgendaItemController extends Controller
{
    public function create(Request $request, Meeting $meeting): View
    {
        $user = $request->user();
        abort_unless(OscAccess::canManage($user) || $user->canPerformOsc('osc.agenda.propose'), 403);
        abort_if(in_array($meeting->status, [MeetingStatus::InProgress, MeetingStatus::Closed, MeetingStatus::MinutesConfirmed], true), 403, 'Mesyuarat ini tidak lagi menerima cadangan.');
        $filters = $request->validate(['application' => ['nullable', 'string', 'size:26']]);
        $application = null;
        if (filled($filters['application'] ?? null)) {
            $application = Application::query()->findOrFail($filters['application']);
            abort_unless(OscAccess::canManage($user) || $user->canAccessApplication($application), 403);
            abort_unless(OscAccess::canManage($user) || $user->canPerformOsc('osc.agenda.propose', (string) $application->responsible_department_id), 403);
        }
        $canManage = OscAccess::canManage($user);
        $itemDepartment = $application?->responsible_department_id ?? $user->department_id;

        return view('osc.propose', [
            'meeting' => $meeting,
            'application' => $application,
            'canManage' => $canManage,
            'purposes' => TablingPurpose::cases(),
            'departments' => $canManage ? DB::table('departments')->where('is_active', true)->orderBy('name')->pluck('name', 'id') : collect(),
            'presenters' => User::query()->where('account_type', 'staff')->where('is_active', true)
                ->when(! $canManage, fn ($query) => $query->where(fn ($same) => $same->where('department_id', $itemDepartment)->orWhere('id', $user->id)))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreAgendaItemRequest $request, Meeting $meeting, AgendaService $agenda): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $presenter = User::query()->findOrFail($data['presenter_id']);
        if (filled($data['application_id'] ?? null)) {
            $agenda->proposeLinked($meeting, Application::query()->findOrFail($data['application_id']),
                TablingPurpose::from($data['purpose']), $presenter, $user);
        } else {
            $agenda->proposeStandalone($meeting, $data, $user);
        }

        $target = OscAccess::canViewMeeting($user, $meeting) ? route('osc.meetings.show', $meeting) : route('osc.meetings.index');

        return redirect($target)->with('status', 'Cadangan telah dihantar ke agenda mesyuarat.');
    }

    public function endorse(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $agenda->endorse($item, (int) $data['expected_version'], $request->user());

        return $this->back($item, 'Cadangan telah disokong.');
    }

    public function accept(Request $request, AgendaItem $item, AgendaService $agenda): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $agenda->accept($item, (int) $data['expected_version'], $request->user());

        return $this->back($item, 'Perkara telah diterima ke dalam agenda.');
    }

    public function return(ReturnAgendaItemRequest $request, AgendaItem $item, AgendaService $agenda): RedirectResponse
    {
        $agenda->returnToDepartment($item, (int) $request->validated('expected_version'), $request->validated('reason'), $request->user());

        return $this->back($item, 'Cadangan telah dikembalikan kepada jabatan.');
    }

    private function back(AgendaItem $item, string $message): RedirectResponse
    {
        return to_route('osc.meetings.show', $item->meeting_id)->with('status', $message);
    }
}
