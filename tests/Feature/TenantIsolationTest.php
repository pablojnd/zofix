<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Facades\Filament;

it('refreshes tenant membership after a loaded relationship becomes stale', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->load('companies');
    $user->companies()->attach($company);

    expect($user->getTenants(Filament::getPanel('admin'))->modelKeys())->toBe([$company->id]);

    $user->load('companies');
    $user->companies()->detach($company);

    expect($user->getTenants(Filament::getPanel('admin')))->toBeEmpty();
    expect($user->canAccessTenant($company))->toBeFalse();
});

it('rejects a non-company model even when its key matches a membership', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $warehouse = Warehouse::factory()->create(['id' => $company->id, 'company_id' => $company->id]);

    expect($user->canAccessTenant($warehouse))->toBeFalse();
});

it('excludes soft-deleted companies from tenant discovery and access', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $user->load('companies');
    $company->delete();

    expect($user->getTenants(Filament::getPanel('admin')))->toBeEmpty();
    expect($user->canAccessTenant($company))->toBeFalse();
});

it('resolves catalog ownership and company collections across warehouses', function (string $model, string $relationship): void {
    $company = Company::factory()->create();
    $first = $model::factory()->create(['company_id' => $company->id]);
    $second = $model::factory()->create(['company_id' => $company->id]);
    $foreign = $model::factory()->create();

    expect($first->company->is($company))->toBeTrue();
    expect($first->warehouse->id)->toBe($first->warehouse_id);
    expect($company->load($relationship)->{$relationship}->modelKeys())
        ->toEqualCanonicalizing([$first->id, $second->id]);
    expect($model::with('company', 'warehouse')->findOrFail($foreign->id)->company->id)
        ->toBe($foreign->company_id);
})->with([
    'brands' => [Brand::class, 'brands'],
    'categories' => [Category::class, 'categories'],
]);

it('allows product classification across warehouses of the same company', function (): void {
    $product = Product::factory()->create();
    $category = Category::factory()->create(['company_id' => $product->company_id]);

    $product->categories()->attach($category, [
        'company_id' => $product->company_id,
        'warehouse_id' => $product->warehouse_id,
    ]);

    expect($product->brand->warehouse_id)->not->toBe($product->warehouse_id);
    expect($category->warehouse_id)->not->toBe($product->warehouse_id);
    expect($product->fresh()->categories->modelKeys())->toBe([$category->id]);
    expect($category->fresh()->products->modelKeys())->toBe([$product->id]);
});

it('keeps login available without an active tenant', function (): void {
    $this->get(route('filament.admin.auth.login'))->assertOk();
    expect(Filament::getTenant())->toBeNull();
});

it('denies admin bootstrap to a local user without company membership', function (): void {
    config(['app.env' => 'local']);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertNotFound();
    expect(Filament::getTenant())->toBeNull();
});

it('redirects local member bootstrap to an active tenant dashboard', function (): void {
    config(['app.env' => 'local']);
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)->get('/admin')
        ->assertRedirect(route('filament.admin.pages.dashboard', ['tenant' => $company->slug]));
});

it('rejects a foreign tenant slug for a local company member', function (): void {
    config(['app.env' => 'local']);
    $user = User::factory()->create();
    $user->companies()->attach(Company::factory()->create());
    $foreign = Company::factory()->create();

    $this->actingAs($user)->get(route('filament.admin.pages.dashboard', ['tenant' => $foreign->slug]))
        ->assertNotFound();
});

it('serves local member content only after identifying its tenant', function (): void {
    config(['app.env' => 'local']);
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)->get(route('filament.admin.pages.dashboard', ['tenant' => $company->slug]))
        ->assertOk();
    expect(Filament::getTenant()->is($company))->toBeTrue();
});
