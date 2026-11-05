@props(['board', 'guide' => 'Fail saya menyenaraikan draf anda. Pilih Permohonan baharu untuk memilih jenis dan menyediakan draf. Muat naik dokumen, semakan awal, bayaran dan penghantaran rasmi belum dibuka.'])
{{-- Kaunter Maya split (ADR-361): AMIRA on the left, the applicant's file on the right. --}}
<div class="counter">
    {{-- Phone: AMIRA waits in a bottom bar and opens as a full-height sheet. --}}
    <button class="amira-counter-bar" type="button" data-amira-sheet-open aria-controls="amira-counter" aria-expanded="false">
        <span class="amira-bubble amira-bubble-small" data-amira-bubble data-mood="{{ $board['mood']->value }}" data-speaking="false" aria-hidden="true"><canvas></canvas></span>
        <span class="amira-counter-bar-text"><strong>Tanya AMIRA</strong><small>{{ $board['todos'] === [] ? 'Pegawai Kaunter Maya' : count($board['todos']).' perkara perlu tindakan' }}</small></span>
        <x-icon name="messages-square" />
    </button>
    <aside class="counter-amira" id="amira-counter" aria-labelledby="amira-counter-title" data-amira-guidance data-guide="{{ $guide }}">
        @include('partials.amira-counter', ['board' => $board])
    </aside>
    <section class="counter-file">{{ $file }}</section>
</div>
