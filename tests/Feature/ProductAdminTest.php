<?php

use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\ProductMetrics;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

afterEach(function (): void {
    Filament::setTenant(null, true);
});

it('scopes product metrics to the current company and fails closed without a tenant', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();

    Product::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);
    Product::factory()->create([
        'company_id' => $company->id,
        'status' => 'inactive',
    ]);
    Product::factory()->create([
        'company_id' => $otherCompany->id,
        'status' => 'active',
    ]);

    Filament::setTenant($company, true);

    expect(ProductMetrics::statusCounts())->toBe([
        'active' => 1,
        'inactive' => 1,
    ])
        ->and(ProductMetrics::query()->count())->toBe(2);

    Filament::setTenant(null, true);

    expect(ProductMetrics::statusCounts())->toBe([])
        ->and(ProductMetrics::query()->count())->toBe(0);
});

it('registers the view page and a fixed formula-safe CSV export', function (): void {
    $columns = ProductExporter::getColumns();
    $exporter = new ProductExporter(new Export, [], []);

    expect(ProductResource::getPages())
        ->toHaveKey('view')
        ->and(ProductResource::getPages()['view']->getPage())->toBe(ViewProduct::class)
        ->and($exporter->getFormats())->toBe([ExportFormat::Csv])
        ->and(array_map(static fn ($column): string => $column->getName(), $columns))
        ->toContain('name', 'sku', 'warehouse_id', 'brand_id', 'images')
        ->and(collect($columns)->every(static fn ($column): bool => $column->shouldPreventFormulaInjection()))
        ->toBeTrue()
        ->and(ProductImporter::shouldPreventFormulaInjection())->toBeTrue();
});

it('imports a new product only for an authenticated company member', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $brand = $company->brands()->create([
        'warehouse_id' => $warehouse->id,
        'name' => 'Tenant Brand',
    ]);

    $this->actingAs($user);
    Filament::setTenant($company, true);

    $importer = new ProductImporter(
        new Import,
        [
            'name' => 'name',
            'sku' => 'sku',
            'sale_unit' => 'sale_unit',
            'status' => 'status',
            'warehouse_id' => 'warehouse_id',
            'brand_id' => 'brand_id',
        ],
        [
            'company_id' => $company->id,
            'update_existing' => false,
        ],
    );

    $importer([
        'name' => 'Imported product',
        'sku' => '',
        'sale_unit' => 'unidad',
        'status' => 'active',
        'warehouse_id' => $warehouse->id,
        'brand_id' => $brand->id,
    ]);

    expect(Product::query()->where('company_id', $company->id)->where('name', 'Imported product')->exists())->toBeTrue();
});

it('rejects an imported relation from another company', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $foreignWarehouse = Warehouse::factory()->create();
    $tenantWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $brand = $company->brands()->create([
        'warehouse_id' => $tenantWarehouse->id,
        'name' => 'Tenant Brand',
    ]);

    $this->actingAs($user);
    Filament::setTenant($company, true);

    $importer = new ProductImporter(
        new Import,
        [
            'name' => 'name',
            'sale_unit' => 'sale_unit',
            'status' => 'status',
            'warehouse_id' => 'warehouse_id',
            'brand_id' => 'brand_id',
        ],
        [
            'company_id' => $company->id,
            'update_existing' => false,
        ],
    );

    expect(function () use ($importer, $foreignWarehouse, $brand): void {
        $importer([
            'name' => 'Rejected product',
            'sale_unit' => 'unidad',
            'status' => 'active',
            'warehouse_id' => $foreignWarehouse->id,
            'brand_id' => $brand->id,
        ]);
    })->toThrow(ValidationException::class);

    expect(Product::query()->where('name', 'Rejected product')->exists())->toBeFalse();
});

it('does not overwrite duplicate SKUs unless explicitly enabled', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $existing = Product::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user);
    Filament::setTenant($company, true);

    $importer = new ProductImporter(
        new Import,
        ['name' => 'name', 'sku' => 'sku'],
        ['company_id' => $company->id, 'update_existing' => false],
    );

    expect(function () use ($importer, $existing): void {
        $importer([
            'name' => 'Unexpected overwrite',
            'sku' => $existing->sku,
        ]);
    })->toThrow(ValidationException::class);

    expect($existing->refresh()->name)->not->toBe('Unexpected overwrite');
});

it('renders the product list for an authenticated company member', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.products.index', ['tenant' => $company->slug]))
        ->assertOk();
});

it('renders the product view page for a product in the current company', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $user->companies()->attach($company);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.products.view', [
            'tenant' => $company->slug,
            'record' => $product,
        ]))
        ->assertOk();
});

it('denies panel access to users without a company outside local development', function (): void {
    config(['app.env' => 'testing']);

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});
