<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppHelpFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_offers_orders_and_addresses_and_shows_selected_details(): void
    {
        $counter = 0;

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.base_url' => 'https://graph.facebook.com/v20.0',
        ]);

        Http::fake(function () use (&$counter) {
            $counter++;

            return Http::response([
                'messages' => [
                    ['id' => 'wamid.outbound.'.$counter],
                ],
            ], 200);
        });

        $tenant = Tenant::factory()->create();
        $store = Store::factory()->create([
            'tenant_id' => $tenant->id,
            'whatsapp_phone_number_id' => '1234567890',
            'slug' => 'chat-store',
            'whatsapp_brand_name' => 'AlphaHarvest Store',
        ]);

        $customer = Customer::factory()->create([
            'store_id' => $store->id,
            'name' => 'Riya Sharma',
            'phone' => '15551234567',
            'metadata' => [
                'delivery' => [
                    'id' => 'addr-1',
                    'pincode' => '700001',
                    'city' => 'Kolkata',
                    'address' => "221B Market Road\nNear Central Metro",
                    'saved_at' => now()->subDay()->toIso8601String(),
                    'address_book' => [
                        [
                            'id' => 'addr-1',
                            'pincode' => '700001',
                            'city' => 'Kolkata',
                            'address' => "221B Market Road\nNear Central Metro",
                            'saved_at' => now()->subDay()->toIso8601String(),
                        ],
                        [
                            'id' => 'addr-2',
                            'pincode' => '700002',
                            'city' => 'Howrah',
                            'address' => "88 River Road\nOpposite Ferry Gate",
                            'saved_at' => now()->subDays(2)->toIso8601String(),
                        ],
                    ],
                ],
            ],
        ]);

        $orders = collect();

        for ($i = 1; $i <= 6; $i++) {
            $order = Order::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'order_number' => 'CHAT-STORE-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'status' => $i % 2 === 0 ? 'completed' : 'pending_payment',
                'payment_status' => $i % 2 === 0 ? 'paid' : 'unpaid',
                'currency' => 'USD',
                'subtotal' => 10 * $i,
                'total' => 10 * $i,
                'metadata' => [
                    'delivery' => [
                        'pincode' => '700001',
                        'city' => 'Kolkata',
                        'address' => "221B Market Road\nNear Central Metro",
                    ],
                ],
                'placed_at' => now()->subMinutes(7 - $i),
                'paid_at' => $i % 2 === 0 ? now()->subMinutes(7 - $i) : null,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_name' => 'Wellness Pack '.$i,
                'sku' => 'WEL-'.$i,
                'quantity' => 1,
                'unit_price' => 10 * $i,
                'total_price' => 10 * $i,
            ]);

            $orders->push($order);
        }

        $recentOrders = $orders->sortByDesc('placed_at')->values();
        $selectedOrder = $recentOrders[2];

        $this->postJson('/api/whatsapp/webhook', $this->textPayload('wamid.inbound.help', $customer->phone, 'Help'))->assertOk();

        $helpRequest = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->first(function (Request $request) {
                $buttons = data_get($request->data(), 'interactive.action.buttons', []);
                $ids = collect($buttons)->pluck('reply.id')->all();

                return data_get($request->data(), 'interactive.type') === 'button'
                    && $ids === ['my_orders', 'my_addresses', 'contact'];
            });

        $this->assertNotNull($helpRequest);

        $this->postJson('/api/whatsapp/webhook', $this->textPayload('wamid.inbound.orders', $customer->phone, 'My Orders'))->assertOk();

        $ordersListRequest = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->reverse()
            ->first(fn (Request $request) => data_get($request->data(), 'interactive.action.button') === 'View Orders');

        $this->assertNotNull($ordersListRequest);
        $orderRows = data_get($ordersListRequest->data(), 'interactive.action.sections.0.rows', []);
        $this->assertCount(5, $orderRows);
        $this->assertSame(
            $recentOrders->take(5)->map(fn (Order $order) => 'show_order:'.$order->id)->all(),
            collect($orderRows)->pluck('id')->all()
        );

        $this->postJson('/api/whatsapp/webhook', $this->interactiveListReplyPayload(
            'wamid.inbound.order-detail',
            $customer->phone,
            'show_order:'.$selectedOrder->id,
            $selectedOrder->order_number
        ))->assertOk();

        $orderDetailMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%Order: '.$selectedOrder->order_number.'%')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('Wellness Pack '.substr($selectedOrder->order_number, -1), $orderDetailMessage->body);
        $this->assertStringContainsString('Deliver to pincode: 700001', $orderDetailMessage->body);

        $this->postJson('/api/whatsapp/webhook', $this->textPayload('wamid.inbound.addresses', $customer->phone, 'My Addresses'))->assertOk();

        $addressesListRequest = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0])
            ->reverse()
            ->first(fn (Request $request) => data_get($request->data(), 'interactive.action.button') === 'View Addresses');

        $this->assertNotNull($addressesListRequest);
        $addressRows = data_get($addressesListRequest->data(), 'interactive.action.sections.0.rows', []);
        $this->assertSame(['show_address:addr-1', 'show_address:addr-2'], collect($addressRows)->pluck('id')->all());

        $this->postJson('/api/whatsapp/webhook', $this->interactiveListReplyPayload(
            'wamid.inbound.address-detail',
            $customer->phone,
            'show_address:addr-2',
            '700002 Howrah'
        ))->assertOk();

        $addressDetailMessage = \App\Models\Message::query()
            ->where('direction', 'outbound')
            ->where('body', 'like', '%88 River Road%')
            ->latest('id')
            ->firstOrFail();

        $this->assertStringContainsString('City: Howrah', $addressDetailMessage->body);
        $this->assertStringContainsString('This address is within our delivery area.', $addressDetailMessage->body);
    }

    protected function textPayload(string $messageId, string $from, string $body): array
    {
        return [
            'entry' => [[
                'id' => 'entry-'.$messageId,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Riya Sharma',
                            ],
                        ]],
                        'messages' => [[
                            'id' => $messageId,
                            'from' => $from,
                            'type' => 'text',
                            'text' => [
                                'body' => $body,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    protected function interactiveListReplyPayload(string $messageId, string $from, string $id, string $title): array
    {
        return [
            'entry' => [[
                'id' => 'entry-'.$messageId,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => '1234567890',
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Riya Sharma',
                            ],
                        ]],
                        'messages' => [[
                            'id' => $messageId,
                            'from' => $from,
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'list_reply',
                                'list_reply' => [
                                    'id' => $id,
                                    'title' => $title,
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
