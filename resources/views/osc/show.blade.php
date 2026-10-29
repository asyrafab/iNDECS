@extends('layouts.app')
@section('title', 'Mesyuarat '.$meeting->number)
@section('content')
@php
    $meetingBadge = ['draft' => 'status-pending', 'agenda_published' => 'status-info', 'in_progress' => 'status-attention', 'closed' => 'status-compliant', 'minutes_confirmed' => 'status-compliant'];
    $itemBadge = ['proposed' => 'status-pending', 'endorsed' => 'status-info', 'accepted' => 'status-compliant', 'returned' => 'status-attention'];
    $acceptsProposals = ! in_array($meeting->status, [\App\Enums\Osc\MeetingStatus::InProgress, \App\Enums\Osc\MeetingStatus::Closed, \App\Enums\Osc\MeetingStatus::MinutesConfirmed], true);
    $viewer = auth()->user();
@endphp
<div class="record-heading">
    <div>
        <a href="{{ route('osc.meetings.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a>
        <div class="record-reference-row"><span class="record-type">OSC</span><span class="record-reference">{{ $meeting->number }}</span><span class="status-badge {{ $meetingBadge[$meeting->status->value] }}">{{ $meeting->status->label() }}</span></div>
        <h1>Mesyuarat OSC {{ $meeting->number }}</h1>
        <p>{{ \App\Services\Osc\OscTime::format($meeting->scheduled_at) }} · {{ $meeting->venue }}</p>
        <p class="muted">Tutup cadangan {{ \App\Services\Osc\OscTime::format($meeting->proposal_cutoff_at) }} · tutup kertas kerja {{ \App\Services\Osc\OscTime::format($meeting->paper_cutoff_at) }}</p>
    </div>
    @if ($canPropose && $acceptsProposals)
        <a href="{{ route('osc.items.create', $meeting) }}" class="button button-secondary"><x-icon name="plus" />Cadang perkara</a>
    @endif
</div>
@if ($errors->any())<div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div>@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
@if ($locked)<div class="alert alert-info" role="status"><x-icon name="circle-alert" /><div>Agenda mesyuarat ini tidak lagi boleh diubah.</div></div>@endif

