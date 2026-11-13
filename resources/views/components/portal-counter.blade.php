@props(['board', 'draft' => null, 'guide' => 'Fail saya menyenaraikan draf anda. Pilih Permohonan baharu untuk memilih jenis dan menyediakan draf. Muat naik dokumen, semakan awal, bayaran dan penghantaran rasmi belum dibuka.'])
{{-- ADR-369: the AMIRA conversation for this page (home, or one saved draft); null when chat is switched off. --}}
@php($chat = auth()->user() ? app(\App\Services\Amira\ChatPanel::class)->forPage(auth()->user(), $draft?->exists ? $draft : null, onForm: $draft !== null) : null)
{{-- Kaunter Maya split (ADR-361): AMIRA on the left, the applicant's file on the right. --}}
<div class="counter">
    {{-- Phone: AMIRA waits in a bottom bar and opens as a full-height sheet. --}}
    <button class="amira-counter-bar" type="button" data-amira-sheet-open aria-controls="amira-counter" aria-expanded="false">
        <span class="amira-bubble amira-bubble-small" data-amira-bubble data-mood="{{ $board['mood']->value }}" data-speaking="false" aria-hidden="true"><canvas></canvas></span>
        <span class="amira-counter-bar-text"><strong>Tanya AMIRA</strong><small>{{ $board['todos'] === [] ? 'Pegawai Kaunter Maya' : count($board['todos']).' perkara perlu tindakan' }}</small></span>
        <x-icon name="messages-square" />
    </button>
    <aside class="counter-amira" id="amira-counter" aria-labelledby="amira-counter-title" data-amira-guidance data-guide="{{ $guide }}"
        data-amira-entrance @unless($chat) data-amira-session data-history-url="{{ route('portal.amira.history') }}" data-message-url="{{ route('portal.amira.messages') }}" data-draft-id="{{ $draft?->exists ? $draft->id : '' }}" @endunless>
        {{-- Desktop: drag (or arrow keys) to change the panel width; double-click resets. Shown by portal.js. --}}
        <div class="counter-resize" data-amira-resize role="separator" aria-orientation="vertical" aria-label="Ubah lebar panel AMIRA" tabindex="0" hidden></div>
        @include('partials.amira-counter', ['board' => $board, 'chat' => $chat])
    </aside>
    <section class="counter-file">{{ $file }}</section>
</div>
