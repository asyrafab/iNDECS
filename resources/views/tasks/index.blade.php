@extends('layouts.app')
@section('title', 'Tugasan pegawai')
@section('content')
<div class="page-heading heading-with-action">
    <div><span class="eyebrow">Ruang kerja bersama</span><h1>Tugasan pegawai</h1><p>Kerja yang diagihkan kepada anda dan pasukan dalam skop akses rekod anda.</p></div>
    @if ($canAssign)<a class="button button-primary" href="{{ route('tasks.create') }}"><x-icon name="plus" />Cipta tugasan</a>@endif
</div>
<section class="content-card">
    <div class="card-heading"><h2>Senarai tugasan</h2><span class="muted">{{ $tasks->total() }} rekod</span></div>
    <form method="GET" action="{{ route('tasks.index') }}" class="filter-bar">
        <label><span class="sr-only">Cari tugasan atau rujukan</span><input name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="255" placeholder="Tugasan atau rujukan permohonan"></label>
        <label><span class="sr-only">Skop tugasan</span><select name="scope"><option value="mine" @selected(($filters['scope'] ?? 'mine') === 'mine' || ! $canAssign)>Tugasan saya</option>@if ($canAssign)<option value="team" @selected(($filters['scope'] ?? '') === 'team')>Tugasan pasukan</option>@endif</select></label>
        <label><span class="sr-only">Jabatan</span><select name="department"><option value="">Semua jabatan dibenarkan</option>@foreach ($departments as $department)<option value="{{ $department }}" @selected(($filters['department'] ?? '') === $department)>{{ $department }}</option>@endforeach</select></label>
        <label><span class="sr-only">Status tugasan</span><select name="status"><option value="">Semua status</option>@foreach (\App\Models\OfficerTask::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="checkbox-field"><input type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false)><span>Lewat sahaja</span></label>
        <button class="button button-secondary" type="submit">Tapis</button><a class="button button-ghost" href="{{ route('tasks.index') }}">Kosongkan</a>
    </form>
    @if ($tasks->isEmpty())
        <div class="empty-state compact"><h2>Tiada tugasan ditemui</h2><p>Tugasan hanya dipaparkan apabila rekod dan skop pasukan dibenarkan.</p></div>
    @else
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Tugasan</th><th>Permohonan</th><th>Pegawai</th><th>Tarikh sasaran manual</th><th>Status</th></tr></thead><tbody>
        @foreach ($tasks as $task)<tr>
            <td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><small>{{ $task->department_id }}</small></td>
            <td>{{ $task->application->application_reference }}<small>{{ $task->application->title }}</small></td>
            <td>{{ $task->assignee->name }}</td>
            <td>{{ $task->due_date?->format('d/m/Y') ?? 'Tidak ditetapkan' }}@if ($task->due_date?->lt(today(config('app.display_timezone', 'Asia/Kuala_Lumpur'))) && in_array($task->status, ['open', 'in_progress']))<small class="muted">Melepasi tarikh sasaran</small>@endif</td>
            <td><span class="status-badge status-{{ $task->status === 'completed' ? 'compliant' : ($task->status === 'cancelled' ? 'pending' : 'info') }}">{{ \App\Models\OfficerTask::STATUSES[$task->status] }}</span></td>
        </tr>@endforeach
        </tbody></table></div>
        @if ($tasks->hasPages())<nav class="pagination" aria-label="Halaman tugasan">@if ($tasks->previousPageUrl())<a class="button button-ghost" href="{{ $tasks->previousPageUrl() }}">Sebelumnya</a>@endif<span>Halaman {{ $tasks->currentPage() }} / {{ $tasks->lastPage() }}</span>@if ($tasks->nextPageUrl())<a class="button button-ghost" href="{{ $tasks->nextPageUrl() }}">Seterusnya</a>@endif</nav>@endif
    @endif
    <p class="muted">Tarikh sasaran ditetapkan secara manual. Penyelesaian tugasan tidak mengubah keputusan atau peringkat permohonan.</p>
</section>
@endsection
