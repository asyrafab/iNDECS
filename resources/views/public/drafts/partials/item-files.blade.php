{{-- An item window's files: one for a single-file item, up to max_files for a multi-file item (each with its own actions). --}}
@php($multi = $item['max_files'] > 1)
@forelse ($item['documents'] as $file)
    @php($fileLabel = \App\Services\Precheck\DraftChecklist::label($file, $item['keywords']))
    <div class="precheck-file-entry">
        <p class="precheck-file"><x-icon name="file-text" />
            @if ($file->scan_pending || $file->rejected_reason){{ $file->original_name }}
            @else<a href="{{ route('portal.drafts.documents.download', [$draft, $file]) }}">{{ $file->original_name }}</a>@endif
            @if ($multi)<span class="status-badge {{ $fileLabel->badgeClass() }}">{{ $fileLabel->value }}</span>@endif
        </p>
        @if ($fileLabel === \App\Enums\PrecheckLabel::Unreadable)<p class="precheck-guidance">Fail tidak dapat dibaca. Sila muat naik semula.</p>
        @elseif ($fileLabel === \App\Enums\PrecheckLabel::NeedsConfirmation)<p class="precheck-guidance">{{ $file->ocr_unavailable ? 'Kandungan tidak dapat disemak secara automatik.' : 'Perkataan yang dijangka tidak ditemui.' }} Pastikan ini dokumen yang betul.</p>
        @endif
        @if (($editable ?? true) && ($multi || $fileLabel === \App\Enums\PrecheckLabel::NeedsConfirmation))
            <div class="precheck-actions">
                @if ($fileLabel === \App\Enums\PrecheckLabel::NeedsConfirmation)
                    <form method="POST" action="{{ route('portal.drafts.documents.confirm', [$draft, $file]) }}">@csrf<button class="button button-secondary button-small" type="submit"><x-icon name="check" />Saya sahkan dokumen ini betul</button></form>
                @endif
                @if ($multi)
                    <form method="POST" action="{{ route('portal.drafts.documents.remove', [$draft, $file]) }}">@csrf @method('DELETE')<button class="button button-secondary button-small" type="submit"><x-icon name="x" />Buang</button></form>
                @endif
            </div>
        @endif
    </div>
@empty
    <p class="precheck-guidance">Dokumen ini belum dikemukakan. Pilih fail untuk melampirkannya.</p>
@endforelse
@if ($editable ?? true)
    @if ($multi && count($item['documents']) >= $item['max_files'])
        <p class="precheck-guidance">Buang satu fail untuk menambah fail lain.</p>
    @else
        <div class="precheck-actions">
            <form class="precheck-upload" method="POST" action="{{ route('portal.drafts.documents.store', [$draft, $item['code']]) }}" enctype="multipart/form-data">
                @csrf
                <label class="field"><span>Fail untuk {{ $item['name'] }}</span><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png"></label>
                <button class="button button-primary button-small" type="submit"><x-icon name="file-plus-2" />{{ $multi ? ($item['documents'] === [] ? 'Muat naik' : 'Tambah fail') : ($item['document'] ? 'Muat naik semula' : 'Muat naik') }}</button>
            </form>
        </div>
    @endif
@endif
