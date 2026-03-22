<nav class="panel-navbar" aria-label="Top navigation">
    <div class="panel-navbar__lead">

        <!-- Always visible -->
        {{-- <button class="panel-menu-toggle" type="button" data-panel-open aria-label="Open sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button> --}}


        <button type="button" data-panel-open aria-label="Open sidebar"
            class="flex flex lg:hidden flex-col justify-center items-center w-10 h-10 gap-1.5">
            <span class="block w-6 h-0.5 bg-gray-800 rounded"></span>
            <span class="block w-6 h-0.5 bg-gray-800 rounded"></span>
            <span class="block w-6 h-0.5 bg-gray-800 rounded"></span>
        </button>

        <!-- Hidden on mobile -->
        <div class="panel-navbar__desktop">
            <p class="panel-navbar__label">{{ $brandLabel }}</p>

            @if (!empty($navbarMeta))
            <div class="panel-meta-row">
                @foreach ($navbarMeta as $meta)
                <span class="badge subtle">{{ $meta }}</span>
                @endforeach
            </div>
            @endif
        </div>

    </div>

    <!-- Hidden on mobile -->
    @if (!empty($topActions))
    <div class="panel-navbar__desktop">
        @foreach ($topActions as $action)
        <a href="{{ $action['url'] }}"
            class="button {{ ($action['variant'] ?? 'primary') === 'secondary' ? 'secondary' : '' }}">
            {{ $action['label'] }}
        </a>
        @endforeach
    </div>
    @endif
</nav>