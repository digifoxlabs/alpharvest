@extends('layouts.panel', [
    'title' => $title ?? 'Admin Panel',
    'heading' => $heading ?? 'Admin Panel',
    'subheading' => $subheading ?? 'Manage tenants, stores, categories, messages, and products from a single responsive workspace.',
    'brandName' => 'AlphaHarvest',
    'brandLabel' => 'Admin Panel',
    'brandCaption' => 'Platform control center',
    'navigation' => [
        [
            'label' => 'Overview',
            'description' => 'Platform summary',
            'url' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.dashboard'),
        ],
        [
            'label' => 'Tenants',
            'description' => 'Workspace accounts',
            'url' => route('admin.tenants.index'),
            'active' => request()->routeIs('admin.tenants.*'),
        ],
        [
            'label' => 'Stores',
            'description' => 'Commerce storefronts',
            'url' => route('admin.stores.index'),
            'active' => request()->routeIs('admin.stores.*'),
        ],
        [
            'label' => 'Categories',
            'description' => 'Catalog structure',
            'url' => route('admin.categories.index'),
            'active' => request()->routeIs('admin.categories.*'),
        ],
        [
            'label' => 'Messages',
            'description' => 'WhatsApp activity',
            'url' => route('admin.messages.index'),
            'active' => request()->routeIs('admin.messages.*'),
        ],
        [
            'label' => 'Products',
            'description' => 'Sellable inventory',
            'url' => route('admin.products.index'),
            'active' => request()->routeIs('admin.products.*'),
        ],
        [
            'label' => 'Public home',
            'description' => 'Customer-facing site',
            'url' => route('platform.home'),
            'active' => false,
        ],
    ],
    'topActions' => [
        [
            'label' => 'Open platform',
            'url' => route('platform.home'),
            'variant' => 'secondary',
        ],
        [
            'label' => 'Admin home',
            'url' => route('admin.dashboard'),
            'variant' => 'primary',
        ],
    ],
    'navbarMeta' => [
        'Unified admin',
        'Responsive UI',
    ],
    'headerBadges' => $headerBadges ?? [
        'Platform admin',
        'Sidebar workspace',
        'Mobile drawer',
    ],
    'sidebarFooter' => 'Operate every tenant from the same shell.',
    'footerText' => 'AlphaHarvest admin uses the shared responsive panel layout.',
])
