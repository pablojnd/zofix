<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\Products\ProductMetrics;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\ProductStatsOverview;
use App\Filament\Widgets\ProductStatusChart;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ProductImporter::class)
                ->authorize('import', Product::class),
            ExportAction::make()
                ->label('Export CSV')
                ->exporter(ProductExporter::class)
                ->columnMapping(false)
                ->formats([ExportFormat::Csv])
                ->chunkSize(100)
                ->modifyQueryUsing(fn (Builder $query): Builder => ProductMetrics::scopeToCurrentTenant($query)),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ProductStatsOverview::class,
            ProductStatusChart::class,
        ];
    }
}
