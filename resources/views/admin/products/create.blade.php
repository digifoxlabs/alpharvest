@extends('admin.layout', [
    'title' => 'Create Product',
    'heading' => 'Create product',
    'subheading' => 'Add a new sellable product with pricing, inventory, media, and Meta retailer mapping.',
])

@section('content')
    <section class="panel">
        <h2>New product</h2>

        @include('admin.products._form', [
            'action' => route('admin.products.store'),
            'submitLabel' => 'Create product',
            'backUrl' => route('admin.products.index'),
        ])
    </section>
@endsection
