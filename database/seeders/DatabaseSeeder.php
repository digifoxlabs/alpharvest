<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'AlphaHarvest Commerce',
            'slug' => 'alpharvest-commerce',
            'plan' => 'growth',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'USD',
            'settings' => [
                'channels' => ['whatsapp', 'dashboard'],
            ],
        ]);

        $owner = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'AlphaHarvest Owner',
            'email' => 'owner@alpharvest.test',
            'role' => 'owner',
            'password' => Hash::make('password'),
            'phone' => '+15550001111',
        ]);

        $store = Store::create([
            'tenant_id' => $tenant->id,
            'name' => 'AlphaHarvest Wellness Store',
            'slug' => 'alpharvest-store',
            'support_phone' => '+15550009999',
            'contact_email' => 'support@alpharvest.test',
            'contact_phone' => '+15550002222',
            'description' => 'Organic wellness blends, superfoods, and premium teas sold directly in WhatsApp.',
            'currency' => 'USD',
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_business_account_id' => '987654321',
            'meta_catalog_id' => '5566778899',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
            'whatsapp_welcome_text' => 'Hi! Welcome to AlphaHarvest. Choose Visit Store, Orders, or Contact.',
            'whatsapp_store_intro' => 'Browse our featured wellness products below. Each card lets customers add items to cart from inside WhatsApp.',
            'whatsapp_contact_text' => 'Our support team usually replies within business hours.',
            'settings' => [
                'bot_enabled' => true,
                'owner_id' => $owner->id,
            ],
        ]);

        $coffee = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Coffee & Energy',
            'slug' => 'coffee-energy',
            'description' => 'Daily performance blends and premium roasts.',
            'sort_order' => 1,
        ]);

        $wellness = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Wellness',
            'slug' => 'wellness',
            'description' => 'Adaptogens and nutrition boosters.',
            'sort_order' => 2,
        ]);

        $products = collect([
            [
                'category_id' => $coffee->id,
                'name' => 'Morning Lift Coffee',
                'slug' => 'morning-lift-coffee',
                'sku' => 'COF-250',
                'price' => 18.50,
                'inventory' => 60,
                'description' => 'Single-origin roast with a clean citrus finish.',
            ],
            [
                'category_id' => $coffee->id,
                'name' => 'Focus Brew',
                'slug' => 'focus-brew',
                'sku' => 'FOC-100',
                'price' => 14.00,
                'inventory' => 48,
                'description' => 'Mushroom coffee blend for steady focus.',
            ],
            [
                'category_id' => $wellness->id,
                'name' => 'Ashwagandha Daily',
                'slug' => 'ashwagandha-daily',
                'sku' => 'ASH-060',
                'price' => 22.00,
                'inventory' => 35,
                'description' => 'Daily stress support capsules.',
            ],
            [
                'category_id' => $wellness->id,
                'name' => 'Golden Turmeric Mix',
                'slug' => 'golden-turmeric-mix',
                'sku' => 'GLD-150',
                'price' => 16.75,
                'inventory' => 40,
                'description' => 'Turmeric latte mix with ginger and black pepper.',
            ],
        ])->map(fn (array $product) => Product::create([
            'store_id' => $store->id,
            'product_category_id' => $product['category_id'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'sku' => $product['sku'],
            'meta_retailer_id' => $product['sku'],
            'price' => $product['price'],
            'inventory_quantity' => $product['inventory'],
            'description' => $product['description'],
            'metadata' => ['featured' => true],
        ]));

        $customer = Customer::create([
            'store_id' => $store->id,
            'name' => 'Riya Sharma',
            'phone' => '+15551234567',
            'whatsapp_id' => '+15551234567',
            'preferred_language' => 'en',
            'last_message_at' => now()->subMinutes(3),
        ]);

        $conversation = Conversation::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $owner->id,
            'source' => 'whatsapp',
            'status' => 'open',
            'last_message_at' => now()->subMinutes(3),
            'context' => [
                'last_intent' => 'checkout',
            ],
        ]);

        $order = Order::create([
            'store_id' => $store->id,
            'customer_id' => $customer->id,
            'conversation_id' => $conversation->id,
            'order_number' => 'ALPHARVEST-00001',
            'status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'currency' => 'USD',
            'subtotal' => 36.50,
            'total' => 36.50,
            'placed_at' => now()->subMinutes(2),
            'metadata' => [
                'channel' => 'whatsapp',
            ],
        ]);

        $firstProduct = $products->first();
        $secondProduct = $products->skip(1)->first();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'product_name' => $firstProduct->name,
            'sku' => $firstProduct->sku,
            'quantity' => 1,
            'unit_price' => $firstProduct->price,
            'total_price' => $firstProduct->price,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'product_name' => $secondProduct->name,
            'sku' => $secondProduct->sku,
            'quantity' => 1,
            'unit_price' => $secondProduct->price,
            'total_price' => $secondProduct->price,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'manual_link',
            'reference' => 'PAY-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $payment->update([
            'payment_url' => url('/pay/'.$payment->reference),
        ]);
    }
}
