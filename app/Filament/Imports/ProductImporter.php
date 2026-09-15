<?php

namespace App\Filament\Imports;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Product;
use App\Models\SveParameter\SveTariffCode;
use App\Models\SveParameter\SveUnitOfMeasurement;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('sku')
                ->label('SKU')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('sku_provider')
                ->label('Provider SKU')
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('price')
                ->integer()
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('packing')
                ->numeric(decimalPlaces: 2)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('weight')
                ->numeric(decimalPlaces: 2)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('height')
                ->numeric(decimalPlaces: 2)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('width')
                ->numeric(decimalPlaces: 2)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('length')
                ->numeric(decimalPlaces: 2)
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('sale_unit')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'in:unidad,m2,m3,pie,ml']),
            ImportColumn::make('status')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'in:active,inactive,discontinued']),
            ImportColumn::make('images')
                ->array('|')
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['nullable', 'string', 'max:2048']),
            ImportColumn::make('warehouse_id')
                ->label('Warehouse ID')
                ->relationship('warehouse')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string']),
            ImportColumn::make('brand_id')
                ->label('Brand ID')
                ->relationship('brand')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string']),
            ImportColumn::make('provider_id')
                ->label('Provider ID')
                ->rules(['nullable', 'string']),
            ImportColumn::make('sve_unit_of_measurement_id')
                ->label('SVE Unit of Measurement ID')
                ->rules(['nullable', 'string']),
            ImportColumn::make('sve_tariff_code_id')
                ->label('SVE Tariff Code ID')
                ->rules(['nullable', 'string']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Hidden::make('company_id')
                ->default(fn (): ?string => self::currentTenantId())
                ->required(),
            Checkbox::make('update_existing')
                ->label('Update existing products matching the SKU')
                ->default(false),
        ];
    }

    public function resolveRecord(): ?Product
    {
        $tenantId = $this->tenantId();
        $sku = $this->getData()['sku'] ?? null;

        if (! is_string($sku) || trim($sku) === '') {
            return new Product;
        }

        $record = Product::query()
            ->where('company_id', $tenantId)
            ->where('sku', trim($sku))
            ->first();

        if (! $record) {
            return new Product;
        }

        if (! (bool) ($this->getOptions()['update_existing'] ?? false)) {
            throw ValidationException::withMessages([
                'sku' => 'A product with this SKU already exists. Enable updating existing products to continue.',
            ]);
        }

        return $record;
    }

    protected function beforeValidate(): void
    {
        $data = $this->getData();
        $tenantId = $this->tenantId();
        $errors = [];

        if ($this->hasInvalidTenantReference($data, 'warehouse_id', Warehouse::class, $tenantId)) {
            $errors['warehouse_id'] = 'The warehouse must belong to the current company.';
        }

        if ($this->hasInvalidTenantReference($data, 'brand_id', Brand::class, $tenantId)) {
            $errors['brand_id'] = 'The brand must belong to the current company.';
        }

        $providerId = $data['provider_id'] ?? null;
        if ($this->isColumnMapped('provider_id') && filled($providerId)) {
            $providerQuery = DB::table('providers')
                ->where('id', $providerId)
                ->where('company_id', $tenantId)
                ->whereNull('deleted_at');

            if (filled($warehouseId = $data['warehouse_id'] ?? $this->getRecord()?->warehouse_id)) {
                $providerQuery->where('warehouse_id', $warehouseId);
            }

            if (! $providerQuery->exists()) {
                $errors['provider_id'] = 'The provider must belong to the current company and warehouse.';
            }
        }

        $unitId = $data['sve_unit_of_measurement_id'] ?? null;
        if ($this->isColumnMapped('sve_unit_of_measurement_id') && $this->hasInvalidActiveReference($unitId, SveUnitOfMeasurement::class)) {
            $errors['sve_unit_of_measurement_id'] = 'The SVE unit must be active and valid.';
        }

        $tariffCodeId = $data['sve_tariff_code_id'] ?? null;
        if ($this->isColumnMapped('sve_tariff_code_id') && $this->hasInvalidActiveReference($tariffCodeId, SveTariffCode::class)) {
            $errors['sve_tariff_code_id'] = 'The SVE tariff code must be active and valid.';
        }

        if (
            $this->isColumnMapped('sve_tariff_code_id')
            && $this->isColumnMapped('sve_unit_of_measurement_id')
            && filled($tariffCodeId)
            && filled($unitId)
        ) {
            $matchesUnit = SveTariffCode::query()
                ->whereKey($tariffCodeId)
                ->where('sve_unit_of_measurement_id', $unitId)
                ->exists();

            if (! $matchesUnit) {
                $errors['sve_tariff_code_id'] = 'The SVE tariff code must match the selected unit.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        $tenantId = $this->tenantId();

        if (! $record instanceof Product || ($record->exists && $record->company_id !== $tenantId)) {
            throw new AccessDeniedHttpException('The product does not belong to the current company.');
        }

        if (! $record->exists) {
            $record->company_id = $tenantId;
        }
    }

    private static function currentTenantId(): ?string
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Company && is_string($tenant->getKey())
            ? $tenant->getKey()
            : null;
    }

    private function tenantId(): string
    {
        $optionTenantId = $this->getOptions()['company_id'] ?? null;
        $currentTenantId = self::currentTenantId();
        $tenantId = $currentTenantId ?? (is_string($optionTenantId) ? $optionTenantId : null);

        if (! is_string($tenantId) || trim($tenantId) === '') {
            throw new AccessDeniedHttpException('A company tenant is required for product imports.');
        }

        if ($currentTenantId !== null && $optionTenantId !== null && $optionTenantId !== $currentTenantId) {
            throw new AccessDeniedHttpException('The import tenant does not match the current company.');
        }

        $user = Auth::user();
        $tenant = Company::query()->find($tenantId);

        if (! $user instanceof User || ! $tenant || ! $user->canAccessTenant($tenant)) {
            throw new AccessDeniedHttpException('The authenticated user cannot import products for this company.');
        }

        return $tenantId;
    }

    /**
     * @param  class-string<Warehouse|Brand>  $model
     * @param  array<string, mixed>  $data
     */
    private function hasInvalidTenantReference(array $data, string $attribute, string $model, string $tenantId): bool
    {
        if (! $this->isColumnMapped($attribute)) {
            return false;
        }

        $value = $data[$attribute] ?? null;

        if (blank($value)) {
            return true;
        }

        return ! $model::query()
            ->whereKey($value)
            ->where('company_id', $tenantId)
            ->exists();
    }

    private function isColumnMapped(string $attribute): bool
    {
        return filled($this->columnMap[$attribute] ?? null);
    }

    /**
     * @param  class-string<SveUnitOfMeasurement|SveTariffCode>  $model
     */
    private function hasInvalidActiveReference(mixed $value, string $model): bool
    {
        if (blank($value)) {
            return false;
        }

        return ! $model::query()
            ->whereKey($value)
            ->where('is_active', true)
            ->exists();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
