@extends('admin.layout', [
    'title' => 'Edit Tenant',
    'heading' => 'Edit tenant',
    'subheading' => 'Update workspace settings for '.$tenant->name.'.',
])

@section('content')
    <section class="panel">
        <h2>{{ $tenant->name }}</h2>

        <form class="stack" method="POST" action="{{ route('admin.tenants.update', $tenant) }}">
            @csrf
            @method('PUT')

            <label>
                Tenant name
                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required>
            </label>

            <div class="two-up">
                <label>
                    Slug
                    <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required>
                </label>
                <label>
                    Plan
                    <input type="text" name="plan" value="{{ old('plan', $tenant->plan) }}" required>
                </label>
            </div>

            <div class="two-up">
                <label>
                    Timezone
                    <input type="text" name="timezone" value="{{ old('timezone', $tenant->timezone) }}" required>
                </label>
                <label>
                    Currency
                    <input type="text" name="currency" value="{{ old('currency', $tenant->currency) }}" maxlength="3" required>
                </label>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $tenant->is_active))>
                Tenant is active
            </label>

            <div class="actions">
                <button type="submit">Save tenant</button>
                <a class="button secondary" href="{{ route('admin.tenants.index') }}">Back</a>
            </div>
        </form>
    </section>
@endsection
