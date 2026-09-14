<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
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
            // Keeps the brand's warehouse inside the same tenant company.
            'warehouse_id' => fn (array $attributes): string => Warehouse::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'name' => fake()->company(),
        ];
    }
}
