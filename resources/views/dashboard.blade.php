<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }} Dashboard</title>
    <style>
        :root {
            --bg: #101c1b;
            --panel: rgba(241, 239, 231, 0.92);
            --panel-dark: rgba(22, 37, 34, 0.88);
            --ink: #11231c;
            --muted: #5e6d67;
            --accent: #f3b457;
            --line: rgba(17, 35, 28, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Trebuchet MS", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top, rgba(243, 180, 87, 0.28), transparent 26%),
                linear-gradient(135deg, #14302d 0%, #0d1816 68%, #15201d 100%);
        }
        h1, h2, h3, p { margin: 0; }
        .shell {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 40px;
            display: grid;
            gap: 18px;
        }
        .hero, .card {
            border-radius: 26px;
            overflow: hidden;
        }
        .hero {
            padding: 30px;
            background: linear-gradient(135deg, rgba(243, 180, 87, 0.95), rgba(250, 234, 198, 0.92));
        }
        .hero p {
            max-width: 66ch;
            line-height: 1.65;
            color: rgba(17, 35, 28, 0.82);
            margin-top: 10px;
        }
        .grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .card {
            background: var(--panel);
            padding: 22px;
        }
        .value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .muted { color: var(--muted); }
        .section {
            display: grid;
            gap: 18px;
            grid-template-columns: 1.1fr 0.9fr;
        }
        .table {
            display: grid;
            gap: 14px;
        }
        .table-row {
            display: grid;
            gap: 6px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }
        .table-row:last-child { border-bottom: none; padding-bottom: 0; }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-size: 12px;
            color: var(--muted);
        }
        .api {
            background: var(--panel-dark);
            color: #f5f2e8;
        }
        .api code {
            display: block;
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.08);
        }
        @media (max-width: 900px) {
            .section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="eyebrow">{{ strtoupper($tenant->plan) }} plan</div>
            <h1>{{ $tenant->name }} SaaS dashboard</h1>
            <p>
                This tenant dashboard rolls up the WhatsApp store engine, the open agent inbox,
                and recent commerce activity into one backend-facing operational view.
            </p>
        </section>

        <section class="grid">
            <article class="card">
                <div class="value">{{ $overview['metrics']['stores'] }}</div>
                <p class="muted">active stores</p>
            </article>
            <article class="card">
                <div class="value">{{ $overview['metrics']['products'] }}</div>
                <p class="muted">catalog products</p>
            </article>
            <article class="card">
                <div class="value">{{ $overview['metrics']['open_conversations'] }}</div>
                <p class="muted">open WhatsApp conversations</p>
            </article>
            <article class="card">
                <div class="value">{{ number_format((float) $overview['metrics']['recent_order_value'], 2) }}</div>
                <p class="muted">recent order value</p>
            </article>
        </section>

        <section class="section">
            <article class="card">
                <div class="eyebrow">Agent Inbox</div>
                <h2>Open conversations</h2>
                <div class="table" style="margin-top: 18px;">
                    @forelse ($overview['open_conversations'] as $conversation)
                        <div class="table-row">
                            <strong>{{ $conversation->customer?->name ?: $conversation->customer?->phone }}</strong>
                            <span class="muted">{{ $conversation->store?->name }} | {{ ucfirst($conversation->status) }} | {{ optional($conversation->last_message_at)->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="muted">No open conversations yet.</p>
                    @endforelse
                </div>
            </article>
            <article class="card">
                <div class="eyebrow">Commerce</div>
                <h2>Recent orders</h2>
                <div class="table" style="margin-top: 18px;">
                    @forelse ($overview['recent_orders'] as $order)
                        <div class="table-row">
                            <strong>{{ $order->order_number }} | {{ $order->customer?->name ?: $order->customer?->phone ?: 'Unknown customer' }}</strong>
                            <span class="muted">{{ $order->store?->name }} | {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }} | {{ $order->currency }} {{ number_format((float) $order->total, 2) }}</span>
                            <span class="muted">
                                Deliver to pincode: {{ data_get($order->metadata, 'delivery.pincode') ?: 'Not saved' }}
                                | Address: {{ data_get($order->metadata, 'delivery.address') ?: 'Not saved' }}
                            </span>
                            <span class="muted">
                                Products:
                                {{ $order->items->map(fn ($item) => $item->quantity.' x '.$item->product_name)->implode(', ') ?: 'No items' }}
                            </span>
                        </div>
                    @empty
                        <p class="muted">No orders yet.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid">
            @foreach ($overview['stores'] as $store)
                <article class="card">
                    <div class="eyebrow">Store</div>
                    <h3>{{ $store->name }}</h3>
                    <p class="muted" style="margin-top: 10px;">{{ $store->products_count }} products | {{ $store->customers_count }} customers | {{ $store->orders_count }} orders</p>
                </article>
            @endforeach
            <article class="card api">
                <div class="eyebrow">Useful endpoints</div>
                <h3>Backend API</h3>
                <code>GET /api/dashboard/{{ $tenant->slug }}/overview</code>
                <code>GET /api/dashboard/{{ $tenant->slug }}/conversations</code>
                <code>GET /api/dashboard/{{ $tenant->slug }}/orders</code>
            </article>
        </section>
    </main>
</body>
</html>
