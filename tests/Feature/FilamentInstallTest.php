<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates all domain tables with consistent naming', function (): void {
    $tables = [
        'companies', 'warehouses', 'sve_tokens', 'sve_unit_of_measurements', 'sve_tariff_codes',
        'providers', 'brands', 'categories', 'attributes', 'attribute_values',
        'products', 'product_category', 'attribute_product', 'company_sku_settings',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    // The old singular name must no longer exist.
    expect(Schema::hasTable('sve_tariff_code'))->toBeFalse();
});

it('serves the filament admin panel to authenticated users', function (): void {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    // Panel tenancy requires the user to belong to at least one tenant.
    $user->companies()->attach($company);

    $this->actingAs($user)->followingRedirects()->get('/admin')->assertOk();
});

it('rolls back the new migrations cleanly', function (): void {
    Artisan::call('migrate:rollback');

    foreach (['attribute_product', 'product_category', 'products', 'sve_tariff_codes', 'categories'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
});
