@if($draft->removable())
    <form class="inline-form" method="POST" action="{{ route('portal.drafts.destroy', $draft) }}" data-draft-remove data-draft-title="{{ $draft->title }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="version" value="{{ $draft->version }}">
        <button class="{{ ($iconOnly ?? false) ? 'icon-button file-row-trash' : 'button button-secondary button-small' }}" type="submit" aria-label="Buang draf {{ $draft->title }}" title="Buang draf" data-draft-remove-trigger disabled>
            <x-icon name="trash-2" />
            @unless($iconOnly ?? false)
                Buang draf
            @endunless
        </button>
        <noscript><small>Aktifkan JavaScript untuk membuang draf.</small></noscript>
    </form>
@endif