<div class="panel-stack">
@if ($canManage)
    <section class="content-card" id="cadangan">
        <div class="card-heading"><h2>Cadangan</h2><span class="muted">{{ $proposals->count() }} menunggu tindakan</span></div>
        @if ($proposals->isEmpty())
            <p class="muted">Tiada cadangan menunggu tindakan.</p>
        @else
            <ul class="agenda-list">
                @foreach ($proposals as $item)
                    @php
                        $needsEndorsement = $item->status === \App\Enums\Osc\AgendaItemStatus::Proposed && in_array($item->department_id, $endorsing, true);
                    @endphp
                    <li class="agenda-row">
                        <span class="agenda-row-number" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div class="agenda-row-body">
                            <strong>{{ $item->title }}</strong>
                            <p class="agenda-row-meta"><span class="status-badge {{ $itemBadge[$item->status->value] }}">{{ $item->status->label() }}</span><span>{{ $item->reference ?: $item->standalone_type }}</span><span>{{ $item->purpose->label() }}</span><span>{{ $departments[$item->department_id] ?? $item->department_id }}</span><span>Penyampai: {{ $item->presenter?->name }}</span></p>
                            @include('osc._paper', ['item' => $item, 'upload' => ! $locked])
                            @if (! $locked)
                                <form method="POST" action="{{ route('osc.items.return', $item) }}" class="inline-form" data-confirm="Kembalikan cadangan ini kepada jabatan?">
                                    @csrf
                                    <input type="hidden" name="expected_version" value="{{ $item->row_version }}">
                                    <label class="field"><span class="sr-only">Sebab pengembalian untuk {{ $item->title }}</span><input type="text" name="reason" maxlength="2000" placeholder="Sebab pengembalian" required></label>
                                    <button type="submit" class="button button-danger button-small">Kembalikan</button>
                                </form>
                            @endif
                        </div>
                        @if (! $locked)
                            <div class="agenda-row-actions">
                                @if ($item->status === \App\Enums\Osc\AgendaItemStatus::Proposed && $viewer->canPerformOsc('osc.agenda.endorse', $item->department_id))
                                    <form method="POST" action="{{ route('osc.items.endorse', $item) }}">@csrf<input type="hidden" name="expected_version" value="{{ $item->row_version }}"><button type="submit" class="button button-secondary button-small">Sokong</button></form>
                                @endif
                                @if ($needsEndorsement)
                                    <span class="muted">Menunggu sokongan ketua jabatan</span>
                                @else
                                    <form method="POST" action="{{ route('osc.items.accept', $item) }}">@csrf<input type="hidden" name="expected_version" value="{{ $item->row_version }}"><button type="submit" class="button button-primary button-small">Terima</button></form>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($returned->isNotEmpty())
            <h3 class="subsection-title">Dikembalikan <span>{{ $returned->count() }}</span></h3>
            <ul class="agenda-list">
                @foreach ($returned as $item)
                    <li class="agenda-row"><span class="agenda-row-number" aria-hidden="true">{{ $loop->iteration }}</span><div class="agenda-row-body"><strong>{{ $item->title }}</strong><p class="agenda-row-meta"><span class="status-badge {{ $itemBadge[$item->status->value] }}">{{ $item->status->label() }}</span><span>{{ $departments[$item->department_id] ?? $item->department_id }}</span><span>Sebab: {{ $item->return_reason }}</span></p></div></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="content-card" id="agenda">
        <div class="card-heading"><h2>Agenda</h2><span class="muted">{{ $accepted->count() }} perkara</span>
            @if (! $locked && $accepted->isNotEmpty())
                <form method="POST" action="{{ route('osc.meetings.publish', $meeting) }}" data-confirm="Terbitkan agenda kepada ahli mesyuarat?">@csrf<input type="hidden" name="expected_version" value="{{ $meeting->row_version }}"><button type="submit" class="button button-primary button-small"><x-icon name="check" />Terbitkan agenda</button></form>
            @endif
        </div>
        @if ($accepted->isEmpty())
            <p class="muted">Belum ada perkara dalam agenda. Cadangan yang diluluskan akan dipaparkan di sini.</p>
        @else
            <ol class="agenda-list">
                @foreach ($accepted as $item)
                    @php
                        $ids = $accepted->pluck('id')->all();
                        $index = $loop->index;
                        $up = $ids;
                        $down = $ids;
                        if ($index > 0) { [$up[$index - 1], $up[$index]] = [$up[$index], $up[$index - 1]]; }
                        if ($index < count($ids) - 1) { [$down[$index + 1], $down[$index]] = [$down[$index], $down[$index + 1]]; }
                    @endphp
                    <li class="agenda-row">
                        <span class="agenda-row-number">{{ $loop->iteration }}</span>
                        <div class="agenda-row-body">
                            <strong>{{ $item->title }}</strong>
                            <p class="agenda-row-meta"><span>{{ $item->reference ?: $item->standalone_type }}</span><span>{{ $item->purpose->label() }}</span><span>{{ $departments[$item->department_id] ?? $item->department_id }}</span><span>Penyampai: {{ $item->presenter?->name }}</span>@if ($item->isMissingPaper())<span class="status-badge status-attention">Kertas belum diterima</span>@endif</p>
                            @include('osc._paper', ['item' => $item, 'upload' => ! $locked])
                            @if (! $locked)
                                <form method="POST" action="{{ route('osc.items.return', $item) }}" class="inline-form" data-confirm="Kembalikan perkara ini kepada jabatan?">
                                    @csrf
                                    <input type="hidden" name="expected_version" value="{{ $item->row_version }}">
                                    <label class="field"><span class="sr-only">Sebab pengembalian untuk {{ $item->title }}</span><input type="text" name="reason" maxlength="2000" placeholder="Sebab pengembalian" required></label>
                                    <button type="submit" class="button button-danger button-small">Kembalikan</button>
                                </form>
                            @endif
                        </div>
                        @if (! $locked)
                            <div class="agenda-row-actions">
                                @foreach ([['up', $up, 'arrow-up', 'Naikkan', $index === 0], ['down', $down, 'chevron-down', 'Turunkan', $index === count($ids) - 1]] as [$direction, $order, $icon, $label, $disabled])
                                    <form method="POST" action="{{ route('osc.meetings.reorder', $meeting) }}">
                                        @csrf
                                        <input type="hidden" name="expected_version" value="{{ $meeting->row_version }}">
                                        @foreach ($order as $id)<input type="hidden" name="order[]" value="{{ $id }}">@endforeach
                                        <button type="submit" class="icon-button" aria-label="{{ $label }} {{ $item->title }}" title="{{ $label }}" @disabled($disabled)><x-icon :name="$icon" /></button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <section class="content-card" id="versi">
        <div class="card-heading"><h2>Versi</h2><span class="muted">{{ $versions->count() }} diterbitkan</span></div>
        @if ($versions->isEmpty())
            <p class="muted">Agenda belum diterbitkan.</p>
        @else
            <ol class="agenda-list">
                @foreach ($versions as $version)
                    <li class="agenda-row">
                        <span class="agenda-row-number">{{ $version->version }}</span>
                        <div class="agenda-row-body"><strong>Versi {{ $version->version }} · diterbitkan {{ \App\Services\Osc\OscTime::format($version->published_at) }}</strong>
                            <p class="agenda-row-meta"><span>{{ count($version->items) }} perkara</span><span>{{ count($version->changes['added'] ?? []) }} ditambah</span><span>{{ count($version->changes['removed'] ?? []) }} dibuang</span><span>{{ ($version->changes['reordered'] ?? false) ? 'Susunan diubah' : 'Susunan tidak berubah' }}</span></p></div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@else
    @if ($mine->isNotEmpty())
    <section class="content-card" id="cadangan-saya">
        <div class="card-heading"><h2>{{ count($endorsable) ? 'Cadangan' : 'Cadangan saya' }}</h2><span class="muted">{{ $mine->count() }} perkara</span></div>
        <ul class="agenda-list">
            @foreach ($mine as $item)
                <li class="agenda-row">
                    <span class="agenda-row-number" aria-hidden="true">{{ $loop->iteration }}</span>
                    <div class="agenda-row-body">
                        <strong>{{ $item->title }}</strong>
                        <p class="agenda-row-meta"><span class="status-badge {{ $itemBadge[$item->status->value] }}">{{ $item->status->label() }}</span><span>{{ $item->reference ?: $item->standalone_type }}</span><span>{{ $item->purpose->label() }}</span><span>{{ $departments[$item->department_id] ?? $item->department_id }}</span></p>
                        @if ($item->status === \App\Enums\Osc\AgendaItemStatus::Returned && $item->return_reason)<p class="agenda-row-meta"><span class="status-badge status-attention">Sebab pengembalian</span><span>{{ $item->return_reason }}</span></p>@endif
                        @include('osc._paper', ['item' => $item, 'upload' => ! $locked && ($item->presenter_id === $viewer->id || $item->proposed_by === $viewer->id)])
                    </div>
                    @if (! $locked && $item->status === \App\Enums\Osc\AgendaItemStatus::Proposed && in_array($item->department_id, $endorsable, true) && in_array($item->department_id, $endorsing, true))
                        <div class="agenda-row-actions"><form method="POST" action="{{ route('osc.items.endorse', $item) }}">@csrf<input type="hidden" name="expected_version" value="{{ $item->row_version }}"><button type="submit" class="button button-secondary button-small">Sokong</button></form></div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @endif
    @if ($published)
    <section class="content-card" id="agenda">
        <div class="card-heading"><h2>Agenda</h2><span class="muted">Versi {{ $published->version }} · diterbitkan {{ \App\Services\Osc\OscTime::format($published->published_at) }}</span></div>
        <ol class="agenda-list">
            @foreach ($agenda as $item)
                @php($paper = $item->papers->first())
                <li class="agenda-row">
                    <span class="agenda-row-number">{{ $loop->iteration }}</span>
                    <div class="agenda-row-body">
                        <strong>{{ $item->title }}</strong>
                        <p class="agenda-row-meta"><span>{{ $item->reference ?: $item->standalone_type }}</span><span>{{ $item->purpose->label() }}</span><span>{{ $departments[$item->department_id] ?? $item->department_id }}</span><span>Penyampai: {{ $item->presenter?->name }}</span></p>
                        @if ($paper)<p class="agenda-row-meta"><a class="text-link" href="{{ route('osc.papers.show', $paper) }}" target="_blank" rel="noopener"><x-icon name="file-text" />Kertas kerja</a></p>@else<p class="agenda-row-meta"><span>Kertas kerja belum dimuat naik.</span></p>@endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
    @elseif ($mine->isEmpty())
        <section class="content-card"><div class="empty-state compact"><h3>Agenda belum diterbitkan</h3><p>Agenda akan dipaparkan di sini selepas diterbitkan oleh setiausaha.</p></div></section>
    @endif
@endif
</div>
@endsection
