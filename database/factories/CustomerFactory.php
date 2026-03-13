<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $phone = fake()->e164PhoneNumber();

        return [
            'store_id' => Store::factory(),
            'name' => fake()->name(),
            'phone' => $phone,
            'whatsapp_id' => $phone,
            'preferred_language' => 'en',
            'last_message_at' => now(),
            'metadata' => null,
        ];
    }
}
