<?php

declare(strict_types=1);

namespace App\Http\Controllers\Osc;

use App\Enums\Osc\AgendaItemStatus;
use App\Enums\Osc\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Osc\ReorderAgendaRequest;
use App\Http\Requests\Osc\StoreMeetingRequest;
use App\Models\Application;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\DepartmentSetting;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\MeetingService;
use App\Services\Osc\OscAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate(['application' => ['nullable', 'string', 'size:26']]);
        $application = null;
        $pickable = collect();
        if (filled($filters['application'] ?? null)) {
            $application = Application::query()->findOrFail($filters['application']);
            abort_unless(OscAccess::canManage($user) || $user->canAccessApplication($application), 403);
            abort_unless(OscAccess::canManage($user) || $user->canPerformOsc('osc.agenda.propose', (string) $application->responsible_department_id), 403);
            $pickable = Meeting::query()
                ->whereIn('status', [MeetingStatus::Draft->value, MeetingStatus::AgendaPublished->value])
                ->where('scheduled_at', '>', now()->toIso8601String())->orderBy('scheduled_at')->get();
        }

        // Same rule as OscAccess::canViewMeeting: member of a non-draft meeting, presenter or proposer of an item,
        // or holder of osc.agenda.endorse for an item's department.
        $endorsing = OscAccess::canManage($user) ? [] : OscAccess::endorsingDepartments($user);
        $awaiting = array_values(array_intersect($endorsing,
            DepartmentSetting::query()->where('requires_endorsement', true)->pluck('department_id')->all()));
        $meetings = Meeting::query()
            ->when(! OscAccess::canManage($user), fn (Builder $query) => $query->where(fn (Builder $visible) => $visible
                ->where(fn (Builder $member) => $member
                    ->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id))
                    ->where('status', '!=', MeetingStatus::Draft->value))
                ->orWhereHas('items', fn (Builder $items) => $items->where(fn (Builder $own) => $own
                    ->where('presenter_id', $user->id)
                    ->orWhere('proposed_by', $user->id)
                    ->when($endorsing !== [], fn (Builder $endorse) => $endorse->orWhereIn('department_id', $endorsing))))))
            ->withCount(['items as awaiting_endorsement_count' => fn (Builder $items) => $awaiting === []
                ? $items->whereRaw('1 = 0')
                : $items->where('status', AgendaItemStatus::Proposed->value)->whereIn('department_id', $awaiting)])
            ->orderByDesc('scheduled_at')->orderByDesc('id')->get();

        return view('osc.index', [
            'meetings' => $meetings,
            'application' => $application,
            'pickable' => $pickable,
            'canManage' => OscAccess::canManage($user),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(OscAccess::canManage($request->user()), 403);

        return view('osc.create', ['staff' => $this->staff()]);
    }

    public function store(StoreMeetingRequest $request, MeetingService $meetings): RedirectResponse
    {
        $meeting = $meetings->create($request->meetingData(), $request->user());

        return to_route('osc.meetings.show', $meeting)->with('status', 'Mesyuarat '.$meeting->number.' telah dicipta.');
    }

    public function show(Request $request, Meeting $meeting): View
    {
        $user = $request->user();
        abort_unless(OscAccess::canViewMeeting($user, $meeting), 403);
        $canManage = OscAccess::canManage($user);
        $departments = DB::table('departments')->pluck('name', 'id');
        $data = [
            'meeting' => $meeting,
            'canManage' => $canManage,
            'locked' => in_array($meeting->status, [MeetingStatus::Closed, MeetingStatus::MinutesConfirmed], true),
            'departments' => $departments,
            'members' => $meeting->members()->with('user')->orderBy('id')->get(),
            'canPropose' => $canManage || $user->canPerformOsc('osc.agenda.propose'),
        ];

        if ($canManage) {
            $items = $meeting->items()->with(['presenter', 'papers'])->get();
            $data += [
                'proposals' => $items->whereIn('status', [AgendaItemStatus::Proposed, AgendaItemStatus::Endorsed])->values(),
                'returned' => $items->where('status', AgendaItemStatus::Returned)->values(),
                'accepted' => $items->where('status', AgendaItemStatus::Accepted)->values(),
                'versions' => $meeting->versions()->reorder()->orderByDesc('version')->get(),
                'endorsing' => DepartmentSetting::query()->where('requires_endorsement', true)->pluck('department_id')->all(),
            ];

            return view('osc.show', $data);
        }

        $endorsable = OscAccess::endorsableDepartments($user, $meeting);
        $data['endorsable'] = $endorsable;
        $data['endorsing'] = DepartmentSetting::query()->where('requires_endorsement', true)->pluck('department_id')->all();
        $data['mine'] = $meeting->items()->where(fn (Builder $own) => $own->where('presenter_id', $user->id)
            ->orWhere('proposed_by', $user->id)->orWhereIn('department_id', $endorsable))->with(['presenter', 'papers'])->get();
        $data['agenda'] = collect();
        $data['published'] = null;
        if ($meeting->status !== MeetingStatus::Draft && OscAccess::isMember($user, $meeting)) {
            $data['published'] = $meeting->versions()->reorder()->orderByDesc('version')->first();
            if ($data['published'] !== null) {
                $order = collect($data['published']->items)->pluck('id')->flip();
                $data['agenda'] = $meeting->items()->reorder()->where('status', AgendaItemStatus::Accepted->value)
                    ->whereIn('id', $order->keys())->with(['presenter', 'papers'])->get()
                    ->sortBy(fn (AgendaItem $item) => $order[$item->id])->values();
            }
        }

        return view('osc.show', $data);
    }

    public function reorder(ReorderAgendaRequest $request, Meeting $meeting, AgendaService $agenda): RedirectResponse
    {
        $agenda->reorder($meeting, $request->validated('order'), (int) $request->validated('expected_version'), $request->user());

        return to_route('osc.meetings.show', $meeting)->with('status', 'Susunan agenda telah dikemas kini.');
    }

    public function publish(Request $request, Meeting $meeting, AgendaService $agenda): RedirectResponse
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $version = $agenda->publish($meeting, (int) $data['expected_version'], $request->user());

        return to_route('osc.meetings.show', $meeting)->with('status', 'Agenda versi '.$version->version.' telah diterbitkan.');
    }

    /** @return Collection<int, User> */
    private function staff()
    {
        return User::query()->where('account_type', 'staff')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'department_id']);
    }
}
