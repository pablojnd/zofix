<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum ProductStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONTINUED = 'discontinued';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INACTIVE => __('Inactive'),
            self::DISCONTINUED => __('Discontinued'),
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::ACTIVE => Heroicon::Pencil,
            self::INACTIVE => Heroicon::Pencil,
            self::DISCONTINUED => Heroicon::Pencil,
        };
    }

    public function getColor(): array|string|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'warning',
            self::DISCONTINUED => 'danger',
        };
    }
}
