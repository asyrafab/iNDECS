{{-- LJUP: the proposed lighting types decide which AIRS technical rules apply. Rendered for every draft
     and revealed as soon as LJUP is chosen, so the applicant sees the requirement before saving. --}}
<section class="form-card" id="jenis-lampu" {{ $showLighting ? '' : 'hidden' }} data-show-for-type="LJUP">
    <div class="form-section-heading"><div><h2>Jenis lampu dicadangkan</h2><p>Jenis lampu menentukan item semakan teknikal yang berkaitan. Pilih sekurang-kurangnya satu.</p></div></div>
    <div class="form-grid">
        <fieldset class="field field-span-2 precheck-sites"><legend>Jenis lampu</legend>
            @foreach (config('airs.lighting_types') as $value => $type)
                <label class="checkbox-field"><input type="checkbox" name="lighting_types[]" value="{{ $value }}" @checked(in_array($value, old('lighting_types', $draft->lighting_types ?? []), true))><span>{{ $type['label'] }}</span></label>
            @endforeach
        </fieldset>
        @include('public.drafts.partials.prefill-note', ['field' => 'lighting_types', 'prefill' => $draft->prefill_sources ?? []])
        @error('lighting_types')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    </div>
</section>
<script>(() => {
    const section = document.getElementById('jenis-lampu');
    const type = section?.closest('form')?.querySelector('select[name="application_type_id"]');
    if (!section || !type) return;
    const sync = () => { section.hidden = type.value !== section.dataset.showForType; };
    type.addEventListener('change', sync);
})();</script>
