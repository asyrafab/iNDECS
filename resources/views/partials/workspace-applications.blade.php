@foreach (app(\App\Services\ApplicationLauncher::class)->forUser($staff) as $application)
    @continue(isset($application['module']))
    @php
        $available = filled($application['url']);
        $current = isset($application['department']) && $application['department'] === $navigationDepartment;
        $reason = $application['unavailable'] ?? 'Tiada kebenaran akses';
    @endphp
    @if ($available)
        <a class="workspace-option" href="{{ $application['url'] }}" @if ($current) aria-current="location" @endif>
    @else
        <span class="workspace-option" role="link" aria-disabled="true" aria-label="{{ $application['name'] }} — {{ $reason }}" title="{{ $reason }}">
    @endif
        <span class="workspace-option-icon">
            @if (isset($application['department']))
                <x-department-icon :department="$application['department']" compact />
            @elseif (($application['brand'] ?? null) === 'eosc')
                <x-eosc-icon compact />
            @elseif (($application['brand'] ?? null) === 'esri')
                <img class="workspace-app-logo workspace-app-esri" src="{{ asset('img/esri-logo.svg') }}" alt="" aria-hidden="true">
            @else
                <x-icon :name="$application['icon']" />
            @endif
        </span>
        <span>{{ $application['name'] }}</span>
    @if ($available)</a>@else</span>@endif
@endforeach
