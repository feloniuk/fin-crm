<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Enums\DiscountType;
use App\Filament\Resources\InvoiceResource;
use App\Models\Counterparty;
use App\Models\Order;
use App\Models\OurCompany;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public ?Order $order = null;

    public function mount(): void
    {
        parent::mount();

        // Check if order_id is passed in URL
        $orderId = request()->query('order_id');
        if ($orderId) {
            $this->order = Order::find($orderId);
            if ($this->order) {
                $this->fillFormFromOrder();
            }
        }
    }

    protected function fillFormFromOrder(): void
    {
        if (!$this->order) {
            return;
        }

        // Find or create counterparty from order data
        $counterparty = null;
        if ($this->order->customer_phone) {
            $counterparty = Counterparty::where('phone', $this->order->customer_phone)->first();
        }

        // Get items from order - use order_items if available, fallback to raw_data
        if ($this->order->items()->exists()) {
            // Use order_items with discounts applied
            $items = $this->order->items()->get()->map(function ($item) {
                return [
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'unit' => 'шт.',
                    'unit_price' => $item->unit_price,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'total' => $item->total,
                ];
            })->toArray();
        } else {
            // Fallback to raw_data for legacy orders
            $items = collect($this->order->getItemsFromRawData())->map(function ($item) {
                return [
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit' => 'шт.',
                    'unit_price' => $item['unit_price'],
                    'discount_type' => null,
                    'discount_value' => 0,
                    'total' => $item['quantity'] * $item['unit_price'],
                ];
            })->toArray();
        }

        $this->form->fill([
            'order_id' => $this->order->id,
            'our_company_id' => $this->order->our_company_id,
            'with_vat' => $this->order->with_vat ?? false,
            'counterparty_id' => $counterparty?->id,
            'items' => $items,
        ]);
    }

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
            $order = isset($this->data['order_id']) ? Order::find($this->data['order_id']) : null;

            $action = app(CreateInvoiceAction::class);

            $invoice = $action->execute(
                company: $company,
                counterparty: $counterparty,
                items: $this->data['items'] ?? [],
                withVat: (bool) ($this->data['with_vat'] ?? false),
                order: $order,
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
