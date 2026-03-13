<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'store_id' => Store::factory(),
            'product_category_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'sku' => Str::upper(Str::substr(Str::slug($name, ''), 0, 3)).'-'.fake()->numberBetween(100, 999),
            'meta_retailer_id' => 'retailer-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(),
            'image_path' => null,
            'price' => fake()->randomFloat(2, 5, 150),
            'compare_at_price' => null,
            'inventory_quantity' => fake()->numberBetween(5, 100),
            'metadata' => [
                'featured' => fake()->boolean(),
            ],
            'is_active' => true,
        ];
    }
}
