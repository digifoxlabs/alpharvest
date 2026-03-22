@extends('tenant.layout', [
    'title' => $tenant->name.' Store',
    'heading' => 'Edit store',
    'subheading' => 'Update storefront settings, WhatsApp connection details, and presentation for '.$store->name.'.',
    'headerBadges' => [
        $store->name,
        strtoupper($tenant->plan).' plan',
        'Store settings',
    ],
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>{{ $store->name }}</h2>

            <div class="callout">
                <div class="actions">
                    <strong>Native WhatsApp catalog</strong>
                    <span class="badge {{ $catalogReadiness['ready'] ? 'success' : 'warning' }}">
                        {{ $catalogReadiness['ready'] ? 'Ready' : 'Needs setup' }}
                    </span>
                </div>
                <div class="checklist">
                    <span class="badge {{ $catalogReadiness['checks']['phone_number_id'] ? 'success' : 'warning' }}">Phone number ID</span>
                    <span class="badge {{ $catalogReadiness['checks']['access_token'] ? 'success' : 'warning' }}">Access token</span>
                    <span class="badge {{ $catalogReadiness['checks']['meta_catalog_id'] ? 'success' : 'warning' }}">Meta catalog ID</span>
                    <span class="badge {{ $catalogReadiness['checks']['active_products'] ? 'success' : 'warning' }}">Active products {{ $catalogReadiness['active_products'] }}</span>
                    <span class="badge {{ $catalogReadiness['checks']['mapped_products'] ? 'success' : 'warning' }}">Retailer IDs {{ $catalogReadiness['catalog_products'] }}/{{ $catalogReadiness['active_products'] }}</span>
                </div>
                @if ($catalogReadiness['issues'] !== [])
                    <p class="muted">{{ implode(' ', $catalogReadiness['issues']) }}</p>
                @else
                    <p class="muted">App-side checks pass. If WhatsApp still does not show the native catalog, confirm the same Meta catalog is linked inside Commerce Manager.</p>
                @endif
                <p class="muted">Product feed: <a href="{{ route('feeds.meta-products') }}" target="_blank">/feeds/meta-products</a></p>
            </div>

            @include('tenant.stores._form', [
                'store' => $store,
                'action' => route('dashboard.stores.update', [$tenant, $store]),
                'method' => 'PUT',
                'submitLabel' => 'Save store',
                'backUrl' => route('dashboard.stores.index', $tenant),
                'deliveryZonesText' => $deliveryZonesText,
                'undeliverableMessage' => data_get($store->settings, 'undeliverable_message'),
            ])
        </article>

        <article class="panel">
            <h2>Catalog checklist</h2>
            <div class="table">
                <div class="table-row">
                    <strong>WhatsApp phone number ID</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['phone_number_id'] ? 'Configured' : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Access token</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['access_token'] ? 'Configured' : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Meta catalog ID</strong>
                    <span class="muted">{{ $catalogReadiness['checks']['meta_catalog_id'] ? ($store->meta_catalog_id ?: 'Configured') : 'Missing' }}</span>
                </div>
                <div class="table-row">
                    <strong>Active products</strong>
                    <span class="muted">{{ $catalogReadiness['active_products'] }}</span>
                </div>
                <div class="table-row">
                    <strong>Products with local Meta retailer IDs</strong>
                    <span class="muted">{{ $catalogReadiness['catalog_products'] }} of {{ $catalogReadiness['active_products'] }}</span>
                </div>
                <div class="table-row">
                    <strong>Deliverable areas</strong>
                    <span class="muted">{{ count(data_get($store->settings, 'delivery_zones', [])) }}</span>
                </div>
            </div>
        </article>
    </section>
@endsection
