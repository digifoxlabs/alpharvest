@extends('admin.layout', [
    'title' => 'Manage Tenants',
    'heading' => 'Manage tenants',
    'subheading' => 'Create and maintain SaaS workspaces that own stores and dashboard users.',
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Tenant control</p>
            <h2>Workspace lifecycle</h2>
            <p class="muted">Keep plans, timezones, and account activity aligned while the admin shell handles filtering and pagination for larger SaaS rosters.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Tenants</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">total workspaces</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Active</span>
                <strong>{{ $stats['active'] }}</strong>
                <span class="muted">currently live</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Plans</span>
                <strong>{{ $stats['plans'] }}</strong>
                <span class="muted">plan variants</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Filtered</span>
                <strong>{{ $stats['filtered'] }}</strong>
                <span class="muted">results in view</span>
            </article>
        </div>
    </section>

    <section class="management-layout">
        <article class="panel">
            <div class="table-header">
                <div>
                    <p class="eyebrow">Create</p>
                    <h2>Create tenant</h2>
                    <p class="muted">Spin up a new workspace with its own billing plan, timezone, and currency defaults.</p>
                </div>
            </div>

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
                    <p class="eyebrow">Directory</p>
                    <h2>Existing tenants</h2>
                    <p class="muted">Each tenant is a top-level SaaS account.</p>
                </div>
                <span class="badge subtle">{{ $tenants->total() }} matching</span>
            </div>

            <form class="toolbar toolbar--three" method="GET" action="{{ route('admin.tenants.index') }}">
                <label class="toolbar-field">
                    Search tenants
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Name, slug, plan, timezone">
                </label>
                <label class="toolbar-field">
                    Status
                    <select name="status">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </label>
                <label class="toolbar-field">
                    Plan
                    <select name="plan">
                        <option value="">All plans</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan }}" @selected($filters['plan'] === $plan)>{{ ucfirst($plan) }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="toolbar-actions">
                    <button type="submit">Apply filters</button>
                    <a class="button secondary" href="{{ route('admin.tenants.index') }}">Reset</a>
                </div>
            </form>

            <div class="table entity-table">
                @forelse ($tenants as $tenant)
                    <div class="entity-row">
                        <div class="entity-main">
                            <div class="entity-title">
                                <strong>{{ $tenant->name }}</strong>
                                <span class="badge {{ $tenant->is_active ? 'success' : 'warning' }}">{{ $tenant->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="badge subtle">{{ strtoupper($tenant->plan) }}</span>
                            </div>
                            <div class="entity-meta">
                                <span>{{ $tenant->slug }}</span>
                                <span>{{ $tenant->currency }}</span>
                                <span>{{ $tenant->timezone }}</span>
                            </div>
                            <p class="entity-copy">{{ $tenant->stores_count }} stores connected and {{ $tenant->users_count }} users provisioned for this workspace.</p>
                        </div>
                        <div class="entity-actions">
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

            @include('layouts.panel.pagination', ['paginator' => $tenants])
        </article>
    </section>
@endsection
