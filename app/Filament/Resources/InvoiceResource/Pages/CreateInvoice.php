<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Enums\DiscountType;
use App\Filament\Resources\InvoiceResource;
use App\Models\Counterparty;
use App\Models\OurCompany;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract items from form data before creating
        $this->data = $data;
        return [];
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $company = OurCompany::findOrFail($this->data['our_company_id']);
            $counterparty = Counterparty::findOrFail($this->data['counterparty_id']);

            $action = app(CreateInvoiceAction::class);

            $invoice = $action->execute(
                company: $company,
                counterparty: $counterparty,
                items: $this->data['items'] ?? [],
                withVat: (bool) ($this->data['with_vat'] ?? false),
                order: null,
                comment: $this->data['comment'] ?? null,
                discountType: DiscountType::tryFrom($this->data['discount_type'] ?? '') ?? DiscountType::NONE,
                discountValue: (float) ($this->data['discount_value'] ?? 0),
            );

            Notification::make()
                ->success()
                ->title('Успіх')
                ->body("Рахунок {$invoice->invoice_number} успішно створено")
                ->send();

            return $invoice;
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Помилка')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }
}
