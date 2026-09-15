<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanySkuSetting;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ProductSkuGenerator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JeffersonGoncalves\Filament\BarcodeField\Forms\Components\BarcodeInput;
use LogicException;
use RuntimeException;

it('generates sequential fallback SKUs when a company has no settings', function (): void {
    $company = Company::factory()->create();

    $firstProduct = Product::factory()->create(['company_id' => $company->id]);
    $secondProduct = Product::factory()->create(['company_id' => $company->id]);

    expect($firstProduct->sku)->toBe('P000001')
        ->and($secondProduct->sku)->toBe('P000002')
        ->and($company->fresh()->skuSetting->next_sequence)->toBe(3);
});

it('respects a company prefix format and sequence length', function (): void {
    $company = Company::factory()->create();

    CompanySkuSetting::create([
        'company_id' => $company->id,
        'prefix' => 'C',
        'format' => '{prefix}-{number}',
        'sequence_length' => 4,
        'next_sequence' => 7,
    ]);

    $product = Product::factory()->create(['company_id' => $company->id]);

    expect($product->sku)->toBe('C-0007')
        ->and($company->fresh()->skuSetting->next_sequence)->toBe(8);
});

it('retries a stale sequence reservation without reusing a number', function (): void {
    $company = Company::factory()->create();
    CompanySkuSetting::create([
        'company_id' => $company->id,
        'prefix' => 'P',
        'format' => '{prefix}{number}',
        'sequence_length' => 6,
        'next_sequence' => 1,
    ]);
    $simulateReservationByAnotherWriter = true;

    DB::listen(function (QueryExecuted $query) use ($company, &$simulateReservationByAnotherWriter): void {
        if (
            $simulateReservationByAnotherWriter
            && str_starts_with(strtolower(ltrim($query->sql)), 'select')
            && str_contains($query->sql, 'company_sku_settings')
        ) {
            $simulateReservationByAnotherWriter = false;

            DB::table('company_sku_settings')
                ->where('company_id', $company->id)
                ->update(['next_sequence' => 2]);
        }
    });

    $sku = app(ProductSkuGenerator::class)->generate($company);

    expect($sku)->toBe('P000002')
        ->and($company->fresh()->skuSetting->next_sequence)->toBe(3);
});

it('accepts manual SKUs that match the company rules without reserving a sequence', function (): void {
    $company = Company::factory()->create();
    CompanySkuSetting::create([
        'company_id' => $company->id,
        'prefix' => 'C',
        'format' => '{prefix}-{number}',
        'sequence_length' => 4,
        'next_sequence' => 7,
    ]);

    $product = Product::factory()->create([
        'company_id' => $company->id,
        'sku' => 'C-0007',
    ]);

    expect($product->sku)->toBe('C-0007')
        ->and($company->fresh()->skuSetting->next_sequence)->toBe(7);
});

it('rejects manual SKUs that do not match the company rules', function (string $sku): void {
    $company = Company::factory()->create();
    CompanySkuSetting::create([
        'company_id' => $company->id,
        'prefix' => 'C',
        'format' => '{prefix}-{number}',
        'sequence_length' => 4,
        'next_sequence' => 7,
    ]);

    expect(fn (): Product => Product::factory()->create([
        'company_id' => $company->id,
        'sku' => $sku,
    ]))->toThrow(InvalidArgumentException::class, 'does not match Company');
})->with([
    'wrong prefix' => 'P-0007',
    'wrong sequence length' => 'C-007',
]);

it('uses default rules to validate a manual SKU without creating settings', function (): void {
    $company = Company::factory()->create();

    $product = Product::factory()->create([
        'company_id' => $company->id,
        'sku' => 'P123456',
    ]);

    expect($product->sku)->toBe('P123456')
        ->and(CompanySkuSetting::query()->where('company_id', $company->id)->exists())->toBeFalse();
});

it('allows the same SKU in different companies but not twice in one company', function (): void {
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();

    $firstProduct = Product::factory()->create(['company_id' => $firstCompany->id]);
    $secondProduct = Product::factory()->create(['company_id' => $secondCompany->id]);

    expect($secondProduct->sku)->toBe($firstProduct->sku);

    expect(fn (): Product => Product::factory()->create([
        'company_id' => $firstCompany->id,
        'sku' => $firstProduct->sku,
    ]))->toThrow(QueryException::class);
});

