@extends('tenant.layout', [
    'title' => $tenant->name.' Product',
    'heading' => 'Create product',
    'subheading' => 'Add a new product for '.$tenant->name.' with inventory, pricing, and catalog-ready metadata.',
    'headerBadges' => [
        strtoupper($tenant->plan).' plan',
        'Inventory control',
        'New product',
    ],
])

@section('content')
    <section class="panel">
        <h2>New product</h2>

        @include('tenant.products._form', [
            'action' => route('dashboard.products.store', $tenant),
            'submitLabel' => 'Create product',
            'backUrl' => route('dashboard.products.index', $tenant),
        ])
    </section>
@endsection
