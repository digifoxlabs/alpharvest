@extends('tenant.layout', [
    'title' => $tenant->name.' Product',
    'heading' => 'Edit product',
    'subheading' => 'Update product pricing, inventory, media, and merchandising for '.$product->name.'.',
    'headerBadges' => [
        $product->name,
        strtoupper($tenant->plan).' plan',
        'Product settings',
    ],
])

@section('content')
    <section class="panel">
        <h2>{{ $product->name }}</h2>

        @include('tenant.products._form', [
            'product' => $product,
            'action' => route('dashboard.products.update', [$tenant, $product]),
            'method' => 'PUT',
            'submitLabel' => 'Save product',
            'backUrl' => route('dashboard.products.index', $tenant),
        ])
    </section>
@endsection
