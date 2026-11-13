<section class="landing-introduction landing-container" aria-labelledby="mark-story-title">
    <h2 id="mark-story-title" class="sr-only">Selamat datang ke iNDECS</h2>
    <svg class="building-mark" viewBox="-28 -8 160 176" role="img" aria-labelledby="building-mark-title">
        <title id="building-mark-title">Logo iNDECS: tiga segi empat mewakili Perancang, Bangunan dan Kejuruteraan; kanopi bulat mewakili Landskap</title>
        <defs>
            <image id="building-roof-photo" href="{{ asset('img/brand/roof.webp') }}" width="64" height="64" preserveAspectRatio="none" />
            <image id="building-wall-photo" href="{{ asset('img/brand/walls.webp') }}" x="28" y="28" width="64" height="64" preserveAspectRatio="none" />
            <image id="building-plant-photo" href="{{ asset('img/brand/landscape.webp') }}" width="20" height="20" preserveAspectRatio="none" />
            <pattern id="building-edging-stone" patternUnits="userSpaceOnUse" width="12" height="12">
                <image href="{{ asset('img/brand/stone-path.png') }}" width="36" height="36" preserveAspectRatio="xMidYMid slice" />
            </pattern>
            <clipPath id="building-garden-clip">
                <path d="M196.6 196.5 V137.8 A58.7 58.7 0 1 1 137.9 196.5 Z" />
            </clipPath>
            <mask id="building-garden-path-mask" maskUnits="userSpaceOnUse" x="137.9" y="137.8" width="117.4" height="117.4">
                <circle class="building-mask-keep" cx="196.6" cy="196.5" r="58.7" />
                <circle class="building-mask-cut" cx="196.6" cy="196.5" r="49.7" />
            </mask>
            <mask id="building-doorway-mask" maskUnits="userSpaceOnUse" x="28" y="28" width="64" height="64">
                <rect class="building-mask-keep" x="28" y="28" width="64" height="64" />
                <rect class="building-mask-cut" x="54.5" y="85" width="11" height="6.5" />
            </mask>
        </defs>
        <g class="building-landscape" data-building-landscape aria-hidden="true"></g>
        <svg class="building-garden" x="0" y="0" width="120" height="120" viewBox="25.6 25.6 229.6 229.6" aria-hidden="true">
            <g clip-path="url(#building-garden-clip)">
                <image href="{{ asset('img/brand/lawn.webp') }}" x="137.9" y="137.8" width="117.4" height="117.4" preserveAspectRatio="xMidYMid slice" />
                <image href="{{ asset('img/brand/stone-path.png') }}" x="137.9" y="137.8" width="117.4" height="117.4" preserveAspectRatio="xMidYMid slice" mask="url(#building-garden-path-mask)" />
                <circle class="building-garden-contact" cx="196.6" cy="196.5" r="49.4" />
                <g class="building-garden-edging">
                    <circle cx="196.6" cy="196.5" r="50.4" />
                    <circle cx="196.6" cy="196.5" r="57.8" />
                </g>
                <g class="building-garden-joints">
                    <circle cx="196.6" cy="196.5" r="50.4" />
                    <circle cx="196.6" cy="196.5" r="57.8" />
                </g>
            </g>
        </svg>
        <g class="building-layer building-footprint">
            <g class="building-underlay">
                <image class="building-texture" href="{{ asset('img/brand/footprint.webp') }}" x="0" y="56" width="64" height="64" preserveAspectRatio="none" />
            </g>
            <g class="building-dust" data-building-dust="footprint" aria-hidden="true"></g>
        </g>
        <g class="building-layer building-walls">
            <g class="building-underlay">
              <g class="building-texture">
                <use href="#building-wall-photo" mask="url(#building-doorway-mask)" />
                <svg x="54.5" y="85" width="11" height="6.5" viewBox="43 70 11 6.5" overflow="hidden" aria-hidden="true">
                    <use href="#building-wall-photo" />
                </svg>
                <path class="building-door-sweep" d="M64.8 90.2 A9.5 9.5 0 0 1 57.3 99.5" />
                <g transform="translate(55.3 90.2)">
                    <g class="building-door" aria-hidden="true">
                        <svg x="0" y="-0.7" width="9.5" height="1.4" viewBox="55 86 9.5 1.4" overflow="hidden">
                            <use href="#building-wall-photo" />
                        </svg>
                        <circle class="building-door-handle" cx="8.5" cy="0" r="0.2" />
                    </g>
                </g>
              </g>
            </g>
            <g class="building-dust" data-building-dust="walls" aria-hidden="true"></g>
        </g>
        <g class="building-layer building-roof">
            <g class="building-texture" transform="translate(56 0)">
                <g data-roof-tiles aria-hidden="true"></g>
                <g class="building-dust" data-tile-dust aria-hidden="true"></g>
            </g>
            <g class="building-dust" data-building-dust="roof" aria-hidden="true"></g>
        </g>
        <svg class="building-symbol building-brand" x="0" y="0" width="120" height="120" viewBox="25.6 25.6 229.6 229.6" aria-hidden="true">
            @include('partials.indecs-mark-shapes')
        </svg>
        <text class="building-wordmark" x="60" y="148" text-anchor="middle" aria-hidden="true">iNDECS</text>
    </svg>
</section>
