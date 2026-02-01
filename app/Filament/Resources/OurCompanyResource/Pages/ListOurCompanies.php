<?php
namespace App\Filament\Resources\OurCompanyResource\Pages;
use App\Filament\Resources\OurCompanyResource;
use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;
class ListOurCompanies extends BaseListRecords
{
    protected static string $resource = OurCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
