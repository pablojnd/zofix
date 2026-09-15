<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
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
            // Warehouse and brand are kept inside the product's tenant company.
            'warehouse_id' => fn (array $attributes): string => Warehouse::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'brand_id' => fn (array $attributes): string => Brand::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->id,
            'provider_id' => null,
            'name' => fake()->words(3, true),
            'sku_provider' => null,
            'price' => fake()->numberBetween(100, 100_000),
            'packing' => fake()->randomFloat(2, 1, 100),
            'weight' => fake()->randomFloat(2, 0.1, 50),
            'height' => fake()->randomFloat(2, 1, 200),
            'width' => fake()->randomFloat(2, 1, 200),
            'length' => fake()->randomFloat(2, 1, 200),
            'sale_unit' => 'unidad',
            'status' => 'active',
            'images' => null,
        ];
    }

    /**
     * Attach one category that shares the product's company and warehouse.
     */
    public function withCategory(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $category = Category::factory()->create([
                'company_id' => $product->company_id,
                'warehouse_id' => $product->warehouse_id,
            ]);

            $product->categories()->attach($category->id, [
                'company_id' => $product->company_id,
                'warehouse_id' => $product->warehouse_id,
            ]);
        });
    }
}
