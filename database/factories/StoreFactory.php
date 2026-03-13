<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Store';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'support_phone' => fake()->e164PhoneNumber(),
            'description' => fake()->sentence(),
            'currency' => 'USD',
            'whatsapp_phone_number_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'whatsapp_business_account_id' => (string) fake()->numberBetween(1000000, 9999999),
            'settings' => [
                'bot_enabled' => true,
            ],
            'is_active' => true,
        ];
    }
}
