<nav class="panel-navbar" aria-label="Top navigation">
    <div class="panel-navbar__lead">
        <button class="panel-menu-toggle" type="button" data-panel-open aria-label="Open sidebar">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div>
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

    @if (!empty($topActions))
        <div class="panel-navbar__actions">
            @foreach ($topActions as $action)
                <a
                    href="{{ $action['url'] }}"
                    class="button {{ ($action['variant'] ?? 'primary') === 'secondary' ? 'secondary' : '' }}"
                >
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</nav>
