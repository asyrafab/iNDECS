@extends('layouts.app')

@section('title', 'Daftar PKM')

@section('content')
    <div class="page-heading">
        <a href="{{ route('perancang.index') }}" class="back-link"><x-icon name="arrow-left" /> Kembali ke senarai</a>
        <span class="eyebrow">Pendaftaran permohonan</span>
        <h1>Daftar PKM baharu</h1>
        <p>Lengkapkan maklumat asas, pihak berkepentingan, tapak dan butiran perancangan.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-error validation-summary" role="alert">
            <x-icon name="circle-alert" />
            <div>
                <strong>Sila semak maklumat yang dimasukkan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('perancang.store') }}" class="application-form">
        @csrf

        <section class="form-card">
            <div class="section-number">01</div>
            <div class="form-section-heading">
                <div>
                    <h2>Maklumat permohonan</h2>
                    <p>Maklumat rujukan dan cadangan utama.</p>
                </div>
                <span class="required-note">* Wajib diisi</span>
            </div>
            <div class="form-grid">
                <label class="field field-span-2">
                    <span>Tajuk cadangan <b>*</b></span>
                    <input name="application_title" value="{{ old('application_title') }}" required placeholder="Contoh: Cadangan pembangunan bercampur di Lot 101">
                </label>
                <label class="field">
                    <span>Tarikh penerimaan <b>*</b></span>
                    <input type="date" name="submission_date" value="{{ old('submission_date', $today) }}" required>
                </label>
                <label class="field">
                    <span>Rujukan OSC</span>
                    <input name="external_reference" value="{{ old('external_reference') }}" placeholder="Jika berkenaan">
                </label>
                <label class="field">
                    <span>Keutamaan <b>*</b></span>
                    <select name="priority" required>
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                        <option value="high" @selected(old('priority') === 'high')>Tinggi</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>Segera</option>
                    </select>
                </label>
                <label class="field field-span-2">
                    <span>Keterangan cadangan</span>
                    <textarea name="application_description" rows="3" placeholder="Ringkasan skop pembangunan">{{ old('application_description') }}</textarea>
                </label>
            </div>
        </section>

        <section class="form-card">
            <div class="section-number">02</div>
            <div class="form-section-heading">
                <div>
                    <h2>Pemohon dan perunding</h2>
                    <p>Maklumat ini disimpan sebagai snapshot semasa penghantaran.</p>
                </div>
            </div>

            <h3 class="subsection-title">Pemohon</h3>
            <div class="form-grid">
                <label class="field">
                    <span>Jenis pemohon <b>*</b></span>
                    <select name="applicant[party_type]" required>
                        <option value="organisation" @selected(old('applicant.party_type', 'organisation') === 'organisation')>Organisasi / syarikat</option>
                        <option value="individual" @selected(old('applicant.party_type') === 'individual')>Individu</option>
                    </select>
                </label>
                <label class="field field-span-2">
                    <span>Nama pemohon <b>*</b></span>
                    <input name="applicant[name]" value="{{ old('applicant.name') }}" required>
                </label>
                <label class="field">
                    <span>No. pendaftaran / pengenalan</span>
                    <input name="applicant[registration_number]" value="{{ old('applicant.registration_number') }}">
                </label>
                <label class="field">
                    <span>Telefon</span>
                    <input name="applicant[phone]" value="{{ old('applicant.phone') }}">
                </label>
                <label class="field">
                    <span>E-mel</span>
                    <input type="email" name="applicant[email]" value="{{ old('applicant.email') }}">
                </label>
                <label class="field field-span-2">
                    <span>Alamat surat-menyurat</span>
                    <textarea name="applicant[address]" rows="2">{{ old('applicant.address') }}</textarea>
                </label>
            </div>

            <div class="form-divider"></div>
            <h3 class="subsection-title">Perunding <span>Pilihan</span></h3>
            <div class="form-grid">
                <label class="field">
                    <span>Peranan</span>
                    <select name="consultant[role]">
                        <option value="principal_submitting_person">Principal Submitting Person</option>
                        <option value="town_planner" @selected(old('consultant.role') === 'town_planner')>Perancang bandar</option>
                        <option value="architect" @selected(old('consultant.role') === 'architect')>Arkitek</option>
                        <option value="engineer" @selected(old('consultant.role') === 'engineer')>Jurutera</option>
                        <option value="consultant" @selected(old('consultant.role') === 'consultant')>Perunding lain</option>
                    </select>
                </label>
                <input type="hidden" name="consultant[party_type]" value="organisation">
                <label class="field field-span-2">
                    <span>Nama perunding / firma</span>
                    <input name="consultant[name]" value="{{ old('consultant.name') }}">
                </label>
                <label class="field">
                    <span>No. pendaftaran</span>
                    <input name="consultant[registration_number]" value="{{ old('consultant.registration_number') }}">
                </label>
                <label class="field">
                    <span>Telefon</span>
                    <input name="consultant[phone]" value="{{ old('consultant.phone') }}">
                </label>
                <label class="field">
                    <span>E-mel</span>
                    <input type="email" name="consultant[email]" value="{{ old('consultant.email') }}">
                </label>
            </div>
        </section>

        <section class="form-card">
            <div class="section-number">03</div>
            <div class="form-section-heading">
                <div>
                    <h2>Tapak dan lot kadaster</h2>
                    <p>Geometri ArcGIS akan dipautkan kepada rekod tapak dalam fasa GIS.</p>
                </div>
            </div>
            <div class="form-grid">
                <label class="field field-span-2">
                    <span>Alamat tapak <b>*</b></span>
                    <textarea name="site[development_address]" rows="2" required>{{ old('site.development_address') }}</textarea>
                </label>
                <label class="field">
                    <span>Daerah <b>*</b></span>
                    <select name="site[district]" required>
                        <option value="">Pilih daerah</option>
                        @foreach (['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan'] as $district)
                            <option value="{{ $district }}" @selected(old('site.district') === $district)>{{ $district }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Mukim <b>*</b></span>
                    <input name="site[mukim]" value="{{ old('site.mukim') }}" required>
                </label>
                <input type="hidden" name="site[state]" value="Pulau Pinang">
                <label class="field">
                    <span>Luas tapak</span>
                    <input type="number" step="0.0001" min="0" name="site[site_area]" value="{{ old('site.site_area') }}">
                </label>
                <label class="field">
                    <span>Unit keluasan</span>
                    <select name="site[site_area_unit]">
                        <option value="hectare" @selected(old('site.site_area_unit', 'hectare') === 'hectare')>Hektar</option>
                        <option value="square_metre" @selected(old('site.site_area_unit') === 'square_metre')>Meter persegi</option>
                    </select>
                </label>
            </div>

            <div class="form-divider"></div>
            <div class="repeater-heading">
                <h3 class="subsection-title">Senarai lot <b>*</b></h3>
                <button type="button" class="button button-small button-secondary" data-repeater-add="lots"><x-icon name="plus" /> Tambah lot</button>
            </div>
            <div data-repeater="lots">
                <div data-repeater-items>
                    @foreach (old('lots', [['lot_number' => '', 'lot_type' => '', 'section' => '', 'land_area' => '', 'land_area_unit' => 'hectare']]) as $index => $lot)
                        <div class="repeater-row" data-repeater-item>
                            <span class="row-number">{{ $index + 1 }}</span>
                            <label class="field">
                                <span>No. lot <b>*</b></span>
                                <input name="lots[{{ $index }}][lot_number]" value="{{ $lot['lot_number'] ?? '' }}" required>
                            </label>
                            <label class="field">
                                <span>Jenis lot</span>
                                <input name="lots[{{ $index }}][lot_type]" value="{{ $lot['lot_type'] ?? '' }}" placeholder="Lot / PT / PA">
                            </label>
                            <label class="field">
                                <span>Seksyen</span>
                                <input name="lots[{{ $index }}][section]" value="{{ $lot['section'] ?? '' }}">
                            </label>
                            <label class="field">
                                <span>Keluasan</span>
                                <input type="number" step="0.0001" min="0" name="lots[{{ $index }}][land_area]" value="{{ $lot['land_area'] ?? '' }}">
                            </label>
                            <label class="field compact-field">
                                <span>Unit</span>
                                <select name="lots[{{ $index }}][land_area_unit]">
                                    <option value="hectare" @selected(($lot['land_area_unit'] ?? 'hectare') === 'hectare')>ha</option>
                                    <option value="square_metre" @selected(($lot['land_area_unit'] ?? '') === 'square_metre')>m²</option>
                                </select>
                            </label>
                            <button type="button" class="remove-row" data-repeater-remove aria-label="Buang lot"><x-icon name="x" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div class="repeater-row" data-repeater-item>
                        <span class="row-number"></span>
                        <label class="field"><span>No. lot <b>*</b></span><input name="lots[__INDEX__][lot_number]" required></label>
                        <label class="field"><span>Jenis lot</span><input name="lots[__INDEX__][lot_type]" placeholder="Lot / PT / PA"></label>
                        <label class="field"><span>Seksyen</span><input name="lots[__INDEX__][section]"></label>
                        <label class="field"><span>Keluasan</span><input type="number" step="0.0001" min="0" name="lots[__INDEX__][land_area]"></label>
                        <label class="field compact-field"><span>Unit</span><select name="lots[__INDEX__][land_area_unit]"><option value="hectare">ha</option><option value="square_metre">m²</option></select></label>
                        <button type="button" class="remove-row" data-repeater-remove aria-label="Buang lot"><x-icon name="x" /></button>
                    </div>
                </template>
            </div>
        </section>

        <section class="form-card">
            <div class="section-number">04</div>
            <div class="form-section-heading">
                <div>
                    <h2>Butiran Kebenaran Merancang</h2>
                    <p>Parameter utama untuk penilaian perancangan.</p>
                </div>
            </div>
            <div class="form-grid">
                <label class="field">
                    <span>Kategori KM <b>*</b></span>
                    <select name="pkm[permission_category]" required>
                        <option value="">Pilih kategori</option>
                        @foreach (['Pembangunan Baharu', 'Perubahan Material Penggunaan', 'Pindaan Pelan', 'Pelanjutan Tempoh', 'Lain-lain'] as $category)
                            <option value="{{ $category }}" @selected(old('pkm.permission_category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Jenis KM <b>*</b></span>
                    <select name="pkm[permission_type]" required>
                        <option value="">Pilih jenis</option>
                        @foreach (['Kebenaran Merancang Penuh', 'Kebenaran Merancang Terhad', 'Pindaan Kebenaran Merancang', 'Lain-lain'] as $type)
                            <option value="{{ $type }}" @selected(old('pkm.permission_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">
                    <span>Guna tanah sedia ada <b>*</b></span>
                    <input name="pkm[existing_land_use]" value="{{ old('pkm.existing_land_use') }}" required>
                </label>
                <label class="field">
                    <span>Guna tanah dicadangkan <b>*</b></span>
                    <input name="pkm[proposed_land_use]" value="{{ old('pkm.proposed_land_use') }}" required>
                </label>
                <label class="field">
                    <span>Kepadatan</span>
                    <input type="number" step="0.01" min="0" name="pkm[density]" value="{{ old('pkm.density') }}" placeholder="Unit per hektar">
                </label>
                <label class="field">
                    <span>Nisbah plot</span>
                    <input type="number" step="0.001" min="0" name="pkm[plot_ratio]" value="{{ old('pkm.plot_ratio') }}">
                </label>
                <label class="field">
                    <span>Bilangan unit</span>
                    <input type="number" min="0" name="pkm[number_of_units]" value="{{ old('pkm.number_of_units') }}">
                </label>
                <label class="field">
                    <span>Bilangan tingkat</span>
                    <input type="number" min="0" name="pkm[number_of_storeys]" value="{{ old('pkm.number_of_storeys') }}">
                </label>
                <label class="field">
                    <span>Ketinggian bangunan (m)</span>
                    <input type="number" step="0.01" min="0" name="pkm[building_height]" value="{{ old('pkm.building_height') }}">
                </label>
                <label class="field field-span-2">
                    <span>Piawaian perancangan / catatan awal</span>
                    <textarea name="pkm[planning_standards]" rows="3">{{ old('pkm.planning_standards') }}</textarea>
                </label>
            </div>

            <div class="form-divider"></div>
            <div class="repeater-heading">
                <div>
                    <h3 class="subsection-title">Komponen pembangunan <span>Pilihan</span></h3>
                    <p class="helper-text">Pecahkan cadangan kepada komponen seperti kediaman, komersial atau kemudahan.</p>
                </div>
                <button type="button" class="button button-small button-secondary" data-repeater-add="components"><x-icon name="plus" /> Tambah komponen</button>
            </div>
            <div data-repeater="components">
                <div data-repeater-items>
                    @foreach (old('components', []) as $index => $component)
                        <div class="repeater-row component-row" data-repeater-item>
                            <span class="row-number">{{ $index + 1 }}</span>
                            <label class="field"><span>Nama komponen <b>*</b></span><input name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}" required></label>
                            <label class="field"><span>Kategori</span><input name="components[{{ $index }}][category]" value="{{ $component['category'] ?? '' }}"></label>
                            <label class="field"><span>Bil. unit</span><input type="number" min="0" name="components[{{ $index }}][number_of_units]" value="{{ $component['number_of_units'] ?? '' }}"></label>
                            <label class="field"><span>Luas lantai (m²)</span><input type="number" step="0.01" min="0" name="components[{{ $index }}][floor_area]" value="{{ $component['floor_area'] ?? '' }}"></label>
                            <button type="button" class="remove-row" data-repeater-remove aria-label="Buang komponen"><x-icon name="x" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div class="repeater-row component-row" data-repeater-item>
                        <span class="row-number"></span>
                        <label class="field"><span>Nama komponen <b>*</b></span><input name="components[__INDEX__][name]" required></label>
                        <label class="field"><span>Kategori</span><input name="components[__INDEX__][category]"></label>
                        <label class="field"><span>Bil. unit</span><input type="number" min="0" name="components[__INDEX__][number_of_units]"></label>
                        <label class="field"><span>Luas lantai (m²)</span><input type="number" step="0.01" min="0" name="components[__INDEX__][floor_area]"></label>
                        <button type="button" class="remove-row" data-repeater-remove aria-label="Buang komponen"><x-icon name="x" /></button>
                    </div>
                </template>
            </div>
        </section>

        <div class="form-actions">
            <a href="{{ route('perancang.index') }}" class="button button-ghost">Batal</a>
            <div>
                <small>Rujukan PKM dijana secara automatik</small>
                <button type="submit" class="button button-primary button-large">Daftar permohonan <span><x-icon name="arrow-right" /></span></button>
            </div>
        </div>
    </form>
@endsection
