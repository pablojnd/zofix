<?php

namespace App\Enums;

use App\Exceptions\InvalidSaleQuantityException;
use App\Models\Product;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Units a product can be sold in. Glass sheets, sized glass, tiles,
 * aluminum strips, grass, etc.
 *
 * Dimension-based units (m2, m3, ml, pie) are computed from the
 * product's physical dimensions (height, width, length — in cm) or
 * from an explicit per-order measure (in meters) supplied at sale
 * time — e.g. sized glass cut to 1.20 x 0.60 m.
 *
 * Quantity semantics: `quantity()` receives the number of pieces
 * ordered and returns the total saleable amount in this unit:
 *  - unidad:  4 sheets            -> 4
 *  - m2:      5 sheets of 2.10x1.20 m -> 5 * 2.52 = 12.60 m2
 *  - m3:      2 blocks of 0.50x0.20x0.30 m -> 2 * 0.03 = 0.06 m3
 *  - ml:      3 strips of 6 m     -> 18.00 ml
 *  - pie:     3 strips of 2.40 m  -> 3 * 7.874 = 23.62 pie (1 pie = 30.48 cm)
 */
enum SalesUnit: string implements HasColor, HasIcon, HasLabel
{
    case Unit = 'unidad';
    case SquareMeter = 'm2';
    case CubicMeter = 'm3';
    case Foot = 'pie';
    case LinearMeter = 'ml';

    /** 1 foot = 30.48 cm of linear measure. */
    public const CM_PER_FOOT = 30.48;

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Unit => 'Unidad',
            self::SquareMeter => 'm²',
            self::CubicMeter => 'm³',
            self::Foot => 'Pie',
            self::LinearMeter => 'm lineal',
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null|Heroicon
    {
        return match ($this) {
            self::Unit => Heroicon::OutlinedCube,
            self::SquareMeter => Heroicon::OutlinedSquares2x2,
            self::CubicMeter => Heroicon::OutlinedCubeTransparent,
            self::Foot => Heroicon::OutlinedChartPie,
            self::LinearMeter => Heroicon::OutlinedArrowsRightLeft,
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::Unit => 'gray',
            self::SquareMeter => 'primary',
            self::CubicMeter => 'info',
            self::Foot => 'warning',
            self::LinearMeter => 'success',
        };
    }

    // /**
    //  * Total saleable quantity for the ordered pieces, in this unit.
    //  *
    //  * @param  array{length?: float, width?: float, height?: float}|null  $measure  Per-order measure in meters (overrides product dimensions).
    //  *
    //  * @throws InvalidSaleQuantityException when the unit requires dimensions that are not available
    //  */
    // public function quantity(Product $product, float $pieces, ?array $measure = null): float
    // {
    //     return match ($this) {
    //         self::Unit => $pieces,
    //         self::Foot => round($pieces * $this->feetPerPiece($product, $measure), 2),
    //         self::LinearMeter => round($pieces * $this->metersPerPiece($product, $measure, 'length'), 2),
    //         self::SquareMeter => round($pieces * $this->squareMetersPerPiece($product, $measure), 2),
    //         self::CubicMeter => round($pieces * $this->cubicMetersPerPiece($product, $measure), 2),
    //     };
    // }

    // /**
    //  * Price for the computed quantity. The unit price is always per
    //  * sale unit.
    //  */
    // public function price(Product $product, float $quantity): float
    // {
    //     return round($product->price * $quantity, 2);
    // }

    // /**
    //  * Per-piece linear feet: explicit length (m) beats product length (cm).
    //  */
    // public function feetPerPiece(Product $product, ?array $measure = null): float
    // {
    //     $meters = $measure['length'] ?? $product->length / 100;

    //     if ($meters <= 0) {
    //         throw new InvalidSaleQuantityException(
    //             "Product [{$product->sku}] is sold by {$this->getLabel()} but has no configured length."
    //         );
    //     }

    //     return $meters * 100 / self::CM_PER_FOOT;
    // }

    // /**
    //  * Per-piece measure in meters for a single dimension.
    //  */
    // public function metersPerPiece(Product $product, ?array $measure, string $dimension): float
    // {
    //     $meters = $measure[$dimension] ?? $product->{$dimension} / 100;

    //     if ($meters <= 0) {
    //         throw new InvalidSaleQuantityException(
    //             "Product [{$product->sku}] is sold by {$this->getLabel()} but has no configured {$dimension}."
    //         );
    //     }

    //     return $meters;
    // }

    // /**
    //  * Per-piece area in m2: explicit length/width (m) beats product
    //  * dimensions (cm).
    //  */
    // public function squareMetersPerPiece(Product $product, ?array $measure = null): float
    // {
    //     $length = $measure['length'] ?? $product->length / 100;
    //     $width = $measure['width'] ?? $product->width / 100;

    //     if ($length <= 0 || $width <= 0) {
    //         throw new InvalidSaleQuantityException(
    //             "Product [{$product->sku}] is sold by {$this->getLabel()} but has no configured dimensions."
    //         );
    //     }

    //     return $length * $width;
    // }

    // /**
    //  * Per-piece volume in m3: explicit length/width/height (m) beats
    //  * product dimensions (cm).
    //  */
    // public function cubicMetersPerPiece(Product $product, ?array $measure = null): float
    // {
    //     $length = $measure['length'] ?? $product->length / 100;
    //     $width = $measure['width'] ?? $product->width / 100;
    //     $height = $measure['height'] ?? $product->height / 100;

    //     if ($length <= 0 || $width <= 0 || $height <= 0) {
    //         throw new InvalidSaleQuantityException(
    //             "Product [{$product->sku}] is sold by {$this->getLabel()} but has no configured dimensions."
    //         );
    //     }

    //     return $length * $width * $height;
    // }
}
