@extends('tenant.layout', [
    'title' => $tenant->name.' Store',
    'heading' => 'Create store',
    'subheading' => 'Set up a new storefront for '.$tenant->name.' without leaving the tenant workspace.',
    'headerBadges' => [
        strtoupper($tenant->plan).' plan',
        'Store management',
        'New store',
    ],
])

@section('content')
    <section class="panel">
        <h2>New store</h2>

        @include('tenant.stores._form', [
            'action' => route('dashboard.stores.store', $tenant),
            'submitLabel' => 'Create store',
            'backUrl' => route('dashboard.stores.index', $tenant),
        ])
    </section>
@endsection
