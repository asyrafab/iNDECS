{{-- AMIRA as the Kaunter Maya officer. With $chat (ADR-369) the composer talks to AMIRA on the local model through
     iNDECS; without it, local guidance only. Never a submission, payment or status change. --}}
@php($chat ??= null)
<div class="amira-counter-identity">
    <div class="amira-bubble" data-amira-bubble data-mood="{{ $board['mood']->value }}" data-speaking="false" role="img" aria-label="AMIRA"><canvas aria-hidden="true"></canvas></div>
    <div data-amira-name><h2 id="amira-counter-title">AMIRA</h2><p>Pegawai Kaunter Maya</p></div>
    <button class="icon-button amira-counter-close" type="button" data-amira-sheet-close aria-label="Tutup AMIRA"><x-icon name="x" /></button>
</div>
<div class="amira-counter-conversation" data-amira-scroll tabindex="0" aria-label="Sejarah perbualan AMIRA">
    @unless($chat)
    <button class="amira-chip" type="button" data-amira-older hidden>Muat perbualan terdahulu</button>
    <div class="amira-counter-thread" data-amira-thread></div>
    @endunless
    <div class="amira-counter-fallback" data-amira-fallback>
    <p class="amira-counter-message">{{ $board['greeting'] }}</p>
    @if($board['todos'] !== [])
        <div class="amira-counter-message">
            <p>Perkara untuk tindakan anda:</p>
            <ul class="amira-counter-todos">
                @foreach($board['todos'] as $todo)<li><a href="{{ $todo['href'] }}">{{ $todo['text'] }}<x-icon name="arrow-right" /></a></li>@endforeach
            </ul>
        </div>
    @else
        <p class="amira-counter-message">Tiada tindakan diperlukan hari ini.</p>
    @endif
    </div>
    <div class="amira-counter-interactive" data-amira-interactive hidden>
    <div class="amira-counter-prompts">
        <button type="button" class="amira-chip" data-amira-prompt="page">Panduan halaman ini</button>
        <button type="button" class="amira-chip" data-amira-prompt="fields">Bantu isi borang</button>
        <button type="button" class="amira-chip" data-amira-prompt="next">Langkah seterusnya</button>
    </div>
    @if($chat)<div class="amira-counter-user" data-amira-user hidden></div>
    <section class="amira-counter-answer" data-amira-answer aria-live="polite" aria-atomic="true" hidden>
        <span class="eyebrow">Panduan tempatan</span><h3 data-amira-answer-title></h3><p data-amira-answer-copy></p>
    </section>
    @endif
    </div>
    @if($chat)
        @include('partials.amira-thread', ['chat' => $chat])
        <p class="sr-only" data-amira-announce aria-live="polite"></p>
    @endif
</div>
@unless($chat)
<template data-amira-turn-template>
    <div class="amira-counter-turn">
        <div class="amira-counter-user" data-amira-user hidden></div>
        <section class="amira-counter-answer" data-amira-answer aria-live="polite" aria-atomic="true" hidden>
            <span class="eyebrow">Panduan tempatan</span><h3 data-amira-answer-title></h3><p data-amira-answer-copy></p>
        </section>
    </div>
</template>
<p class="amira-counter-error" data-amira-history-error role="status" hidden></p>
<button class="amira-chip" type="button" data-amira-retry hidden>Cuba semula</button>
@endunless
@if($chat && $chat['consented'])
    <form class="amira-counter-composer" data-amira-form data-amira-chat data-ask-url="{{ $chat['askUrl'] }}" data-amira-interactive hidden>
        <label for="amira-counter-question">Tanya AMIRA</label>
        <div><textarea id="amira-counter-question" name="question" rows="2" maxlength="{{ $chat['maxChars'] }}" autocomplete="off" data-amira-input placeholder="Contoh: Tambahan 1 tingkat rumah teres. Dokumen apa perlu?"></textarea><button type="submit" class="button button-primary" aria-label="Hantar soalan kepada AMIRA"><x-icon name="arrow-up" /></button></div>
        <p class="amira-counter-error" data-amira-error role="alert" hidden></p>
    </form>
@elseif($chat)
    {{-- ADR-369: AI chat stays locked until the PSP agrees; local guidance chips keep working. --}}
    <div class="amira-counter-composer amira-consent-prompt">
        <p>AMIRA perlukan persetujuan anda sebelum berbual menggunakan maklumat akaun dan draf anda.</p>
        <a class="button button-primary button-small" href="#amira-consent" data-portal-open="amira-consent" aria-haspopup="dialog">Baca persetujuan</a>
    </div>
    @include('partials.amira-consent', ['retentionMonths' => $chat['retentionMonths']])
@else
    <form class="amira-counter-composer" data-amira-form data-amira-interactive hidden>
        <label for="amira-counter-question">Tanya AMIRA (panduan tempatan)</label>
        <div><input id="amira-counter-question" name="question" maxlength="300" autocomplete="off" data-amira-input placeholder="Bagaimana saya boleh teruskan?"><button type="submit" class="button button-primary" aria-label="Hantar soalan kepada panduan AMIRA"><x-icon name="arrow-up" /></button></div>
        <p class="amira-counter-availability"><span class="status-badge status-pending">Perbualan AI belum tersedia</span></p>
    </form>
@endif
