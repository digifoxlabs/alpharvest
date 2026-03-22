<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} Catalog</title>
    <style>
        :root {
            --bg: #f4efe4;
            --ink: #1f2d25;
            --muted: #6d796f;
            --card: rgba(255, 252, 246, 0.92);
            --line: rgba(31, 45, 37, 0.1);
            --accent: #cf7a2a;
            --accent-soft: rgba(207, 122, 42, 0.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(207, 122, 42, 0.16), transparent 28%),
                linear-gradient(180deg, #f9f4ea 0%, #efe5d5 100%);
        }
        .shell {
            width: min(1120px, calc(100% - 28px));
            margin: 0 auto;
            padding: 28px 0 44px;
            display: grid;
            gap: 18px;
        }
        .hero, .panel {
            border-radius: 26px;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: 0 18px 40px rgba(31, 45, 37, 0.08);
        }
        .hero {
            padding: 28px;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 12px;
            color: var(--muted);
        }
        h1, h2, h3, p { margin: 0; }
        .hero p {
            margin-top: 12px;
            max-width: 60ch;
            line-height: 1.65;
            color: var(--muted);
        }
        .grid {
            display: grid;
            gap: 18px;
        }
        .category {
            padding: 22px;
        }
        .products {
            margin-top: 18px;
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .product {
            padding: 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid var(--line);
            display: grid;
            gap: 10px;
        }
        .price-stack {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 10px;
        }
        .price {
            color: var(--accent);
            font-weight: 700;
        }
        .price-original {
            color: var(--muted);
            text-decoration: line-through;
        }
        .meta {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .attribute-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <main class="shell">
        <section class="hero">
            <div class="eyebrow">Web Catalog</div>
            <h1>{{ $store->whatsapp_brand_name ?: $store->name }}</h1>
            <p>{{ $store->description ?: 'This temporary web catalog is the launch point for the custom WhatsApp webstore experience.' }}</p>
            <p class="tag">Custom catalog webview placeholder</p>
        </section>

        <section class="grid">
            @forelse ($store->categories as $category)
                <article class="panel category">
                    <div class="eyebrow">Category</div>
                    <h2>{{ $category->name }}</h2>
                    @if ($category->description)
                        <p style="margin-top: 8px; color: var(--muted);">{{ $category->description }}</p>
                    @endif

                    <div class="products">
                        @forelse ($category->products as $product)
                            <article class="product">
                                <h3>{{ $product->name }}</h3>
                                <div class="meta">{{ $product->sku }}</div>
                                <div class="price-stack">
                                    @if ($product->sale_price && $product->sale_price > 0 && $product->sale_price <= $product->price)
                                        <div class="price">{{ $store->currency }} {{ number_format((float) $product->sale_price, 2) }}</div>
                                        <div class="price-original">{{ $store->currency }} {{ number_format((float) $product->price, 2) }}</div>
                                    @else
                                        <div class="price">{{ $store->currency }} {{ number_format((float) $product->price, 2) }}</div>
                                    @endif
                                </div>
                                <div class="attribute-list">
                                    @if ($product->color)
                                        <span class="tag">Color: {{ $product->color }}</span>
                                    @endif
                                    @if ($product->size)
                                        <span class="tag">Size: {{ $product->size }}</span>
                                    @endif
                                    @if ($product->shipping_weight)
                                        <span class="tag">Weight: {{ number_format((float) $product->shipping_weight, 2) }} kg</span>
                                    @endif
                                </div>
                                <p class="meta">{{ $product->description ?: 'Product details will appear here in the next webstore version.' }}</p>
                            </article>
                        @empty
                            <p class="meta">No active products in this category yet.</p>
                        @endforelse
                    </div>
                </article>
            @empty
                <article class="panel category">
                    <h2>No catalog yet</h2>
                    <p style="margin-top: 10px; color: var(--muted);">Add categories and products in admin to populate this web catalog.</p>
                </article>
            @endforelse
        </section>
    </main>
</body>
</html>
