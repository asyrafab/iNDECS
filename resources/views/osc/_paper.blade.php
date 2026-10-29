@php($latest = $item->papers->first())
<div class="agenda-row-meta">
    @forelse ($item->papers as $paper)
        <a class="text-link" href="{{ route('osc.papers.show', $paper) }}" target="_blank" rel="noopener"><x-icon name="file-text" />Kertas v{{ $paper->version }}</a>
    @empty
        <span>Tiada kertas kerja dimuat naik.</span>
    @endforelse
</div>
@if (! empty($upload))
    <form method="POST" action="{{ route('osc.papers.store', $item) }}" enctype="multipart/form-data" class="inline-form">
        @csrf
        <label class="field"><span class="sr-only">Fail PDF kertas kerja untuk {{ $item->title }}</span><input type="file" name="paper" accept="application/pdf" required></label>
        <button type="submit" class="button button-secondary button-small">{{ $latest ? 'Muat naik versi baharu' : 'Muat naik kertas' }}</button>
    </form>
@endif
