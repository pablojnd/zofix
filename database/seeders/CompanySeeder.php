<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Seeds deterministic tenant data: two users, three companies (panel tenants)
 * and five warehouses.
 *
 * Layout:
 * - alpha@example.com owns "acme-logistics" (2 warehouses) and "acme-retail" (1 warehouse).
 * - beta@example.com owns "beta-foods" (2 warehouses).
 *
 * Idempotent: every record is keyed on its unique column (email, slug,
 * company_id + code), so re-running updates in place instead of duplicating.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $alpha = User::firstOrCreate(
            ['email' => 'alpha@example.com'],
            ['name' => 'Alpha Owner', 'password' => '123456'],
        );

        $beta = User::firstOrCreate(
            ['email' => 'beta@example.com'],
            ['name' => 'Beta Owner', 'password' => '123456'],
        );

        $acmeLogistics = $this->company('acme-logistics', 'Acme Logistics', $alpha);
        $acmeRetail = $this->company('acme-retail', 'Acme Retail', $alpha);
        $betaFoods = $this->company('beta-foods', 'Beta Foods', $beta);

        $this->warehouse($acmeLogistics, 'alpha-north-dc', 'Alpha North DC', 'ALN-01');
        $this->warehouse($acmeLogistics, 'alpha-south-dc', 'Alpha South DC', 'ALS-02');
        $this->warehouse($acmeRetail, 'alpha-store', 'Alpha Store', 'ART-01');
        $this->warehouse($betaFoods, 'beta-cold-store', 'Beta Cold Store', 'BFC-01');
        $this->warehouse($betaFoods, 'beta-main-store', 'Beta Main Store', 'BFM-02');
    }

    /**
     * Create a company (tenant) unless it exists and attach its owner
     * through the company_user pivot that panel tenancy resolves against.
     */
    private function company(string $slug, string $name, User $owner): Company
    {
        $company = Company::firstOrCreate(['slug' => $slug], ['name' => $name]);

        $owner->companies()->syncWithoutDetaching([$company->id]);

        return $company;
    }

    private function warehouse(Company $company, string $slug, string $name, string $code): Warehouse
    {
        return Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            ['slug' => $slug, 'name' => $name],
        );
    }
}
