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
            'currency' => 'INR',
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
            'currency' => 'INR',
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

        $rice = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Rice',
            'slug' => 'rice',
            'description' => 'Traditional and specialty rice varieties from the region.',
            'sort_order' => 1,
        ]);

        $cookingOil = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Cooking Oil',
            'slug' => 'cooking-oil',
            'description' => 'Cold-pressed and traditional cooking oils.',
            'sort_order' => 2,
        ]);

        $pickles = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Pickles',
            'slug' => 'pickles',
            'description' => 'Regional pickles with bold, authentic flavors.',
            'sort_order' => 3,
        ]);

        $others = ProductCategory::create([
            'store_id' => $store->id,
            'name' => 'Others',
            'slug' => 'others',
            'description' => 'More pantry staples and regional specialties.',
            'sort_order' => 4,
        ]);

        $products = collect([
            [
                'category_id' => $rice->id,
                'name' => 'Titabor Aijung Rice',
                'slug' => 'titabor-aijung-rice',
                'sku' => 'RIC-TAJ',
                'price' => 899.00,
                'inventory' => 60,
                'description' => 'Premium aromatic rice from Assam with a soft fluffy finish.',
            ],
            [
                'category_id' => $rice->id,
                'name' => 'Manipuri Black Rice',
                'slug' => 'manipuri-black-rice',
                'sku' => 'RIC-MBR',
                'price' => 749.00,
                'inventory' => 48,
                'description' => 'Nutritious heirloom black rice with a rich earthy aroma.',
            ],
            [
                'category_id' => $rice->id,
                'name' => 'Sticky Rice',
                'slug' => 'sticky-rice',
                'sku' => 'RIC-STK',
                'price' => 579.00,
                'inventory' => 42,
                'description' => 'Soft sticky rice ideal for traditional steamed dishes and snacks.',
            ],
            [
                'category_id' => $rice->id,
                'name' => 'Joha Rice',
                'slug' => 'joha-rice',
                'sku' => 'RIC-JOH',
                'price' => 629.00,
                'inventory' => 35,
                'description' => 'Fragrant joha rice with a naturally sweet aroma.',
            ],
            [
                'category_id' => $cookingOil->id,
                'name' => 'Majuli Pur Mustard Oil',
                'slug' => 'majuli-pur-mustard-oil',
                'sku' => 'OIL-MPM',
                'price' => 349.00,
                'inventory' => 40,
                'description' => 'Cold-pressed mustard oil with a bold traditional flavor.',
            ],
            [
                'category_id' => $pickles->id,
                'name' => 'Ghost Chilli Pickle',
                'slug' => 'ghost-chilli-pickle',
                'sku' => 'PIC-GCP',
                'price' => 289.00,
                'inventory' => 28,
                'description' => 'Fiery ghost chilli pickle made with a traditional homestyle recipe.',
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
            'currency' => 'INR',
            'subtotal' => 1648.00,
            'total' => 1648.00,
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
