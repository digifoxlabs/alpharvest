@extends('admin.layout', [
    'title' => 'Message Statuses',
    'heading' => 'Message statuses',
    'subheading' => 'Track inbound customer messages and outbound WhatsApp delivery, read, and failure states.',
])

@section('content')
    <section class="panel spotlight">
        <div>
            <p class="eyebrow">Message oversight</p>
            <h2>WhatsApp status queue</h2>
            <p class="muted">Review message health across every tenant store with filters for direction, tenant, and delivery lifecycle state.</p>
        </div>
        <div class="summary-grid">
            <article class="summary-card">
                <span class="eyebrow">Messages</span>
                <strong>{{ $stats['total'] }}</strong>
                <span class="muted">total logged</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Inbound</span>
                <strong>{{ $stats['inbound'] }}</strong>
                <span class="muted">customer updates</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Outbound</span>
                <strong>{{ $stats['outbound'] }}</strong>
                <span class="muted">store replies</span>
            </article>
            <article class="summary-card">
                <span class="eyebrow">Filtered</span>
                <strong>{{ $stats['filtered'] }}</strong>
                <span class="muted">results in view</span>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <p class="eyebrow">Queue</p>
                <h2>All recent messages</h2>
                <p class="muted">Newest first across every tenant store.</p>
            </div>
            <span class="badge subtle">{{ $messages->total() }} matching</span>
        </div>

        <form class="toolbar toolbar--four" method="GET" action="{{ route('admin.messages.index') }}">
            <label class="toolbar-field">
                Search messages
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Body, WhatsApp ID, customer, store">
            </label>
            <label class="toolbar-field">
                Tenant
                <select name="tenant_id">
                    <option value="">All tenants</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected($filters['tenant_id'] === (string) $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="toolbar-field">
                Direction
                <select name="direction">
                    <option value="">All directions</option>
                    <option value="inbound" @selected($filters['direction'] === 'inbound')>Inbound</option>
                    <option value="outbound" @selected($filters['direction'] === 'outbound')>Outbound</option>
                </select>
            </label>
            <label class="toolbar-field">
                Status
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="received" @selected($filters['status'] === 'received')>Received</option>
                    <option value="queued" @selected($filters['status'] === 'queued')>Queued</option>
                    <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                    <option value="delivered" @selected($filters['status'] === 'delivered')>Delivered</option>
                    <option value="read" @selected($filters['status'] === 'read')>Read</option>
                    <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                </select>
            </label>
            <div class="toolbar-actions">
                <button type="submit">Apply filters</button>
                <a class="button secondary" href="{{ route('admin.messages.index') }}">Reset</a>
            </div>
        </form>

        <div class="table entity-table entity-table--stacked">
            @forelse ($messages as $message)
                <div class="entity-row entity-row--stacked">
                    <div class="entity-main">
                        <div class="entity-title">
                            <strong>{{ $message->conversation?->store?->name ?: 'Unknown store' }}</strong>
                            <span class="badge {{ $message->status_tone }}">{{ $message->status_label }}</span>
                            <span class="badge subtle">{{ ucfirst($message->direction) }}</span>
                            <span class="badge subtle">{{ $message->type }}</span>
                        </div>
                        <div class="entity-meta">
                            <span>{{ $message->conversation?->store?->tenant?->name ?: 'No tenant' }}</span>
                            <span>{{ $message->conversation?->customer?->name ?: $message->conversation?->customer?->phone ?: 'Unknown customer' }}</span>
                        </div>
                        <p class="entity-copy">{{ $message->body ?: 'No message body stored.' }}</p>
                        <div class="chip-row">
                            <span class="badge subtle">
                                WhatsApp ID:
                                @if ($message->whatsapp_message_id)
                                    {{ $message->whatsapp_message_id }}
                                @elseif ($message->direction === 'outbound' && $message->status_label === 'Failed')
                                    Not assigned because Meta rejected the message
                                @else
                                    Not available
                                @endif
                            </span>
                        </div>
                        <p class="muted">Sent: {{ $message->sent_at?->format('Y-m-d H:i:s') ?: 'Not sent' }} | Delivered: {{ $message->delivered_at?->format('Y-m-d H:i:s') ?: 'Pending' }} | Read: {{ $message->read_at?->format('Y-m-d H:i:s') ?: 'Pending' }}</p>
                        @if ($message->status_detail)
                            <p class="muted">Detail: {{ $message->status_detail }}</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted">No messages yet.</p>
            @endforelse
        </div>

        @include('layouts.panel.pagination', ['paginator' => $messages])
    </section>
@endsection
