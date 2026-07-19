<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'sku' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'name' => $this->faker->sentence(10),
            'price_type' => 'USD',
            'price' => $this->faker->randomFloat(2, 1, 100),
            'shopify_product_id' => $this->faker->unique()->numberBetween(100000000, 999999999),
            'discount' => $this->faker->randomFloat(2, 0, 100),
            'commission' => $this->faker->randomFloat(2, 0, 100),
            'profit' => $this->faker->randomFloat(2, 0, 100),
            'total_price' => $this->faker->randomFloat(2, 0, 100),
            'comparison_price' =>  $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
