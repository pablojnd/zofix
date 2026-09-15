<?php

namespace App\Models;

use App\Services\ProductSkuGenerator;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use LogicException;

#[Fillable([
    'company_id', 'warehouse_id', 'brand_id', 'provider_id', 'name', 'sku', 'sku_provider',
    'price', 'packing', 'weight', 'height', 'width', 'length', 'sale_unit', 'status',
    'images', 'sve_unit_of_measurement_id', 'sve_tariff_code_id',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $companyId = $product->company_id;

            if (! is_string($companyId) || trim($companyId) === '') {
                throw new LogicException('A Product cannot be created without a company_id.');
            }

            if ($product->sku === null || (is_string($product->sku) && trim($product->sku) === '')) {
                $product->sku = app(ProductSkuGenerator::class)->generate($companyId);

                return;
            }

            if (! is_string($product->sku)) {
                self::assertValidSku($product->sku);
            }

            app(ProductSkuGenerator::class)->validateExistingSku($product->sku, $companyId);
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('company_id')) {
                throw new LogicException('Product company_id is immutable after creation.');
            }

            if (! $product->isDirty('sku')) {
                return;
            }

            $originalSku = $product->getRawOriginal('sku');

            if (is_string($originalSku) && trim($originalSku) !== '') {
                throw new LogicException('Product SKU is immutable after creation.');
            }

            self::assertValidSku($product->sku);
        });
    }

    private static function assertValidSku(mixed $sku): void
    {
        if (! is_string($sku) || trim($sku) === '') {
            throw new InvalidArgumentException('Product SKU must be a non-empty string.');
        }

        if (mb_strlen($sku) > 255) {
            throw new InvalidArgumentException('Product SKU must be 255 characters or fewer.');
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Categories are attached through the tenant-scoped product_category pivot,
     * which stores NOT NULL company_id/warehouse_id columns.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category')
            ->withPivot(['company_id', 'warehouse_id'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'packing' => 'decimal:2',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'width' => 'decimal:2',
            'length' => 'decimal:2',
            'images' => 'array',
        ];
    }
}
