{{-- Files AIRS could not place with confidence, and files of items that no longer apply. Payment waits until this is empty. --}}
@if (($checklist['unsorted'] ?? []) !== [])
<div class="unsorted-tray" id="belum-diisih">
    <div class="form-section-heading"><div><h3>Belum diisih ({{ count($checklist['unsorted']) }})</h3>
        <p>Pilih dokumen senarai semak bagi setiap fail, atau buang fail yang tidak diperlukan.</p></div></div>
    @if (collect($checklist['unsorted'])->contains(fn ($file) => $file->ocr_unavailable))
        <p class="precheck-note">Bacaan automatik tidak tersedia untuk sebahagian fail — sila isih fail tersebut sendiri.</p>
    @endif
    <ul class="precheck-list">
        @foreach ($checklist['unsorted'] as $file)
            @php($label = \App\Services\Precheck\DraftChecklist::label($file, []))
            <li class="precheck-item">
                <div class="precheck-item-head">
                    <div><h3>@if ($file->scan_pending || $file->rejected_reason){{ $file->original_name }}@else<a href="{{ route('portal.drafts.documents.download', [$draft, $file]) }}">{{ $file->original_name }}</a>@endif</h3>
                        @if ($file->rejected_reason)<p>{{ $file->rejected_reason }} Buang fail ini.</p>@elseif ($file->scan_pending)<p>Sedang diimbas untuk keselamatan.</p>@endif</div>
                    <span class="status-badge {{ $label->badgeClass() }}">{{ $label === \App\Enums\PrecheckLabel::Pending ? $label->value : 'Belum diisih' }}</span>
                </div>
                @if ($editable ?? true)
                <div class="precheck-actions">
                    @unless ($file->rejected_reason)
                    <form class="unsorted-assign" method="POST" action="{{ route('portal.drafts.documents.assign', [$draft, $file]) }}">
                        @csrf
                        <label><span class="sr-only">Dokumen senarai semak untuk {{ $file->original_name }}</span><select name="item_code" required>
                            <option value="">Pilih dokumen…</option>
                            @foreach ($checklist['items'] as $item)<option value="{{ $item['code'] }}">{{ $item['name'] }}</option>@endforeach
                        </select></label>
                        <button class="button button-secondary button-small" type="submit"><x-icon name="check" />Letak</button>
                    </form>
                    @endunless
                    <form method="POST" action="{{ route('portal.drafts.documents.remove', [$draft, $file]) }}">@csrf @method('DELETE')<button class="button button-secondary button-small" type="submit"><x-icon name="x" />Buang</button></form>
                </div>
                @endif
            </li>
        @endforeach
    </ul>
</div>
@endif
