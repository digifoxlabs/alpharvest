<header class="panel-header">
    <div>
        <h1>{{ $heading }}</h1>
        <p>{{ $subheading }}</p>
    </div>

    @if (!empty($headerBadges))
        <div class="panel-meta-row">
            @foreach ($headerBadges as $badge)
                <span class="badge subtle">{{ $badge }}</span>
            @endforeach
        </div>
    @endif
</header>
