@extends('admin.layout', [
    'title' => 'Edit Product',
    'heading' => 'Edit product',
    'subheading' => 'Update product pricing, inventory, media, and merchandising for '.$product->name.'.',
])

@section('content')
    <section class="panel">
        <h2>{{ $product->name }}</h2>

        @include('admin.products._form', [
            'product' => $product,
            'action' => route('admin.products.update', $product),
            'method' => 'PUT',
            'submitLabel' => 'Save product',
            'backUrl' => route('admin.products.index'),
        ])
    </section>
@endsection
