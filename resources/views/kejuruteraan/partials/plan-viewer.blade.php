<aside id="technical-plan-{{ $check['number'] }}" @class(['technical-plan', 'technical-plan-empty' => $check['documents'] === []]) data-review-viewer aria-label="Pelan dan dokumen semakan">
    <div class="technical-plan-head"><h4><x-icon name="file-text" />Pelan & dokumen</h4><span class="muted">Semakan {{ $check['number'] }}</span><button type="button" class="button button-ghost button-small" data-review-split-reset hidden>Tetapan lebar asal</button></div>
    @if ($check['documents'] !== [])
        <label class="field"><span>Dokumen bagi semakan ini</span><select data-review-document aria-controls="technical-document-{{ $check['number'] }}">
            @foreach ($check['documents'] as $document)<option value="{{ $document['id'] }}" data-document-url="{{ $document['url'] }}">{{ $document['name'] }} · versi {{ $document['revision'] }}</option>@endforeach
        </select></label>
        <div class="technical-plan-page" data-review-page-controls hidden><label class="field"><span>Halaman (PDF)</span><input type="number" min="1" max="99999" value="1" data-review-document-page></label><button type="button" class="button button-secondary button-small" data-review-go-page>Papar halaman</button></div>
        <p class="muted technical-plan-caption" data-review-document-caption role="status" aria-live="polite">Pilih pautan bukti untuk melihat halaman yang dirujuk.</p>
        <iframe id="technical-document-{{ $check['number'] }}" name="technical-document-{{ $check['number'] }}" title="Pratonton dokumen bagi semakan {{ $check['number'] }}" @if (!$collapsed) src="{{ $check['documents'][0]['url'] }}#page=1" @endif data-review-document-frame loading="lazy" referrerpolicy="same-origin"></iframe>
        <a class="technical-plan-fallback" href="{{ $check['documents'][0]['url'] }}" data-review-document-fallback>Buka dokumen di halaman ini jika pratonton tidak tersedia<x-icon name="arrow-right" /></a>
        <noscript><p>Pilih pautan bukti untuk memaparkan dokumen di panel ini. Untuk dokumen lain, gunakan pautan dokumen.</p><ul>@foreach ($check['documents'] as $document)<li><a href="{{ $document['url'] }}" target="technical-document-{{ $check['number'] }}">{{ $document['name'] }}</a></li>@endforeach</ul></noscript>
    @else
        <p class="muted">Tiada PDF atau imej yang boleh dipratonton dalam set dokumen semakan ini.</p>
        <p class="muted">Pautan bukti kekal tersedia pada dapatan berkaitan.</p>
    @endif
</aside>
