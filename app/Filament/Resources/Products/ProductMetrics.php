<?php

namespace App\Filament\Resources\Products;

use App\Models\Company;
use App\Models\Product;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ProductMetrics
{
    /**
     * @return Builder<Product>
     */
    public static function query(): Builder
    {
        return self::scopeToCurrentTenant(Product::query());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToCurrentTenant(Builder $query): Builder
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company || blank($tenant->getKey())) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $query->getModel()->qualifyColumn('company_id'),
            $tenant->getKey(),
        );
    }

    /**
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        return self::query()
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    public static function updatedSince(Carbon $since): int
    {
        return self::query()
            ->where('updated_at', '>=', $since)
            ->count();
    }
}
