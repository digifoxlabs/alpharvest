# AlphaHarvest WhatsApp Store SaaS

AlphaHarvest is now a Laravel 10 backend for a WhatsApp-first commerce SaaS platform. Customers can browse products, build carts, create orders, and receive payment links directly from a WhatsApp conversation handled through Meta's Cloud API webhook flow.

## Architecture

The backend follows this flow:

`Customer (WhatsApp) -> WhatsApp Cloud API -> Laravel Webhook Endpoint -> Chatbot Engine / Agent Inbox / Store Engine -> MySQL -> Admin SaaS Dashboard`

Core backend layers:

- `WhatsAppWebhookService`: ingests Meta webhook payloads, resolves stores, stores messages, and triggers chatbot replies.
- `ChatbotEngineService`: handles `Hi`, `Visit Store`, `Orders`, `Contact`, add-to-cart actions, checkout, and payment prompts.
- `StoreEngineService`: manages catalog views, active carts, order creation, and order summaries.
- `PaymentLinkService`: creates payment records and checkout links, and marks orders paid.
- `AgentInboxService`: aggregates tenant-level conversation and order data for the dashboard.

## Domain model

The platform includes:

- Multi-tenant SaaS accounts via `tenants`
- Storefronts via `stores`
- Catalogs via `product_categories` and `products`
- WhatsApp shoppers via `customers`, `conversations`, and `messages`
- Commerce flow via `carts`, `cart_items`, `orders`, `order_items`, and `payments`
- Webhook observability via `webhook_events`

## Main routes

Web routes:

- `/` platform overview page
- `/admin` admin panel dashboard
- `/admin/tenants` tenant management
- `/admin/stores` store management
- `/admin/categories` category management
- `/admin/products` product management
- `/dashboard/{tenant}` tenant dashboard
- `/pay/{payment}` payment checkout page

API routes:

- `GET /api/storefront/{store}` catalog summary
- `GET /api/storefront/{store}/products` active product list
- `GET /api/dashboard/{tenant}/overview` dashboard data
- `GET /api/dashboard/{tenant}/conversations` inbox feed
- `GET /api/dashboard/{tenant}/orders` recent orders
- `GET /api/whatsapp/webhook` Meta verification endpoint
- `POST /api/whatsapp/webhook` incoming WhatsApp messages

## Example WhatsApp experience

Customers can:

- send `Hi` and receive `Visit Store`, `Orders`, and `Contact` buttons
- tap `Visit Store` and receive WhatsApp product cards with images, price, and `Add to Cart`
- tap `Orders` to see the current cart or active order
- tap `Contact` to see store email and contact number
- use `Add to Cart`, `Checkout`, and `Pay Now` from the WhatsApp flow

The current build keeps browsing and ordering inside WhatsApp, then sends a secure payment link for the final payment step. True in-chat WhatsApp payments depend on Meta payment capabilities for the connected business account.

For the best WhatsApp storefront experience, configure these in admin:

- Store `meta_catalog_id` in `/admin/stores`
- Product `meta_retailer_id` in `/admin/products`

When those are present, `Visit Store` sends a native WhatsApp multi-product catalog message so the customer sees the store as a full in-chat storefront instead of separate product cards.

## Setup

1. Install dependencies:

```bash
composer install
```

2. Configure `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=alpharvest
DB_USERNAME=root
DB_PASSWORD=

WHATSAPP_BASE_URL=https://graph.facebook.com/v20.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=

PAYMENTS_PROVIDER=manual_link
PAYMENTS_CALLBACK_SECRET=
```

3. Run the database:

```bash
php artisan migrate --seed
```

4. Expose uploaded product/store images:

```bash
php artisan storage:link
```

5. Start the app:

```bash
php artisan serve
```

## Testing

The feature suite covers:

- Storefront catalog responses
- WhatsApp webhook -> cart/message flow
- Payment confirmation -> order paid flow

Run tests with:

```bash
php artisan test
```
