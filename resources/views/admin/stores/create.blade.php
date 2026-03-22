@extends('admin.layout', [
    'title' => 'Create Store',
    'heading' => 'Create store',
    'subheading' => 'Provision a storefront, connect its WhatsApp settings, and make it ready for catalog sync.',
])

@section('content')
    <section class="panel">
        <h2>New store</h2>

        @include('admin.stores._form', [
            'action' => route('admin.stores.store'),
            'submitLabel' => 'Create store',
            'backUrl' => route('admin.stores.index'),
        ])
    </section>
@endsection
