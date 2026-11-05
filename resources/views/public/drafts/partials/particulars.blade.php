{{-- Project particulars (ADR-363). They decide which documents the checklist rules require. --}}
<section class="form-card" id="butiran-projek">
    <div class="form-section-heading"><div><h2>Butiran projek</h2><p>Jawapan ini menentukan dokumen yang diperlukan. Kosongkan jika belum pasti.</p></div></div>
    <div class="form-grid">
        <label class="field"><span>Jenis kerja</span><select name="work_type"><option value="">Belum dipilih</option>@foreach (config('kaunter.work_types') as $value => $label)<option value="{{ $value }}" @selected(old('work_type', $draft->work_type) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Kategori bangunan</span><select name="building_category"><option value="">Belum dipilih</option>@foreach (config('kaunter.building_categories') as $value => $label)<option value="{{ $value }}" @selected(old('building_category', $draft->building_category) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Bilangan tingkat</span><input type="number" name="storeys" min="1" max="100" step="1" value="{{ old('storeys', $draft->storeys) }}"></label>
        <label class="field"><span>Keluasan lantai (m²)</span><input type="number" name="floor_area" min="1" max="1000000" step="0.01" value="{{ old('floor_area', $draft->floor_area) }}"></label>
        <fieldset class="field field-span-2 precheck-sites"><legend>Keadaan tapak</legend>
            @foreach (config('kaunter.site_conditions') as $value => $label)
                <label class="checkbox-field"><input type="checkbox" name="site_conditions[]" value="{{ $value }}" @checked(in_array($value, old('site_conditions', $draft->site_conditions ?? []), true))><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
    </div>
</section>
