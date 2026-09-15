<?php

namespace App\Filament\Resources\Products\Tables;

use App\Enums\ProductStatus;
use App\Enums\SalesUnit;
use App\Filament\Resources\Products\ProductMetrics;
use App\Models\Category;
use App\Models\Company;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['warehouse', 'brand']))
            ->columns([
                ImageColumn::make('images')
                    ->label('Images')
                    ->disk('public')
                    ->visibility('public')
                    ->stacked()
                    ->limit(2)
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->money('CLP')
                    ->sortable(),
                TextColumn::make('sale_unit')
                    ->label('Sale Unit')
                    ->formatStateUsing(fn (mixed $state): string => SalesUnit::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (mixed $state): string => ProductStatus::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->color(fn (mixed $state): array|string|null => ProductStatus::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->badge()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProductStatus::class),
                SelectFilter::make('sale_unit')
                    ->options(SalesUnit::class),
                SelectFilter::make('warehouse')
                    ->relationship(
                        name: 'warehouse',
                        titleAttribute: 'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('brand')
                    ->relationship(
                        name: 'brand',
                        titleAttribute: 'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => self::scopeToCurrentCompany($query),
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('categories')
                    ->relationship(
                        name: 'categories',
                        titleAttribute: 'name',
                        modifyQueryUsing: static fn (Builder $query): Builder => self::scopeCategoryToCurrentCompany($query),
                    )
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created from'),
                        DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(static function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['created_from'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $data['created_from']),
                            )
                            ->when(
                                filled($data['created_until'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $data['created_until']),
                            );
                    }),
                TrashedFilter::make(),
            ])
            ->filtersFormColumns(2)
            ->persistFiltersInSession()
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->searchDebounce('500ms')
            ->searchPlaceholder('Search products by name or SKU')
            ->defaultSort('created_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('No products found')
            ->emptyStateDescription('Create a product or adjust the active filters to see results.');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private static function scopeToCurrentCompany(Builder $query): Builder
    {
        return ProductMetrics::scopeToCurrentTenant($query);
    }

    /**
     * @return Builder<Category>
     */
    private static function scopeCategoryToCurrentCompany(Builder $query): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company || blank($tenant->getKey())) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('categories.company_id', $tenant->getKey())
            ->where('product_category.company_id', $tenant->getKey());
    }
}
