<?php

namespace App\Filament\Widgets;

use App\Enums\ProductStatus;
use App\Filament\Resources\Products\ProductMetrics;
use Filament\Widgets\ChartWidget;

class ProductStatusChart extends ChartWidget
{
    protected ?string $heading = 'Products by Status';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $counts = ProductMetrics::statusCounts();
        $statusValues = [
            ProductStatus::ACTIVE->value,
            ProductStatus::INACTIVE->value,
            ProductStatus::DISCONTINUED->value,
        ];

        if (array_sum($counts) === 0) {
            return [];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Products',
                    'data' => array_map(
                        fn (string $status): int => $counts[$status] ?? 0,
                        $statusValues,
                    ),
                    'backgroundColor' => ['#22c55e', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => array_map(
                fn (string $status): string => ProductStatus::from($status)->getLabel(),
                $statusValues,
            ),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
