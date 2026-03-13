<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Commerce';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'plan' => fake()->randomElement(['starter', 'growth', 'scale']),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'settings' => [
                'channels' => ['whatsapp'],
            ],
            'is_active' => true,
        ];
    }
}
