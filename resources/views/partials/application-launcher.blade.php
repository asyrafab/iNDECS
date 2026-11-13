<dialog class="application-dialog" id="application-launcher" aria-labelledby="application-launcher-title" aria-describedby="application-launcher-description" data-application-launcher>
    <div class="application-dialog-head">
        <x-mark class="mark-small" />
        <button class="icon-button" type="button" aria-label="Tutup pilihan aplikasi" data-application-launcher-close><x-icon name="x" /></button>
    </div>
    <h2 id="application-launcher-title">Pilih aplikasi</h2>
    <p id="application-launcher-description">Selamat kembali, {{ auth()->user()->name }}. Pilih aplikasi untuk meneruskan.</p>
    @if (auth()->user()->role === 'system_admin')<span class="status-badge status-info">Superadmin</span>@endif
    <div class="application-grid">
        @forelse ($launcherApplications as $application)
            @php($brandLogo = match ($application['brand'] ?? null) { 'airs' => 'airs-icon.svg', 'esri' => 'esri-logo.svg', 'eosc' => 'eosc-temporary.png', default => null })
            @if ($application['url'])
                <a class="application-tile" href="{{ $application['url'] }}">
                    <span @class(['application-icon', 'application-icon-department' => isset($application['department']), 'application-icon-brand' => $brandLogo !== null, 'application-icon-esri' => ($application['brand'] ?? null) === 'esri'])>
                        @if (isset($application['department']))<x-department-icon :department="$application['department']" />@elseif ($brandLogo)<img class="application-brand-logo" src="{{ asset('img/'.$brandLogo) }}" alt="" aria-hidden="true" width="64" height="64">@else<x-icon :name="$application['icon']" />@endif
                    </span>
                    <strong>{{ $application['name'] }}</strong>
                    <span>{{ $application['description'] }}</span>
                </a>
            @else
                <div class="application-tile application-tile-unavailable">
                    <span @class(['application-icon', 'application-icon-department' => isset($application['department']), 'application-icon-brand' => $brandLogo !== null, 'application-icon-esri' => ($application['brand'] ?? null) === 'esri'])>
                        @if (isset($application['department']))<x-department-icon :department="$application['department']" />@elseif ($brandLogo)<img class="application-brand-logo" src="{{ asset('img/'.$brandLogo) }}" alt="" aria-hidden="true" width="64" height="64">@else<x-icon :name="$application['icon']" />@endif
                    </span>
                    <strong>{{ $application['name'] }}</strong>
                    <span>{{ $application['description'] }}</span>
                </div>
            @endif
        @empty
            <p>Tiada aplikasi tersedia. Sila hubungi pentadbir iNDECS.</p>
        @endforelse
    </div>
</dialog>
<noscript><style>.application-dialog { display: block; position: fixed; inset: var(--space-4); z-index: 10; } [data-application-launcher-close], [data-application-launcher-open] { display: none; }</style></noscript>
