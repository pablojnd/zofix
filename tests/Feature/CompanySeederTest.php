<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\CompanySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CompanySeeder::class);
});

it('seeds exactly two users, three companies and five warehouses', function (): void {
    expect(User::count())->toBe(2)
        ->and(Company::count())->toBe(3)
        ->and(Warehouse::count())->toBe(5);
});

it('creates deterministic companies and warehouse codes', function (): void {
    expect(Company::orderBy('slug')->pluck('slug')->all())
        ->toBe(['acme-logistics', 'acme-retail', 'beta-foods'])
        ->and(Warehouse::orderBy('code')->pluck('code')->all())
        ->toBe(['ALN-01', 'ALS-02', 'ART-01', 'BFC-01', 'BFM-02']);
});

it('distributes tenants and warehouses per owner', function (): void {
    $alpha = User::where('email', 'alpha@example.com')->firstOrFail();
    $beta = User::where('email', 'beta@example.com')->firstOrFail();

    expect($alpha->companies()->count())->toBe(2)
        ->and($alpha->companies()->withCount('warehouses')->get()->sum('warehouses_count'))->toBe(3)
        ->and($beta->companies()->count())->toBe(1)
        ->and($beta->companies()->withCount('warehouses')->get()->sum('warehouses_count'))->toBe(2);
});

it('is idempotent and never duplicates rows when run twice', function (): void {
    $this->seed(CompanySeeder::class);

    expect(User::count())->toBe(2)
        ->and(Company::count())->toBe(3)
        ->and(Warehouse::count())->toBe(5)
        ->and(DB::table('company_user')->count())->toBe(3);
});

it('attaches every warehouse to a company owned by exactly one seeded user', function (): void {
    foreach (Warehouse::with('company.users')->get() as $warehouse) {
        expect($warehouse->company->users)->toHaveCount(1);
    }
});

it('keeps each users visibility scoped to their own companies and warehouses', function (): void {
    $alpha = User::where('email', 'alpha@example.com')->firstOrFail();
    $beta = User::where('email', 'beta@example.com')->firstOrFail();

    $alphaWarehouseIds = $alpha->companies()->with('warehouses')->get()
        ->flatMap(fn (Company $company) => $company->warehouses->pluck('id'))->all();
    $betaWarehouseIds = $beta->companies()->with('warehouses')->get()
        ->flatMap(fn (Company $company) => $company->warehouses->pluck('id'))->all();

    expect($alpha->companies->pluck('slug')->sort()->values()->all())
        ->toBe(['acme-logistics', 'acme-retail'])
        ->and($beta->companies->pluck('slug')->all())->toBe(['beta-foods'])
        ->and(count($alphaWarehouseIds))->toBe(3)
        ->and(count($betaWarehouseIds))->toBe(2)
        ->and(array_intersect($alphaWarehouseIds, $betaWarehouseIds))->toBe([]);
});

it('resolves tenants through the filament panel tenancy wiring', function (): void {
    $alpha = User::where('email', 'alpha@example.com')->firstOrFail();
    $beta = User::where('email', 'beta@example.com')->firstOrFail();

    $panel = Filament::getPanel('admin');
    $tenant = $panel->getTenant('acme-logistics');

    expect($tenant)->toBeInstanceOf(Company::class)
        ->and($tenant->slug)->toBe('acme-logistics')
        ->and($alpha->canAccessTenant($tenant))->toBeTrue()
        ->and($beta->canAccessTenant($tenant))->toBeFalse()
        ->and($alpha->getTenants($panel))->toHaveCount(2)
        ->and($beta->getTenants($panel))->toHaveCount(1);
});
