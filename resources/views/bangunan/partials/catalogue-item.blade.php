{{-- One checklist rule (ADR-362). $index is a number, or "__INDEX__" in the repeater template. --}}
@php
    $conditions = $item['conditions'] ?? [];
    $isTemplate = $index === '__INDEX__';
@endphp
<div class="form-grid catalogue-item" data-repeater-item>
    <label class="field"><span>Kod dokumen *</span><input name="items[{{ $index }}][code]" value="{{ $item['code'] ?? '' }}" pattern="[a-z][a-z0-9_]{0,35}" required></label>
    <label class="field"><span>Nama dokumen *</span><input name="items[{{ $index }}][name]" value="{{ $item['name'] ?? '' }}" maxlength="255" required></label>
    <label class="field"><span>Keperluan</span><select name="items[{{ $index }}][is_required]"><option value="1" @selected($item['is_required'] ?? true)>Wajib</option><option value="0" @selected(! ($item['is_required'] ?? true))>Pilihan</option></select></label>
    <button class="button button-ghost" type="button" data-repeater-remove>Buang dokumen</button>
    <label class="field field-span-2"><span>Keperluan dokumen</span><textarea name="items[{{ $index }}][guidance]" rows="2" maxlength="2000" placeholder="Apa yang mesti ada dalam dokumen ini, contohnya: dimeterai oleh jurutera profesional, skala 1:100.">{{ $item['guidance'] ?? '' }}</textarea></label>
    <label class="field field-span-2"><span>Kata kunci semakan (pisahkan dengan koma)</span><input name="items[{{ $index }}][precheck_keywords_text]" value="{{ $item['precheck_keywords_text'] ?? '' }}" maxlength="700" placeholder="Contoh: HAKMILIK, NO. LOT"><small>Semakan awal percuma mencari mana-mana perkataan ini dalam dokumen. Kosongkan jika tidak perlu.</small></label>
    <details class="field-span-2 catalogue-conditions" @if (! $isTemplate && ! empty($conditions)) open @endif>
        <summary><span>Syarat (pilihan)</span><small>{{ $isTemplate ? 'Sentiasa diperlukan' : \App\Services\Checklist\ChecklistRuleEvaluator::describe($conditions ?: null) }}</small></summary>
        <p class="helper-text">Biarkan kosong jika dokumen sentiasa diperlukan. Jika beberapa syarat dipilih, semuanya mesti dipenuhi.</p>
        <div class="catalogue-condition-groups">
            @foreach (['work_types' => 'Jenis kerja', 'building_categories' => 'Kategori bangunan', 'site_conditions' => 'Keadaan tapak (mana-mana)'] as $key => $legend)
                <fieldset><legend>{{ $legend }}</legend>
                    @foreach (config("kaunter.{$key}") as $value => $label)
                        <label class="checkbox-field"><input type="checkbox" name="items[{{ $index }}][conditions][{{ $key }}][]" value="{{ $value }}" @checked(in_array($value, (array) ($conditions[$key] ?? []), true))><span>{{ $label }}</span></label>
                    @endforeach
                </fieldset>
            @endforeach
            <fieldset><legend>Saiz</legend>
                <label class="field"><span>Bilangan tingkat minimum</span><input type="number" min="1" max="100" step="1" name="items[{{ $index }}][conditions][storeys_min]" value="{{ $conditions['storeys_min'] ?? '' }}"></label>
                <label class="field"><span>Keluasan lantai minimum (m²)</span><input type="number" min="0.01" max="1000000" step="0.01" name="items[{{ $index }}][conditions][floor_area_min]" value="{{ $conditions['floor_area_min'] ?? '' }}"></label>
            </fieldset>
        </div>
    </details>
</div>