it('does not reuse an SKU after its product is soft deleted', function (): void {
    $company = Company::factory()->create();
    $product = Product::factory()->create(['company_id' => $company->id]);
    $sku = $product->sku;

    $product->delete();

    expect(fn (): Product => Product::factory()->create([
        'company_id' => $company->id,
        'sku' => $sku,
    ]))->toThrow(QueryException::class);

    expect(Product::factory()->create(['company_id' => $company->id])->sku)->toBe('P000002');
});

it('preserves the SKU when product details, categories and warehouse change', function (): void {
    $product = Product::factory()->withCategory()->create();
    $sku = $product->sku;
    $newCategory = Category::factory()->create([
        'company_id' => $product->company_id,
        'warehouse_id' => $product->warehouse_id,
    ]);
    $newWarehouse = Warehouse::factory()->create(['company_id' => $product->company_id]);

    $product->categories()->sync([
        $newCategory->id => [
            'company_id' => $product->company_id,
            'warehouse_id' => $product->warehouse_id,
        ],
    ]);
    $product->update([
        'name' => 'Updated product name',
        'warehouse_id' => $newWarehouse->id,
    ]);

    expect($product->refresh()->sku)->toBe($sku);
});

it('rejects changing an assigned SKU', function (): void {
    $product = Product::factory()->create();

    expect(fn (): bool => $product->update(['sku' => 'P999999']))
        ->toThrow(LogicException::class, 'SKU is immutable');
});

it('rejects changing a product company', function (): void {
    $product = Product::factory()->create();
    $otherCompany = Company::factory()->create();

    expect(fn (): bool => $product->update(['company_id' => $otherCompany->id]))
        ->toThrow(LogicException::class, 'company_id is immutable');
});

it('rejects exhausted sequences and unsafe formats', function (): void {
    $exhaustedCompany = Company::factory()->create();
    CompanySkuSetting::create([
        'company_id' => $exhaustedCompany->id,
        'prefix' => 'P',
        'format' => '{prefix}{number}',
        'sequence_length' => 2,
        'next_sequence' => 100,
    ]);

    expect(fn (): Product => Product::factory()->create(['company_id' => $exhaustedCompany->id]))
        ->toThrow(InvalidArgumentException::class, 'sequence is exhausted');

    $invalidFormatCompany = Company::factory()->create();
    CompanySkuSetting::create([
        'company_id' => $invalidFormatCompany->id,
        'prefix' => 'P',
        'format' => '{prefix}{unsafe}{number}',
        'sequence_length' => 6,
        'next_sequence' => 1,
    ]);

    expect(fn (): Product => Product::factory()->create(['company_id' => $invalidFormatCompany->id]))
        ->toThrow(InvalidArgumentException::class, 'exactly one {prefix} and one {number}');
});

it('rejects non-string manual SKUs without coercion', function (): void {
    $company = Company::factory()->create();

    expect(fn (): Product => Product::factory()->create([
        'company_id' => $company->id,
        'sku' => 123,
    ]))->toThrow(InvalidArgumentException::class, 'non-empty string');
});

it('rejects product creation without a company', function (): void {
    expect(fn (): Product => Product::create(['name' => 'Missing company']))
        ->toThrow(LogicException::class, 'without a company_id');
});

it('registers the barcode input and Code 128 scanner format', function (): void {
    $formSource = File::get(app_path('Filament/Resources/Products/Schemas/ProductForm.php'));

    expect(class_exists(BarcodeInput::class))->toBeTrue()
        ->and(config('filament-barcode-field.formats'))->toContain('CODE_128')
        ->and($formSource)->toContain("BarcodeInput::make('sku')")
        ->and($formSource)->toContain('->readOnly()')
        ->and($formSource)->toContain('->dehydrated(false)');
});

it('refuses to restore global SKU uniqueness when tenant duplicates exist', function (): void {
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();
    $sku = 'P123456';

    Product::factory()->create([
        'company_id' => $firstCompany->id,
        'sku' => $sku,
    ]);
    Product::factory()->create([
        'company_id' => $secondCompany->id,
        'sku' => $sku,
    ]);

    $migration = require database_path('migrations/2026_09_15_014147_scope_product_skus_by_company.php');
    if (! is_object($migration) || ! method_exists($migration, 'down')) {
        throw new LogicException('The SKU migration must expose a down method.');
    }
    $down = Closure::fromCallable([$migration, 'down']);

    expect(function () use ($down): void {
        $down();
    })
        ->toThrow(RuntimeException::class, 'Cannot restore global product SKU uniqueness');

    $indexNames = collect(Schema::getIndexes('products'))->pluck('name')->all();

    expect($indexNames)->toContain('products_company_sku_unique')
        ->not->toContain('products_sku_unique');
});
