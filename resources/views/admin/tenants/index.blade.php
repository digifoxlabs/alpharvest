@extends('admin.layout', [
    'title' => 'Manage Tenants',
    'heading' => 'Manage tenants',
    'subheading' => 'Create and maintain SaaS workspaces that own stores and dashboard users.',
])

@section('content')
    <section class="grid columns-2">
        <article class="panel">
            <h2>Create tenant</h2>
            <form class="stack" method="POST" action="{{ route('admin.tenants.store') }}">
                @csrf
                <label>
                    Tenant name
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Northwind Commerce" required>
                </label>

                <div class="two-up">
                    <label>
                        Slug
                        <input type="text" name="slug" value="{{ old('slug') }}" placeholder="northwind-commerce" required>
                    </label>
                    <label>
                        Plan
                        <input type="text" name="plan" value="{{ old('plan', 'growth') }}" placeholder="growth" required>
                    </label>
                </div>

                <div class="two-up">
                    <label>
                        Timezone
                        <input type="text" name="timezone" value="{{ old('timezone', 'UTC') }}" placeholder="UTC" required>
                    </label>
                    <label>
                        Currency
                        <input type="text" name="currency" value="{{ old('currency', 'USD') }}" maxlength="3" required>
                    </label>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                    Tenant is active
                </label>

                <button type="submit">Create tenant</button>
            </form>
        </article>

        <article class="panel">
            <div class="table-header">
                <div>
                    <h2>Existing tenants</h2>
                    <p class="muted">Each tenant is a top-level SaaS account.</p>
                </div>
            </div>

            <div class="table">
                @forelse ($tenants as $tenant)
                    <div class="table-row">
                        <div>
                            <strong>{{ $tenant->name }}</strong>
                            <p class="muted">{{ $tenant->slug }} | {{ strtoupper($tenant->plan) }} | {{ $tenant->currency }} | {{ $tenant->timezone }}</p>
                            <p class="muted">{{ $tenant->stores_count }} stores | {{ $tenant->users_count }} users | {{ $tenant->is_active ? 'Active' : 'Inactive' }}</p>
                        </div>
                        <div class="actions">
                            <a class="button secondary" href="{{ route('admin.tenants.edit', $tenant) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Delete this tenant and all dependent data?');">
                                @csrf
                                @method('DELETE')
                                <button class="danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="muted">No tenants yet.</p>
                @endforelse
            </div>
        </article>
    </section>
@endsection
