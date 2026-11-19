@foreach (['work' => 'Ruang kerja', 'help' => 'Bantuan'] as $section => $heading)
    <span @class(['nav-label', 'nav-label-spaced' => $section === 'help'])>{{ $heading }}</span>
    @foreach ($workspaceMenu[$section] as $item)
        @if ($item['url'])
            <a href="{{ $item['url'] }}" @class(['nav-link', 'active' => $item['active']]) @if ($item['active']) aria-current="page" @endif><x-icon :name="$item['icon']" />{{ $item['label'] }}</a>
        @else
            <span class="nav-link" role="link" aria-disabled="true" aria-label="{{ $item['label'] }} — {{ $item['reason'] }}" title="{{ $item['reason'] }}"><x-icon :name="$item['icon']" />{{ $item['label'] }}</span>
        @endif
    @endforeach
    @if ($section === 'help')
        <button class="nav-link" type="button" data-amira-open aria-controls="amira-panel" aria-expanded="false"><x-icon name="messages-square" />Panduan AMIRA</button>
    @endif
@endforeach
