<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\SalesUnit;
use App\Models\Brand;
use App\Models\Company;
use App\Models\SveParameter\SveTariffCode;
use App\Models\SveParameter\SveUnitOfMeasurement;
use App\Models\Warehouse;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Exists;
use JeffersonGoncalves\Filament\BarcodeField\Forms\Components\BarcodeInput;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 4,
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'xl' => 2,
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        BarcodeInput::make('sku')
                            ->label('SKU / Barcode')
                            ->readOnly()
                            ->dehydrated(false)
                            ->columnSpan(2),
                        TextInput::make('sku_provider')
                            ->label('Provider SKU')
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('CLP'),
                        TextInput::make('packing')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('weight')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('kg')
                            ->default(0),
                        TextInput::make('height')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('cm')
                            ->default(0),
                        TextInput::make('width')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('cm')
                            ->default(0),
                        TextInput::make('length')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('cm')
                            ->default(0),
                        Select::make('sale_unit')
                            ->options(SalesUnit::class)
                            ->required()
                            ->default(SalesUnit::Unit->value),
                        Select::make('status')
                            ->options(ProductStatus::class)
                            ->required()
                            ->default(ProductStatus::INACTIVE->value),
                        FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->columnSpanFull(),
                        Select::make('sve_unit_of_measurement_id')
                            ->label('SVE Unit of Measurement')
                            ->options(fn (): array => self::sveUnitOptions())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->scopedExists(
                                SveUnitOfMeasurement::class,
                                modifyQueryUsing: static fn (Builder $query): Builder => $query
                                    ->where('is_active', true)
                                    ->whereNull('deleted_at'),
                            ),
                        Select::make('sve_tariff_code_id')
                            ->label('SVE Tariff Code')
                            ->options(fn (Get $get): array => self::sveTariffCodeOptions($get('sve_unit_of_measurement_id')))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $unitId = $get('sve_unit_of_measurement_id');

                                    if (
                                        filled($value)
                                        && filled($unitId)
                                        && ! SveTariffCode::query()
                                            ->whereKey($value)
                                            ->where('sve_unit_of_measurement_id', $unitId)
                                            ->exists()
                                    ) {
                                        $fail('The selected SVE tariff code must belong to the selected SVE unit of measurement.');
                                    }
                                },
                            ])
                            ->scopedExists(
                                SveTariffCode::class,
                                modifyQueryUsing: static fn (Builder $query): Builder => $query
                                    ->where('is_active', true)
                                    ->whereNull('deleted_at'),
                            ),
                    ]),
                Section::make('Relations')
                    ->columns(1)
                    ->columnSpan([
                        'default' => 1,
                        'xl' => 1,
                    ])
                    ->schema([
                        Hidden::make('company_id')
                            ->dehydrated(false),
                        Select::make('warehouse_id')
                            ->relationship(
                                name: 'warehouse',
                                titleAttribute: 'name',
                                modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->scopedExists(
                                Warehouse::class,
                                modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                            )
                            ->required(),
                        Select::make('brand_id')
                            ->relationship(
                                name: 'brand',
                                titleAttribute: 'name',
                                modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                            )
                            ->searchable()
                            ->preload()
                            ->scopedExists(
                                Brand::class,
                                modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                            )
                            ->required(),
                        Select::make('provider_id')
                            ->label('Provider')
                            ->options(function (Get $get): array {
                                $tenant = Filament::getTenant();

                                if (! $tenant instanceof Company || blank($tenant->getKey())) {
                                    return [];
                                }

                                return DB::table('providers')
                                    ->where('company_id', $tenant->getKey())
                                    ->whereNull('deleted_at')
                                    ->when(
                                        filled($warehouseId = $get('warehouse_id')),
                                        fn (QueryBuilder $query): QueryBuilder => $query->where('warehouse_id', $warehouseId),
                                    )
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->exists(
                                table: 'providers',
                                column: 'id',
                                modifyRuleUsing: static function (Exists $rule): Exists {
                                    $tenant = Filament::getTenant();

                                    if (! $tenant instanceof Company || blank($tenant->getKey())) {
                                        return $rule->where('company_id', '__no_current_company__');
                                    }

                                    return $rule
                                        ->where('company_id', $tenant->getKey())
                                        ->whereNull('deleted_at');
                                },
                            ),
                    ]),
            ])
            ->columns(3);
    }

    /**
     * @return array<string, string>
     */
    private static function sveUnitOptions(): array
    {
        return SveUnitOfMeasurement::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(static fn (SveUnitOfMeasurement $unit): array => [
                $unit->id => "{$unit->code} — {$unit->name}",
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function sveTariffCodeOptions(?string $unitId): array
    {
        return SveTariffCode::query()
            ->where('is_active', true)
            ->when(filled($unitId), fn (Builder $query): Builder => $query->where('sve_unit_of_measurement_id', $unitId))
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(static fn (SveTariffCode $tariffCode): array => [
                $tariffCode->id => "{$tariffCode->code} — {$tariffCode->name}",
            ])
            ->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function scopeToCurrentCompany(Builder $query): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company || blank($tenant->getKey())) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $query->getModel()->qualifyColumn('company_id'),
            $tenant->getKey(),
        )->whereNull($query->getModel()->qualifyColumn('deleted_at'));
    }
}
