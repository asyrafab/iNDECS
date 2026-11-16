@if($draft->removable())
    <form class="inline-form" method="POST" action="{{ route('portal.drafts.destroy', $draft) }}" data-confirm="Buang draf {{ $draft->title }} daripada Fail saya? Anda tidak boleh menyambung draf ini selepas dibuang.">
        @csrf
        @method('DELETE')
        <input type="hidden" name="version" value="{{ $draft->version }}">
        <button class="button button-secondary button-small" type="submit" aria-label="Buang draf {{ $draft->title }}"><x-icon name="trash-2" />Buang draf</button>
    </form>
@endif
