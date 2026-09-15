<?php

namespace App\Filament\Clusters\SveSetting\Resources\SveUnitOfMeasurements\Pages;

use App\Filament\Clusters\SveSetting\Resources\SveUnitOfMeasurements\SveUnitOfMeasurementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSveUnitOfMeasurements extends ManageRecords
{
    protected static string $resource = SveUnitOfMeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
