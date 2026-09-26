<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'order_number' => 'TEST-'.fake()->unique()->numerify('######'),
            'item_name' => fake()->words(3, true),
            'total_amount' => sprintf('%d.%02d', fake()->numberBetween(5, 500), fake()->numberBetween(0, 99)),
            'ordered_at' => now()->subDays(fake()->numberBetween(1, 25)),
            'final_sale' => false,
        ];
    }
}
