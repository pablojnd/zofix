<?php

namespace App\Filament\Widgets;

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Product Metrics';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $counts = ProductMetrics::statusCounts();
        $total = array_sum($counts);

        return [
            Stat::make('Total Products', $total)
                ->description('Products in the current company')
                ->color('primary'),
            Stat::make('Active', $counts[ProductStatus::ACTIVE->value] ?? 0)
                ->description('Available products')
                ->color('success'),
            Stat::make('Inactive', $counts[ProductStatus::INACTIVE->value] ?? 0)
                ->description('Products not currently available')
                ->color('warning'),
            Stat::make('Discontinued', $counts[ProductStatus::DISCONTINUED->value] ?? 0)
                ->description('Products no longer sold')
                ->color('danger'),
            Stat::make('Updated in 7 days', ProductMetrics::updatedSince(now()->subDays(7)))
                ->description('Recently maintained records')
                ->color('info'),
        ];
    }
}
