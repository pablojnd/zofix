<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->columns(5)
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku_provider')
                            ->maxLength(255),
                        TextInput::make('price')
                            ->numeric()
                            ->default(0),
                        TextInput::make('packing')
                            ->numeric()
                            ->default(0),
                        TextInput::make('weight')
                            ->numeric()
                            ->default(0),
                        TextInput::make('height')
                            ->numeric()
                            ->default(0),
                        TextInput::make('width')
                            ->numeric()
                            ->default(0),
                        TextInput::make('length')
                            ->numeric()
                            ->default(0),
                        TextInput::make('sale_unit')
                            ->default('unidad'),
                        TextInput::make('status')
                            ->default('inactive'),
                        Textarea::make('images')
                            ->nullable(),
                        Select::make('sve_unit_of_measurement_id')

                            ->nullable(),
                        Select::make('sve_tariff_code_id')
                            ->nullable(),
                    ]),
                Section::make('Relations')
                    ->columns(2)
                    ->columnSpan(1)
                    ->schema([
                        Select::make('company_id')
                            ->required(),
                        Select::make('warehouse_id')
                            ->required(),
                        Select::make('brand_id')
                            ->nullable(),
                        Select::make('provider_id')
                            ->nullable(),
                    ]),
            ])->columns(3);
    }
}
