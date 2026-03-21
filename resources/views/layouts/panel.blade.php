<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Control Panel' }}</title>
    @unless (app()->runningUnitTests())
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endunless
</head>
<body class="{{ $bodyClass ?? '' }}">
    <div class="panel-shell" data-panel-shell>
        <div class="panel-overlay" data-panel-close></div>

        @include('layouts.panel.sidebar', [
            'brandName' => $brandName ?? 'AlphaHarvest',
            'brandLabel' => $brandLabel ?? 'Control Panel',
            'brandCaption' => $brandCaption ?? 'Unified workspace',
            'navigation' => $navigation ?? [],
            'sidebarFooter' => $sidebarFooter ?? null,
        ])

        <div class="panel-main">
            @include('layouts.panel.navbar', [
                'brandLabel' => $brandLabel ?? 'Control Panel',
                'topActions' => $topActions ?? [],
                'navbarMeta' => $navbarMeta ?? [],
            ])

            @include('layouts.panel.header', [
                'heading' => $heading ?? 'Workspace',
                'subheading' => $subheading ?? 'Operate your backend from one place.',
                'headerBadges' => $headerBadges ?? [],
            ])

            @if (session('status'))
                <div class="flash success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="flash error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="panel-content">
                @yield('content')
            </div>

            @include('layouts.panel.footer', [
                'footerText' => $footerText ?? 'Built for everyday operations.',
            ])
        </div>
    </div>
</body>
</html>
