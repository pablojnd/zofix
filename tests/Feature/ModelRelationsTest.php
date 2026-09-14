<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;

it('wires tenant-consistent company → warehouse → product chains from factories', function (): void {
    $product = Product::factory()->create();

    expect($product->warehouse->company_id)->toBe($product->company_id)
        ->and($product->brand->company_id)->toBe($product->company_id)
        ->and($product->company->warehouses->pluck('id'))->toContain($product->warehouse_id)
        ->and($product->warehouse->products->pluck('id'))->toContain($product->id)
        ->and($product->company->products->pluck('id'))->toContain($product->id);
});

it('links products and categories through the tenant-scoped pivot', function (): void {
    $product = Product::factory()->withCategory()->create();
    $category = $product->categories->firstOrFail();

    expect($product->categories)->toHaveCount(1)
        ->and($category->warehouse_id)->toBe($product->warehouse_id)
        ->and($category->products->pluck('id'))->toContain($product->id)
        ->and(DB::table('product_category')
            ->where('company_id', $product->company_id)
            ->where('warehouse_id', $product->warehouse_id)
            ->count())->toBe(1);
});

it('applies casts defined on the product model', function (): void {
    $product = Product::factory()->create(['price' => 1500, 'weight' => '12.345', 'images' => ['a.jpg']]);

    expect($product->price)->toBe(1500)
        ->and($product->weight)->toBe('12.35')
        ->and($product->images)->toBe(['a.jpg']);
});

it('associates users with companies through the tenant pivot', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $user->companies()->attach($company);

    expect($user->companies->pluck('id'))->toContain($company->id)
        ->and($company->users->pluck('id'))->toContain($user->id)
        ->and($user->canAccessTenant($company))->toBeTrue();
});

it('hides soft-deleted companies from default queries but keeps them withTrashed', function (): void {
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    $company->delete();

    expect(Company::find($company->id))->toBeNull()
        ->and(Company::withTrashed()->find($company->id))->not->toBeNull()
        ->and(Company::count())->toBe(0)
        ->and(Warehouse::count())->toBe(1)
        ->and($warehouse->refresh()->company_id)->toBe($company->id);
});
