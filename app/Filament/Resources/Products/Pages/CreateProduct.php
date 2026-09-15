<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Company;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Ensure the product is always assigned to the active Filament Company.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company || ! is_string($tenant->getKey())) {
            throw new LogicException('A current Company tenant is required to create a Product.');
        }

        $data['company_id'] = $tenant->getKey();

        return $data;
    }
}
