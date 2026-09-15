<?php

namespace App\Filament\Exports;

use App\Filament\Resources\Products\ProductMetrics;
use App\Models\Product;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->preventFormulaInjection(),
            ExportColumn::make('sku')
                ->label('SKU')
                ->preventFormulaInjection(),
            ExportColumn::make('sku_provider')
                ->label('Provider SKU')
                ->preventFormulaInjection(),
            ExportColumn::make('price')
                ->preventFormulaInjection(),
            ExportColumn::make('packing')
                ->preventFormulaInjection(),
            ExportColumn::make('weight')
                ->preventFormulaInjection(),
            ExportColumn::make('height')
                ->preventFormulaInjection(),
            ExportColumn::make('width')
                ->preventFormulaInjection(),
            ExportColumn::make('length')
                ->preventFormulaInjection(),
            ExportColumn::make('sale_unit')
                ->label('Sale Unit')
                ->preventFormulaInjection(),
            ExportColumn::make('status')
                ->preventFormulaInjection(),
            ExportColumn::make('images')
                ->formatStateUsing(static function (mixed $state): string {
                    if (! is_array($state)) {
                        return (string) $state;
                    }

                    return implode('|', array_map(static fn (mixed $image): string => (string) $image, $state));
                })
                ->preventFormulaInjection(),
            ExportColumn::make('warehouse_id')
                ->label('Warehouse ID')
                ->preventFormulaInjection(),
            ExportColumn::make('brand_id')
                ->label('Brand ID')
                ->preventFormulaInjection(),
            ExportColumn::make('provider_id')
                ->label('Provider ID')
                ->preventFormulaInjection(),
            ExportColumn::make('sve_unit_of_measurement_id')
                ->label('SVE Unit of Measurement ID')
                ->preventFormulaInjection(),
            ExportColumn::make('sve_tariff_code_id')
                ->label('SVE Tariff Code ID')
                ->preventFormulaInjection(),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return ProductMetrics::scopeToCurrentTenant($query)
            ->with(['warehouse', 'brand']);
    }

    public function getFileName(Export $export): string
    {
        return 'products-'.now()->format('Y-m-d').'-'.$export->getKey().'.csv';
    }

    public function getFormats(): array
    {
        return [ExportFormat::Csv];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product export has completed and '.Str::of('row')->counted($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Str::of('row')->counted($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
