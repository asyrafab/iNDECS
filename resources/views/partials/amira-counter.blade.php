{{-- AMIRA as the Kaunter Maya officer. Local guidance only: no model call, no submission, no status change. --}}
<div class="amira-counter-identity">
    <div class="amira-bubble" data-amira-bubble data-mood="{{ $board['mood']->value }}" data-speaking="false" role="img" aria-label="AMIRA"><canvas aria-hidden="true"></canvas></div>
    <div><h2 id="amira-counter-title">AMIRA</h2><p>Pegawai Kaunter Maya</p></div>
    <button class="icon-button amira-counter-close" type="button" data-amira-sheet-close aria-label="Tutup AMIRA"><x-icon name="x" /></button>
</div>
<div class="amira-counter-conversation">
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
    <div class="amira-counter-interactive" data-amira-interactive hidden>
    <div class="amira-counter-prompts">
        <button type="button" class="amira-chip" data-amira-prompt="page">Panduan halaman ini</button>
        <button type="button" class="amira-chip" data-amira-prompt="fields">Bantu isi borang</button>
        <button type="button" class="amira-chip" data-amira-prompt="next">Langkah seterusnya</button>
    </div>
    <div class="amira-counter-user" data-amira-user hidden></div>
    <section class="amira-counter-answer" data-amira-answer aria-live="polite" aria-atomic="true" hidden>
        <span class="eyebrow">Panduan tempatan</span><h3 data-amira-answer-title></h3><p data-amira-answer-copy></p>
    </section>
    </div>
</div>
<form class="amira-counter-composer" data-amira-form data-amira-interactive hidden>
    <label for="amira-counter-question">Tanya AMIRA (panduan tempatan)</label>
    <div><input id="amira-counter-question" name="question" maxlength="300" autocomplete="off" data-amira-input placeholder="Bagaimana saya boleh teruskan?"><button type="submit" class="button button-primary" aria-label="Hantar soalan kepada panduan AMIRA"><x-icon name="arrow-up" /></button></div>
    <p class="amira-counter-availability"><span class="status-badge status-pending">Perbualan AI belum tersedia</span></p>
</form>
<footer class="amira-counter-footer"><x-mark class="mark-small mark-reversed" /><span>AMIRA membantu. Pegawai memutuskan.</span></footer>
