<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->name }} | {{ $store->name }}</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #1f2937;
            --muted: #6b7280;
            --canvas: #f7f4ee;
            --card: #fffdf8;
            --accent: #0b6b58;
            --line: #e5dfd3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top, rgba(11, 107, 88, 0.12), transparent 35%),
                linear-gradient(180deg, #f8f5ef 0%, #f3ede1 100%);
            color: var(--ink);
        }

        main {
            max-width: 860px;
            margin: 0 auto;
            padding: 48px 20px 72px;
        }

        .card {
            display: grid;
            gap: 28px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 28px;
            box-shadow: 0 18px 48px rgba(31, 41, 55, 0.08);
        }

        @media (min-width: 720px) {
            .card {
                grid-template-columns: minmax(260px, 1fr) minmax(0, 1.2fr);
                align-items: start;
            }
        }

        .media {
            border-radius: 22px;
            overflow: hidden;
            background: #efe7d7;
            min-height: 260px;
        }

        .media img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .placeholder {
            display: grid;
            place-items: center;
            min-height: 260px;
            padding: 24px;
            color: var(--muted);
            text-align: center;
            font-size: 1.05rem;
        }

        .eyebrow {
            margin: 0 0 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--accent);
            font-size: 0.78rem;
        }

        h1 {
            margin: 0 0 12px;
            font-size: clamp(2rem, 5vw, 3.3rem);
            line-height: 1.03;
        }

        .price {
            margin: 0 0 20px;
            font-size: 1.4rem;
            font-weight: bold;
        }

        .compare {
            color: var(--muted);
            text-decoration: line-through;
            margin-left: 10px;
            font-size: 1rem;
            font-weight: normal;
        }

        .description {
            margin: 0 0 24px;
            color: #374151;
            line-height: 1.7;
            white-space: pre-line;
        }

        .meta {
            display: grid;
            gap: 10px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            color: var(--muted);
        }

        .cta {
            margin-top: 28px;
            display: inline-block;
            padding: 14px 18px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<main>
    <section class="card">
        <div class="media">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
            @else
                <div class="placeholder">Product image will appear here once uploaded in the admin panel.</div>
            @endif
        </div>

        <div>
            <p class="eyebrow">{{ $store->whatsapp_brand_name ?: $store->name }}</p>
            <h1>{{ $product->name }}</h1>
            <p class="price">
                {{ number_format((float) $product->price, 2) }} {{ $store->currency }}
                @if ($product->compare_at_price && $product->compare_at_price > $product->price)
                    <span class="compare">{{ number_format((float) $product->compare_at_price, 2) }} {{ $store->currency }}</span>
                @endif
            </p>

            <p class="description">{{ $product->description ?: 'Available now in our WhatsApp store.' }}</p>

            <div class="meta">
                <div>SKU: {{ $product->sku }}</div>
                <div>Availability: {{ $product->inventory_quantity > 0 ? 'In stock' : 'Out of stock' }}</div>
                <div>Category: {{ $product->category?->name ?: 'General' }}</div>
                <div>Store: {{ $store->name }}</div>
            </div>

            <a class="cta" href="{{ route('platform.home') }}">Continue in WhatsApp store</a>
        </div>
    </section>
</main>
</body>
</html>
