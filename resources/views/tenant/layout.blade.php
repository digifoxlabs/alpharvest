@php($primaryStore = $overview['stores']->first())

@extends('layouts.panel', [
    'title' => $title ?? ($tenant->name.' Dashboard'),
    'heading' => $heading ?? ($tenant->name.' operations'),
    'subheading' => $subheading ?? 'Track conversations, orders, and store performance from a single responsive workspace.',
    'brandName' => 'AlphaHarvest',
    'brandLabel' => 'Tenant Workspace',
    'brandCaption' => $tenant->name,
    'navigation' => [
        [
            'label' => 'Overview',
            'description' => 'Metrics and store health',
            'url' => route('dashboard.show', $tenant),
            'active' => request()->routeIs('dashboard.show'),
        ],
        [
            'label' => 'Inbox',
            'description' => 'Open conversations',
            'url' => route('dashboard.inbox', $tenant),
            'active' => request()->routeIs('dashboard.inbox'),
        ],
        [
            'label' => 'Orders',
            'description' => 'Paged order handling',
            'url' => route('dashboard.orders', $tenant),
            'active' => request()->routeIs('dashboard.orders'),
        ],
        [
            'label' => 'Catalog',
            'description' => 'Open public storefront',
            'url' => $primaryStore ? route('platform.catalog', $primaryStore) : route('platform.home'),
            'active' => false,
        ],
    ],
    'topActions' => array_values(array_filter([
        [
            'label' => 'Public home',
            'url' => route('platform.home'),
            'variant' => 'secondary',
        ],
        $primaryStore ? [
            'label' => 'Open catalog',
            'url' => route('platform.catalog', $primaryStore),
            'variant' => 'primary',
        ] : null,
    ])),
    'navbarMeta' => [
        strtoupper($tenant->plan).' plan',
        $tenant->currency,
    ],
    'headerBadges' => $headerBadges ?? [
        strtoupper($tenant->plan).' plan',
        $tenant->timezone,
        'Material shell',
    ],
    'sidebarFooter' => 'Tenant dashboard',
    'footerText' => 'Tenant operations now split overview, inbox, and orders into focused pages.',
])
