<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlphaHarvest WhatsApp Commerce</title>
    <style>
        :root {
            --bg: #f4efe6;
            --surface: rgba(255, 255, 255, 0.74);
            --ink: #15231d;
            --muted: #5f6d66;
            --accent: #1c8a5f;
            --accent-dark: #0f5f41;
            --line: rgba(21, 35, 29, 0.1);
            --shadow: 0 24px 70px rgba(34, 46, 38, 0.12);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(28, 138, 95, 0.22), transparent 32%),
                radial-gradient(circle at bottom right, rgba(193, 122, 54, 0.16), transparent 24%),
                linear-gradient(135deg, #efe6d5 0%, #f8f5ee 48%, #e5ecdf 100%);
        }

        a { color: inherit; text-decoration: none; }

        .shell {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 32px 0 56px;
        }

        .hero, .card {
            backdrop-filter: blur(16px);
            background: var(--surface);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 36px;
            display: grid;
            gap: 24px;
        }

        .pill {
            display: inline-flex;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(28, 138, 95, 0.12);
            color: var(--accent-dark);
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1, h2, h3, p { margin: 0; }

        h1 {
            font-size: clamp(2.2rem, 5vw, 4.8rem);
            line-height: 0.96;
            max-width: 12ch;
        }

        .lede {
            font-family: "Trebuchet MS", sans-serif;
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--muted);
            max-width: 68ch;
        }

        .hero-grid,
        .grid {
            display: grid;
            gap: 18px;
        }

        .hero-grid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .metric, .card {
            padding: 22px;
        }

        .metric strong {
            display: block;
            font-size: 2rem;
            margin-bottom: 6px;
        }

        .metric span,
        .muted {
            font-family: "Trebuchet MS", sans-serif;
            color: var(--muted);
        }

        .section {
            margin-top: 26px;
        }

        .section-head {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: end;
            margin-bottom: 18px;
        }

        .section-head p {
            font-family: "Trebuchet MS", sans-serif;
            color: var(--muted);
        }

        .grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .card h3 {
            font-size: 1.35rem;
            margin-bottom: 10px;
        }

        .stack {
            display: grid;
            gap: 10px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-family: "Trebuchet MS", sans-serif;
            padding: 12px 0;
            border-bottom: 1px solid var(--line);
        }

        .row:last-child { border-bottom: none; }

        .button {
            display: inline-flex;
            width: fit-content;
            margin-top: 18px;
            padding: 12px 18px;
            border-radius: 999px;
            background: var(--accent);
            color: white;
            font-family: "Trebuchet MS", sans-serif;
            font-weight: 700;
        }

        code {
            padding: 2px 6px;
            border-radius: 999px;
            background: rgba(21, 35, 29, 0.08);
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <span class="pill">Laravel backend for WhatsApp commerce</span>
            <div class="stack">
                <h1>Sell products, take orders, and collect payments from a WhatsApp chat.</h1>
                <p class="lede">
                    AlphaHarvest now runs as a SaaS-ready Laravel backend with tenants, stores, products, conversations,
                    carts, orders, payments, Meta webhook ingestion, a chatbot engine, an agent inbox view, and dashboard APIs.
                </p>
                <a class="button" href="{{ route('admin.dashboard') }}">Open admin panel</a>
            </div>
            <div class="hero-grid">
                <div class="metric">
                    <strong>{{ $tenants->count() }}</strong>
                    <span>tenants available</span>
                </div>
                <div class="metric">
                    <strong>{{ $stores->count() }}</strong>
                    <span>connected storefronts</span>
                </div>
                <div class="metric">
                    <strong>5</strong>
                    <span>core layers: webhook, chatbot, inbox, store engine, dashboard</span>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-head">
                <div>
                    <h2>How the platform flows</h2>
                    <p>Customer chats enter through Meta, become persisted conversations, mutate carts and orders, then land in the admin dashboard.</p>
                </div>
            </div>
            <div class="grid">
                <article class="card stack">
                    <h3>WhatsApp entrypoint</h3>
                    <p class="muted">Meta verifies and posts to <code>/api/whatsapp/webhook</code>. The backend resolves the store from the incoming phone number ID.</p>
                </article>
                <article class="card stack">
                    <h3>Chatbot engine</h3>
                    <p class="muted">Commands like <code>MENU</code>, <code>ADD SKU 2</code>, <code>CART</code>, <code>CHECKOUT</code>, and <code>PAY</code> drive the customer journey.</p>
                </article>
                <article class="card stack">
                    <h3>Commerce core</h3>
                    <p class="muted">Catalog, cart, order, and payment models keep the chat flow synchronized with actual commerce records.</p>
                </article>
            </div>
        </section>

        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Tenants and stores</h2>
                    <p>Open a tenant dashboard or call the storefront APIs directly.</p>
                </div>
            </div>
            <div class="grid">
                @foreach ($stores as $store)
                    <article class="card stack">
                        <div>
                            <h3>{{ $store->name }}</h3>
                            <p class="muted">{{ $store->description ?: 'Commerce-ready WhatsApp storefront.' }}</p>
                        </div>
                        <div class="row"><span>Products</span><strong>{{ $store->products_count }}</strong></div>
                        <div class="row"><span>Customers</span><strong>{{ $store->customers_count }}</strong></div>
                        <div class="row"><span>Conversations</span><strong>{{ $store->conversations_count }}</strong></div>
                        <div class="row"><span>Orders</span><strong>{{ $store->orders_count }}</strong></div>
                        <p class="muted">Storefront API: <code>/api/storefront/{{ $store->slug }}</code></p>
                        <a class="button" href="{{ route('dashboard.show', $store->tenant) }}">Open {{ $store->tenant->name }} dashboard</a>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
