{{-- Upload-first drafts: drop files, a folder or one ZIP. Without JavaScript the form posts directly (up to 10 files). --}}
@php($creating = ! $draft->exists)
<form class="form-card drop-zone" method="POST" enctype="multipart/form-data"
      action="{{ $creating ? route('portal.drafts.drop') : route('portal.drafts.documents.add', $draft) }}"
      data-drop-zone @if ($creating) data-drop-url="{{ route('portal.drafts.drop') }}" @else data-add-url="{{ route('portal.drafts.documents.add', $draft) }}" @endif>
    @csrf
    <div class="form-section-heading"><div>
        <h2>{{ $creating ? 'Mulakan dengan dokumen anda' : 'Tambah dokumen' }}</h2>
        <p>Seret fail, satu folder atau satu fail ZIP. AIRS menyusun setiap fail ke dalam senarai semak secara automatik; fail yang tidak dapat dipadankan diletakkan dalam "Fail belum dipadankan" untuk anda pilih.</p>
    </div></div>
    @if ($creating)
        <label class="field"><span>Jenis permohonan <b>*</b></span><select name="application_type_id" required>
            <option value="">Pilih jenis permohonan</option>
            @foreach($types->groupBy('primaryDepartment.name') as $department => $departmentTypes)
                <optgroup label="{{ $department }}">@foreach($departmentTypes as $type)<option value="{{ $type->id }}" @selected(old('application_type_id', request('type')) === $type->id)>{{ $type->id }} · {{ $type->name }}</option>@endforeach</optgroup>
            @endforeach
        </select></label>
    @endif
    <div class="drop-target" data-drop-target>
        <x-icon name="file-plus-2" />
        <strong>Seret fail atau folder ke sini</strong>
        <small>PDF, JPG, PNG atau ZIP. Fail besar dimuat naik satu demi satu.</small>
        <div class="drop-buttons">
            <label class="button button-secondary button-small"><input class="sr-only" type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.zip" data-drop-files>Pilih fail</label>
            <label class="button button-secondary button-small"><input class="sr-only" type="file" name="files[]" multiple webkitdirectory data-drop-folder>Pilih folder</label>
        </div>
    </div>
    <ul class="drop-progress" data-drop-progress aria-live="polite"></ul>
    @error('files')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @error('application_type_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    @if (session('drop_rejected'))
        <div class="alert alert-error" role="alert"><x-icon name="circle-alert" /><div><strong>Fail tidak diterima</strong>
            <ul>@foreach (session('drop_rejected') as $rejected)<li>{{ $rejected['name'] }} — {{ $rejected['reason'] }}</li>@endforeach</ul></div></div>
    @endif
    <noscript><button class="button button-primary button-small" type="submit">Muat naik</button></noscript>
</form>
