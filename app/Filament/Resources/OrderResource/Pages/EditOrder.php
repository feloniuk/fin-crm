<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_invoice')
                ->label('Створити рахунок')
                ->icon('heroicon-o-document')
                ->visible(fn (Order $record): bool => $record->canCreateInvoice())
                ->url(fn (Order $record) => route('filament.admin.resources.invoices.create', ['order_id' => $record->id])),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
