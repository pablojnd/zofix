<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->city().' Warehouse',
            'slug' => fake()->unique()->slug(),
            'code' => fake()->unique()->bothify('WH-####'),
            'email_contact' => fake()->unique()->safeEmail(),
            'phone_contact' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }
}
