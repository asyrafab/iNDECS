@if($drafts->isEmpty())
    <div class="empty-state compact"><x-icon name="files" /><h3>Belum ada draf</h3><p>Mulakan dengan maklumat asas cadangan anda. Anda boleh menyimpan dan menyambungnya kemudian.</p>@if(auth()->user()->email_verified_at)<a class="button button-primary" href="{{ route('portal.drafts.create') }}"><x-icon name="file-plus-2" />Mulakan draf pertama</a>@else<a href="{{ route('portal.account') }}">Sahkan emel di Akaun saya</a>@endif</div>
@else
    <ul class="portal-draft-list">
        @foreach($drafts as $draft)
            <li><div><span class="status-badge status-pending">Draf · {{ $draft->application_type_id }}</span><h3><a href="{{ route('portal.drafts.edit', $draft) }}">{{ $draft->title }}</a></h3><p>{{ $draft->applicationType->name }}</p><small>Dikemas kini <time datetime="{{ $draft->updated_at->toIso8601String() }}">{{ $draft->updated_at->timezone('Asia/Kuala_Lumpur')->format('d/m/Y H:i') }}</time> · Versi {{ $draft->version }}</small></div><a class="button button-secondary button-small" href="{{ route('portal.drafts.edit', $draft) }}" aria-label="Sambung draf {{ $draft->title }}">Sambung<x-icon name="arrow-right" /></a>@include('public.drafts.partials.remove')</li>
        @endforeach
    </ul>
@endif
