@extends('admin.layout', [
    'title' => 'Message Statuses',
    'heading' => 'Message statuses',
    'subheading' => 'Track inbound customer messages and outbound WhatsApp delivery, read, and failure states.',
])

@section('content')
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>All recent messages</h2>
                <p class="muted">Newest first across every tenant store.</p>
            </div>
        </div>

        <div class="table">
            @forelse ($messages as $message)
                <div class="table-row">
                    <div class="actions">
                        <strong>{{ $message->conversation?->store?->name ?: 'Unknown store' }}</strong>
                        <span class="badge {{ $message->status_tone }}">{{ $message->status_label }}</span>
                    </div>
                    <p class="muted">
                        {{ $message->conversation?->store?->tenant?->name ?: 'No tenant' }}
                        | {{ ucfirst($message->direction) }}
                        | {{ $message->type }}
                        | {{ $message->conversation?->customer?->name ?: $message->conversation?->customer?->phone ?: 'Unknown customer' }}
                    </p>
                    <p class="muted">
                        WhatsApp ID:
                        @if ($message->whatsapp_message_id)
                            {{ $message->whatsapp_message_id }}
                        @elseif ($message->direction === 'outbound' && $message->status_label === 'Failed')
                            Not assigned because Meta rejected the message
                        @else
                            Not available
                        @endif
                    </p>
                    <p>{{ $message->body ?: 'No message body stored.' }}</p>
                    <p class="muted">
                        Sent: {{ $message->sent_at?->format('Y-m-d H:i:s') ?: 'Not sent' }}
                        | Delivered: {{ $message->delivered_at?->format('Y-m-d H:i:s') ?: 'Pending' }}
                        | Read: {{ $message->read_at?->format('Y-m-d H:i:s') ?: 'Pending' }}
                    </p>
                    @if ($message->status_detail)
                        <p class="muted">Detail: {{ $message->status_detail }}</p>
                    @endif
                </div>
            @empty
                <p class="muted">No messages yet.</p>
            @endforelse
        </div>
    </section>
@endsection
