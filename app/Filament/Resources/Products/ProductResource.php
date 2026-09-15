<?php

namespace App\Filament\Resources\Products;

use App\Enums\ProductStatus;
use App\Enums\SalesUnit;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('name')
                            ->columnSpanFull(),
                        TextEntry::make('sku')
                            ->label('SKU')
                            ->copyable(),
                        TextEntry::make('sku_provider')
                            ->label('Provider SKU')
                            ->placeholder('Not assigned'),
                        TextEntry::make('price')
                            ->money('CLP'),
                        TextEntry::make('sale_unit')
                            ->label('Sale Unit')
                            ->formatStateUsing(fn (mixed $state): string => SalesUnit::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                            ->badge(),
                        TextEntry::make('status')
                            ->formatStateUsing(fn (mixed $state): string => ProductStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                            ->color(fn (mixed $state): array|string|null => ProductStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                            ->badge(),
                        TextEntry::make('packing')
                            ->numeric(decimalPlaces: 2),
                        TextEntry::make('weight')
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' kg'),
                        TextEntry::make('height')
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' cm'),
                        TextEntry::make('width')
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' cm'),
                        TextEntry::make('length')
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' cm'),
                        ImageEntry::make('images')
                            ->label('Images')
                            ->disk('public')
                            ->visibility('public')
                            ->square()
                            ->imageSize(120)
                            ->stacked()
                            ->limit(5)
                            ->visible(fn (Product $record): bool => filled($record->images))
                            ->columnSpanFull(),
                    ]),
                Section::make('Relations')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse'),
                        TextEntry::make('brand.name')
                            ->label('Brand'),
                        TextEntry::make('provider_id')
                            ->label('Provider ID')
                            ->placeholder('Not assigned'),
                        TextEntry::make('sve_unit_of_measurement_id')
                            ->label('SVE Unit of Measurement ID')
                            ->placeholder('Not assigned'),
                        TextEntry::make('sve_tariff_code_id')
                            ->label('SVE Tariff Code ID')
                            ->placeholder('Not assigned'),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ])
            ->columns(3);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
