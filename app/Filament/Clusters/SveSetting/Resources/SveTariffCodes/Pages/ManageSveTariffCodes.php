<?php

namespace App\Filament\Clusters\SveSetting\Resources\SveTariffCodes\Pages;

use App\Filament\Clusters\SveSetting\Resources\SveTariffCodes\SveTariffCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSveTariffCodes extends ManageRecords
{
    protected static string $resource = SveTariffCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
