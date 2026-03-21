<aside class="panel-sidebar" data-panel-sidebar>
    <div class="panel-sidebar__inner">
        <div class="panel-brand">
            <span class="panel-brand__eyebrow">{{ $brandName }}</span>
            <strong class="panel-brand__title">{{ $brandLabel }}</strong>
            <p class="panel-brand__caption">{{ $brandCaption }}</p>
        </div>

        <button class="panel-sidebar__close" type="button" data-panel-close aria-label="Close sidebar">
            Close
        </button>

        <nav class="panel-nav" aria-label="Sidebar navigation">
            @foreach ($navigation as $item)
                <a
                    href="{{ $item['url'] }}"
                    class="panel-nav__link {{ !empty($item['active']) ? 'is-active' : '' }}"
                >
                    <span class="panel-nav__marker"></span>
                    <span>
                        <strong>{{ $item['label'] }}</strong>
                        @if (!empty($item['description']))
                            <small>{{ $item['description'] }}</small>
                        @endif
                    </span>
                </a>
            @endforeach
        </nav>

        @if (!empty($sidebarFooter))
            <div class="panel-sidebar__footer">
                {{ $sidebarFooter }}
            </div>
        @endif
    </div>
</aside>
