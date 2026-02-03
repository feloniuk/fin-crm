<?php
namespace App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadExcel')
                ->label('Скачати Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn () => $this->record->excel_path ? route('invoice.download-excel', $this->record) : null)
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->excel_path)),

            Actions\Action::make('downloadPdf')
                ->label('Скачати PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->url(fn () => $this->record->pdf_path ? route('invoice.download-pdf', $this->record) : null)
                ->openUrlInNewTab()
                ->visible(fn () => !empty($this->record->pdf_path)),

            Actions\DeleteAction::make(),
        ];
    }
}
