{{-- Parked interactive Living Garden Ring Circle concept; remove `hidden` on .welcome-garden-circle to re-enable. --}}
<div class="welcome-garden-circle" data-welcome-garden aria-label="Taman interaktif Landskap" hidden>
    <svg class="welcome-garden-ring" viewBox="0 0 120 120" aria-hidden="true">
        <defs>
            <image id="welcome-plant-photo" href="{{ asset('img/brand/landscape.webp') }}" width="20" height="20" preserveAspectRatio="none" />
            <image id="welcome-palm-photo" href="{{ asset('img/brand/palm.webp') }}" width="26" height="26" preserveAspectRatio="none" />
            <pattern id="welcome-edging-stone" patternUnits="userSpaceOnUse" width="12" height="12">
                <image href="{{ asset('img/brand/stone-path.png') }}" width="36" height="36" preserveAspectRatio="xMidYMid slice" />
            </pattern>
            <clipPath id="welcome-garden-outer-clip">
                <circle cx="60" cy="60" r="60" />
            </clipPath>
            <clipPath id="welcome-garden-lawn-clip">
                <circle cx="60" cy="60" r="50.8" />
            </clipPath>
            <mask id="welcome-garden-ring-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="120" height="120">
                <circle class="building-mask-keep" cx="60" cy="60" r="60" />
                <circle class="building-mask-cut" cx="60" cy="60" r="50.8" />
            </mask>
        </defs>
        <g clip-path="url(#welcome-garden-lawn-clip)">
            <image class="welcome-garden-lawn" href="{{ asset('img/brand/lawn.webp') }}" x="-4" y="-4" width="128" height="128" preserveAspectRatio="xMidYMid slice" data-garden-lawn />
            <g class="welcome-garden-grass" data-garden-grass></g>
        </g>
        <image href="{{ asset('img/brand/stone-path.png') }}" x="0" y="0" width="120" height="120" preserveAspectRatio="xMidYMid slice" mask="url(#welcome-garden-ring-mask)" />
        <circle class="building-garden-contact" cx="60" cy="60" r="50.5" />
        <g class="building-garden-edging">
            <circle cx="60" cy="60" r="51.5" />
            <circle cx="60" cy="60" r="59" />
        </g>
        <g class="building-garden-joints">
            <circle cx="60" cy="60" r="51.5" />
            <circle cx="60" cy="60" r="59" />
        </g>
        <g class="welcome-garden-plants" clip-path="url(#welcome-garden-outer-clip)" data-garden-plants></g>
    </svg>
</div>
