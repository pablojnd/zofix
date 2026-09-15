<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ProductSkuGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds four brands, four categories, and four products per tenant.
 *
 * Layout per company:
 * - Products cycle through the brand pool (one brand each) and through all
 *   statuses so the dashboard chart always has data for every slice.
 * - Every product is attached to one or two random categories from its own
 *   tenant through the product_category pivot.
 *
 * Idempotent: tenants that already have products are left untouched, and
 * each tenant is seeded inside a transaction (DatabaseSeeder runs with
 * WithoutModelEvents, so the SKU is generated explicitly here).
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(function (Company $company): void {
            if ($company->products()->exists()) {
                return;
            }

            DB::transaction(function () use ($company): void {
                $warehouse = $company->warehouses()->first()
                    ?? Warehouse::factory()->create(['company_id' => $company->id]);

                $brands = Brand::factory()->count(4)->create([
                    'company_id' => $company->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $categories = Category::factory()->count(4)->create([
                    'company_id' => $company->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $statuses = array_column(ProductStatus::cases(), 'value');

                $brands->values()->each(function (Brand $brand, int $index) use ($company, $warehouse, $categories, $statuses): void {
                    $product = Product::factory()->create([
                        'company_id' => $company->id,
                        'warehouse_id' => $warehouse->id,
                        'brand_id' => $brand->id,
                        'status' => $statuses[$index % count($statuses)],
                        'sku' => app(ProductSkuGenerator::class)->generate($company->id),
                    ]);

                    $product->categories()->attach(
                        $categories->random(fake()->numberBetween(1, 2))->modelKeys(),
                        ['company_id' => $company->id, 'warehouse_id' => $warehouse->id],
                    );
                });
            });
        });
    }
}
