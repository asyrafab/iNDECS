@php
    $sectionTitles = ['action' => 'Perlu tindakan anda', 'active' => 'Draf belum dihantar', 'closed' => 'Selesai / Ditutup'];
    $sections = array_filter($board['sections'], fn ($items) => $items->isNotEmpty());
@endphp
<div class="file-board">
    <div class="file-board-head">
        <div><span class="eyebrow">Kaunter Maya MBSP</span><h1>Fail saya <span class="file-board-count">{{ $board['draftCount'] }}</span></h1></div>
        <a class="button button-primary" href="#portal-new" data-portal-open="portal-new" aria-haspopup="dialog"><x-icon name="file-plus-2" />Permohonan baharu</a>
    </div>
    @forelse($sections as $key => $items)
        <section class="file-section" data-section="{{ $key }}" aria-labelledby="file-section-{{ $key }}">
            <h2 id="file-section-{{ $key }}">{{ $sectionTitles[$key] }}</h2>
            <ul class="file-list">
                @foreach($items as $draft)
                    <li class="file-row">
                        <div class="file-row-main">
                            <h3><a href="{{ route('portal.drafts.edit', $draft) }}" aria-label="Sambung draf {{ $draft->title }}">{{ $draft->title }}</a></h3>
                            <p>{{ $draft->applicationType->name }}</p>
                            <div class="file-row-meta">
                                <span class="status-badge status-pending">Draf · {{ $draft->application_type_id }}</span>
                                @isset($board['progress'][$draft->id])<span class="status-badge {{ $board['progress'][$draft->id]['ready'] === $board['progress'][$draft->id]['total'] && $board['progress'][$draft->id]['total'] > 0 ? 'status-compliant' : 'status-info' }}">Dokumen {{ $board['progress'][$draft->id]['ready'] }}/{{ $board['progress'][$draft->id]['total'] }}</span>@endisset
                            </div>
                            <small>Belum dihantar kepada MBSP · Dikemas kini <time datetime="{{ $draft->updated_at->toIso8601String() }}">{{ $draft->updated_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }}</time></small>
                        </div>
                        <div class="file-row-remove">
                            @include('public.drafts.partials.remove', ['iconOnly' => true])
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="file-board-empty">
            <span class="portal-choice-icon"><x-icon name="folder-open" /></span>
            <h2>Belum ada fail</h2>
            <p>Mulakan permohonan baharu untuk menyediakan draf maklumat awal. AMIRA di sebelah boleh memberi panduan tempatan.</p>
            <a class="button button-primary" href="#portal-new" data-portal-open="portal-new" aria-haspopup="dialog"><x-icon name="file-plus-2" />Permohonan baharu</a>
        </section>
    @endforelse
    @if($board['draftCount'] > \App\Services\Portal\PortalBoard::ACTIVE_LIMIT)
        <a class="file-board-more" href="{{ route('portal.drafts.index') }}">Semua draf ({{ $board['draftCount'] }})<x-icon name="arrow-right" /></a>
    @endif
    <p class="file-board-note"><x-icon name="circle-dot" />Pilih kad untuk menyambung draf. Draf belum dihantar kepada MBSP.</p>
</div>
