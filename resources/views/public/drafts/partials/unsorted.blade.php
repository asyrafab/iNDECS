{{-- Files AIRS could not place with confidence, and files of items that no longer apply. Payment waits until this is empty. --}}
@if (($checklist['unsorted'] ?? []) !== [])
<div class="unsorted-tray" id="belum-dipadankan">
    <div class="form-section-heading"><div><h3>Fail belum dipadankan ({{ count($checklist['unsorted']) }})</h3>
        <p>Pilih dokumen senarai semak bagi setiap fail, atau buang fail yang tidak diperlukan.</p></div></div>
    @if (collect($checklist['unsorted'])->contains(fn ($file) => $file->ocr_unavailable))
        <p class="precheck-note">Bacaan automatik tidak tersedia untuk sebahagian fail — sila padankan fail tersebut sendiri.</p>
    @endif
    <ul class="precheck-list precheck-compact-list">
        @foreach ($checklist['unsorted'] as $file)
            @php($label = \App\Services\Precheck\DraftChecklist::label($file, []))
            <li>
                <a class="precheck-document" href="#unsorted-{{ $file->id }}" data-portal-open="unsorted-{{ $file->id }}" aria-haspopup="dialog">
                    @include('public.drafts.partials.status-icon', ['label' => $file->rejected_reason ? \App\Enums\PrecheckLabel::Unreadable : ($label === \App\Enums\PrecheckLabel::Pending ? $label : \App\Enums\PrecheckLabel::NeedsConfirmation), 'statusText' => $file->rejected_reason ? 'Fail tidak boleh digunakan' : ($label === \App\Enums\PrecheckLabel::Pending ? 'Sedang dibaca' : 'Belum dipadankan')])
                    <span>{{ $file->original_name }}</span><x-icon class="precheck-document-arrow" name="arrow-up-right" />
                </a>
                <dialog class="portal-dialog precheck-dialog" id="unsorted-{{ $file->id }}" aria-labelledby="unsorted-title-{{ $file->id }}">
                <div class="portal-dialog-head"><span class="eyebrow">Fail belum dipadankan</span><button class="icon-button" type="button" data-portal-close aria-label="Tutup butiran fail"><x-icon name="x" /></button></div>
                <div class="precheck-item-head">
                    <div><h2 id="unsorted-title-{{ $file->id }}">@if ($file->scan_pending || $file->rejected_reason){{ $file->original_name }}@else<a href="{{ route('portal.drafts.documents.download', [$draft, $file]) }}">{{ $file->original_name }}</a>@endif</h2>
                        @if ($file->rejected_reason)<p>{{ $file->rejected_reason }} Buang fail ini.</p>@elseif ($file->scan_pending)<p>Sedang diimbas untuk keselamatan.</p>@endif</div>
                    <span class="status-badge {{ $label->badgeClass() }}">{{ $label === \App\Enums\PrecheckLabel::Pending ? $label->value : 'Belum dipadankan' }}</span>
                </div>
                @if ($editable ?? true)
                <div class="precheck-actions">
                    @unless ($file->rejected_reason)
                    <form class="unsorted-assign" method="POST" action="{{ route('portal.drafts.documents.assign', [$draft, $file]) }}">
                        @csrf
                        <label><span class="sr-only">Dokumen senarai semak untuk {{ $file->original_name }}</span><select name="item_code" required>
                            <option value="">Pilih dokumen…</option>
                            @foreach (['Wajib' => true, 'Pilihan' => false] as $group => $required)
                                @php($options = array_filter($checklist['items'], fn ($item) => $item['is_required'] === $required))
                                @if ($options !== [])<optgroup label="{{ $group }}">@foreach ($options as $item)<option value="{{ $item['code'] }}">{{ $item['name'] }}</option>@endforeach</optgroup>@endif
                            @endforeach
                        </select></label>
                        <button class="button button-secondary button-small" type="submit"><x-icon name="check" />Letak</button>
                    </form>
                    @endunless
                    <form method="POST" action="{{ route('portal.drafts.documents.remove', [$draft, $file]) }}">@csrf @method('DELETE')<button class="button button-secondary button-small" type="submit"><x-icon name="x" />Buang</button></form>
                </div>
                @endif
                </dialog>
            </li>
        @endforeach
    </ul>
</div>
@endif
