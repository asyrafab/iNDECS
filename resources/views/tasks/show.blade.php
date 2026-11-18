@extends('layouts.app')
@section('title', $task->title)
@section('content')
<div class="page-heading"><a class="text-link" href="{{ route('tasks.index') }}">Kembali ke tugasan</a><span class="eyebrow">{{ $task->department_id }} · Tugasan pegawai</span><h1>{{ $task->title }}</h1><p>{{ $task->instructions }}</p></div>
<section class="content-card">
    <div class="card-heading"><h2>Butiran tugasan</h2><span class="status-badge status-{{ $task->status === 'completed' ? 'compliant' : 'info' }}">{{ \App\Models\OfficerTask::STATUSES[$task->status] }}</span></div>
    <dl class="detail-grid"><div><dt>Permohonan</dt><dd><a href="{{ $recordUrl }}">{{ $task->application->application_reference }}</a> · {{ $task->application->title }}</dd></div><div><dt>Pegawai</dt><dd>{{ $task->assignee->name }}</dd></div><div><dt>Tarikh sasaran manual</dt><dd>{{ $task->due_date?->format('d/m/Y') ?? 'Tidak ditetapkan' }}</dd></div><div><dt>Semakan rekod</dt><dd>{{ $task->revision }}</dd></div></dl>
    <p class="muted">Tindakan tugasan direkodkan berasingan daripada peringkat, SLA dan keputusan permohonan.</p>
</section>
@if (in_array($task->status, ['open', 'in_progress']))
    @if ($canWork && $task->status === 'open')
    <form method="POST" action="{{ route('tasks.act', $task) }}" class="content-card">@csrf<input type="hidden" name="revision" value="{{ $task->revision }}"><input type="hidden" name="action" value="start"><button class="button button-primary" type="submit">Mulakan tugasan</button></form>
    @elseif ($canWork)
    <form method="POST" action="{{ route('tasks.act', $task) }}" class="form-card">@csrf<input type="hidden" name="revision" value="{{ old('action') === 'complete' ? old('revision') : $task->revision }}"><input type="hidden" name="action" value="complete">
        <div class="card-heading"><h2>Rekodkan penyelesaian</h2></div>
        <label class="field"><span>Catatan penyelesaian *</span><textarea name="note" rows="4" required maxlength="5000">{{ old('action') === 'complete' ? old('note') : '' }}</textarea></label>
        <fieldset><legend>Bukti dokumen (pilihan)</legend><p class="muted">Versi dokumen yang dipilih disimpan sebagai bukti tindakan ini. Dokumen terdahulu ditandakan sebagai sejarah.</p>@forelse ($documents as $document)<label class="checkbox-field"><input type="checkbox" name="document_ids[]" value="{{ $document->id }}" @checked(old('action') === 'complete' && in_array($document->id, old('document_ids', [])))><span>{{ $document->original_name }} · versi {{ $document->revision_number }}{{ $document->is_current ? '' : ' · sejarah' }}</span></label>@empty<p>Tiada dokumen yang telah diimbas.</p>@endforelse</fieldset>
        <div class="form-actions"><button class="button button-primary" type="submit">Selesaikan tugasan</button></div>
    </form>
    @endif
    @if ($canAssign)
    <section class="content-card"><h2>Urus agihan</h2>
        <form method="POST" action="{{ route('tasks.act', $task) }}" class="public-form">@csrf<input type="hidden" name="revision" value="{{ old('action') === 'assign' ? old('revision') : $task->revision }}"><input type="hidden" name="action" value="assign">
            <label class="field"><span>Pegawai</span><select name="assignee_id" required>@foreach ($assignees as $officer)<option value="{{ $officer->id }}" @selected((int) old('assignee_id', $task->assignee_id) === $officer->id)>{{ $officer->name }}</option>@endforeach</select></label>
            <label class="field"><span>Tarikh sasaran manual</span><input name="due_date" type="date" value="{{ old('action') === 'assign' ? old('due_date') : $task->due_date?->format('Y-m-d') }}"></label>
            <label class="field"><span>Sebab agihan / perubahan *</span><textarea name="note" rows="2" required maxlength="5000">{{ old('action') === 'assign' ? old('note') : '' }}</textarea></label>
            <button class="button button-secondary" type="submit">Simpan agihan</button>
        </form>
        <details><summary>Batalkan tugasan</summary><form method="POST" action="{{ route('tasks.act', $task) }}" class="public-form">@csrf<input type="hidden" name="revision" value="{{ old('action') === 'cancel' ? old('revision') : $task->revision }}"><input type="hidden" name="action" value="cancel"><label class="field"><span>Sebab pembatalan *</span><textarea name="note" rows="2" required maxlength="5000">{{ old('action') === 'cancel' ? old('note') : '' }}</textarea></label><button class="button button-secondary" type="submit">Rekod pembatalan</button></form></details>
    </section>
    @endif
@endif
<section class="content-card"><div class="card-heading"><h2>Sejarah tindakan</h2></div>
    @foreach ($task->actions as $action)<article class="detail-card"><h3>{{ ['created' => 'Dicipta dan diagih', 'start' => 'Dimulakan', 'assign' => 'Agihan dikemas kini', 'complete' => 'Diselesaikan', 'cancel' => 'Dibatalkan'][$action->action] }}</h3><p>{{ $action->actor->name }} · {{ $action->created_at->timezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('d/m/Y H:i') }} · semakan {{ $action->revision }}</p><p>{{ $action->note }}</p><p class="muted">{{ $action->after_values['assignee_name'] }} · {{ \App\Models\OfficerTask::STATUSES[$action->after_values['status']] }} · sasaran manual {{ $action->after_values['due_date'] ?? 'tidak ditetapkan' }}</p>
        @foreach ($action->evidence as $pin)<p><a href="{{ route('tasks.evidence', [$task, $action, $pin['document_id']]) }}">{{ $pin['name'] }} · versi {{ $pin['revision'] }}</a></p>@endforeach
    </article>@endforeach
</section>
@endsection
