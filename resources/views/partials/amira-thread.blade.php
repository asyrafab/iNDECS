{{-- AMIRA conversation thread (ADR-369). Replaced as a whole by polling; every body is escaped. --}}
<div class="amira-thread" data-amira-chat-thread data-pending="{{ $chat['pending'] ? 'true' : 'false' }}" data-thread-url="{{ $chat['threadUrl'] }}">
    @foreach($chat['messages'] as $message)
        @if($message->role === 'psp')
            <p class="amira-counter-user">{!! nl2br(e($message->body)) !!}</p>
        @else
            <div class="amira-counter-message amira-reply{{ $message->fallback ? ' is-fallback' : '' }}" data-amira-reply="{{ $message->id }}">
                <span class="eyebrow">{{ $message->fallback ? 'Panduan asas' : 'Jawapan AI' }}</span>
                <p>{!! nl2br(e($message->body)) !!}</p>
                @if($message->mentions_outcome)
                    <p class="amira-reply-officer"><x-icon name="circle-alert" />Keputusan dibuat oleh pegawai MBSP, bukan AMIRA.</p>
                @endif
                @if($message->sources)
                    <p class="amira-reply-sources"><span>Rujukan:</span>
                        @foreach($message->sources as $source)
                            <a href="{{ route('portal.library.download', $source['library_document_id']) }}"><x-icon name="file-text" />{{ $source['title'] }} (versi {{ $source['version'] }})</a>
                        @endforeach
                    </p>
                @endif
                @if($message->proposal)
                    @php($prefill = \App\Services\Amira\ChatPanel::prefill($message->proposal))
                    <div class="amira-proposal">
                        <strong>Cadangan butiran projek</strong>
                        <dl>
                            @foreach(\App\Services\Amira\ChatPanel::describe($message->proposal, $chat['options']) as [$label, $value])
                                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                            @endforeach
                        </dl>
                        <p>Semak dahulu. Draf hanya berubah apabila anda menyimpannya.</p>
                        @if($chat['fillsForm'])
                            <button type="button" class="button button-primary button-small" data-amira-apply="{{ json_encode($prefill) }}">Guna butiran ini<x-icon name="arrow-right" /></button>
                        @else
                            <a class="button button-primary button-small" href="{{ route('portal.drafts.create', ['prefill' => $prefill]) }}">Guna butiran ini<x-icon name="arrow-right" /></a>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @endforeach
    @if($chat['pending'])
        <p class="amira-typing"><span aria-hidden="true"></span>AMIRA sedang menaip…</p>
    @endif
</div>
